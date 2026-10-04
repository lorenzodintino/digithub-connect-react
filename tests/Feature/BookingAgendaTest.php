<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->operator = User::factory()->operator($this->tenant)->create(['name' => 'Giulia']);
    $this->colleague = User::factory()->operator($this->tenant)->create(['name' => 'Luca']);
    $this->service = Service::factory()->for($this->tenant)->create(['name' => 'Consulenza']);
});

/**
 * A booking assigned to the given operator, some hours from now.
 */
function agendaBooking(User $operator, Service $service, array $attributes = []): Booking
{
    $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(10, 0);

    return Booking::factory()->forSlot($service, $operator, $startsAt)->create($attributes);
}

test('a manager sees every booking of the portal', function () {
    agendaBooking($this->operator, $this->service, ['customer_name' => 'Cliente di Giulia']);
    agendaBooking($this->colleague, $this->service, ['customer_name' => 'Cliente di Luca']);

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->assertSee('Cliente di Giulia')
        ->assertSee('Cliente di Luca');
});

test('a booking menu is identified by its accessible name', function () {
    agendaBooking($this->operator, $this->service);

    $this->actingAs($this->manager)
        ->get(route('app.bookings'))
        ->assertSee('aria-label="Booking actions"', escape: false);
});

test('a booking without notes can be opened from the table', function () {
    $booking = agendaBooking($this->operator, $this->service, ['customer_notes' => null]);

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->assertSee('wire:click="showBooking('.$booking->id.')"', escape: false)
        ->call('showBooking', $booking->id)
        ->assertSet('detailsId', $booking->id);
});

test('modal and notification close labels follow the manager language', function () {
    foreach (['en' => ['Close modal', 'Dismiss notification'], 'it' => ['Chiudi finestra', 'Chiudi notifica']] as $locale => [$modal, $notification]) {
        $this->actingAs($this->manager)
            ->get(route('app.bookings').'?lang='.$locale)
            ->assertSee('aria-label="'.$modal.'"', escape: false)
            ->assertSee('aria-label="'.$notification.'"', escape: false);
    }
});

test('an operator only sees their own bookings', function () {
    agendaBooking($this->operator, $this->service, ['customer_name' => 'Cliente di Giulia']);
    agendaBooking($this->colleague, $this->service, ['customer_name' => 'Cliente di Luca']);

    Livewire::actingAs($this->operator)
        ->test('pages::app.bookings')
        ->assertSee('Cliente di Giulia')
        ->assertDontSee('Cliente di Luca');
});

test('bookings of another portal never appear', function () {
    $foreign = Service::factory()->create();
    $foreignOperator = User::factory()->operator($foreign->tenant)->create();
    agendaBooking($foreignOperator, $foreign, ['customer_name' => 'Cliente estraneo']);

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->assertDontSee('Cliente estraneo');
});

test('the manager confirms a booking waiting for approval', function () {
    $booking = agendaBooking($this->operator, $this->service, [
        'status' => BookingStatus::Pending,
        'confirmed_at' => null,
    ]);

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->call('confirm', $booking);

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
});

test('the assigned operator can confirm their own booking', function () {
    $booking = agendaBooking($this->operator, $this->service, [
        'status' => BookingStatus::Pending,
        'confirmed_at' => null,
    ]);

    Livewire::actingAs($this->operator)
        ->test('pages::app.bookings')
        ->call('confirm', $booking);

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
});

test('an operator cannot confirm a booking of a colleague', function () {
    $booking = agendaBooking($this->colleague, $this->service, [
        'status' => BookingStatus::Pending,
        'confirmed_at' => null,
    ]);

    Livewire::actingAs($this->operator)
        ->test('pages::app.bookings')
        ->call('confirm', $booking)
        ->assertForbidden();
});

test('cancelling from the agenda requires a reason and records the actor', function () {
    $booking = agendaBooking($this->operator, $this->service);

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->call('askToCancel', $booking->id)
        ->set('cancellationReason', '')
        ->call('cancel')
        ->assertHasErrors('cancellationReason')
        ->set('cancellationReason', 'Chiusura straordinaria')
        ->call('cancel')
        ->assertHasNoErrors();

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->cancelled_by_id)->toBe($this->manager->id)
        ->and($booking->cancellation_reason)->toBe('Chiusura straordinaria');
});

test('an operator cannot cancel a booking of a colleague', function () {
    $booking = agendaBooking($this->colleague, $this->service);

    Livewire::actingAs($this->operator)
        ->test('pages::app.bookings')
        ->call('askToCancel', $booking->id)
        ->set('cancellationReason', 'Non mia')
        ->call('cancel')
        ->assertForbidden();
});

test('the pending counter only counts what the user may act on', function () {
    agendaBooking($this->operator, $this->service, ['status' => BookingStatus::Pending, 'confirmed_at' => null]);
    agendaBooking($this->colleague, $this->service, ['status' => BookingStatus::Pending, 'confirmed_at' => null]);

    Livewire::actingAs($this->manager)->test('pages::app.bookings')->assertSet('pendingCount', 2);
    Livewire::actingAs($this->operator)->test('pages::app.bookings')->assertSet('pendingCount', 1);
});

test('every filter says what it filters', function () {
    // A row of dropdowns reading "Upcoming / All statuses / All services /
    // Everyone" leaves the reader guessing which is which, and a chosen value
    // hides the dimension entirely.
    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->assertSeeInOrder(['Customer', 'Period', 'Status', 'Service', 'Operator']);
});

test('month days have a translated tooltip for opening the day', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->set('view', 'month')
        ->assertSeeHtml('title="Open this day"');
});

test('the past filter hides upcoming bookings', function () {
    agendaBooking($this->operator, $this->service, ['customer_name' => 'Cliente futuro']);

    Booking::factory()->forSlot($this->service, $this->operator, CarbonImmutable::now('UTC')->subWeek())
        ->create(['customer_name' => 'Cliente passato']);

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->set('range', 'past')
        ->assertSee('Cliente passato')
        ->assertDontSee('Cliente futuro');
});

test('an operator with upcoming bookings cannot be deleted', function () {
    agendaBooking($this->operator, $this->service);

    Livewire::actingAs($this->manager)
        ->test('pages::app.staff')
        ->call('confirmDelete', $this->operator->id)
        ->call('deleteMember');

    $this->assertDatabaseHas('users', ['id' => $this->operator->id]);
});

test('an operator without upcoming bookings can still be deleted', function () {
    Booking::factory()->forSlot($this->service, $this->operator, CarbonImmutable::now('UTC')->subWeek())->create();

    Livewire::actingAs($this->manager)
        ->test('pages::app.staff')
        ->call('confirmDelete', $this->operator->id)
        ->call('deleteMember');

    $this->assertDatabaseMissing('users', ['id' => $this->operator->id]);
});

test('the search treats wildcards as plain text', function () {
    agendaBooking($this->operator, $this->service, ['customer_name' => 'Sconto 100%']);
    agendaBooking($this->colleague, $this->service, ['customer_name' => 'Mario Rossi']);

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->set('search', '%')
        ->assertSee('Sconto 100%')
        ->assertDontSee('Mario Rossi');
});

test('an underscore in the search matches only itself', function () {
    agendaBooking($this->operator, $this->service, ['customer_email' => 'anna_bianchi@example.test']);
    agendaBooking($this->colleague, $this->service, ['customer_email' => 'annabianchi@example.test']);

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->set('search', 'anna_bianchi')
        ->assertSee('anna_bianchi@example.test')
        ->assertDontSee('annabianchi@example.test');
});

test('a long note is cut down to a preview in the table and read in full in the modal', function () {
    $note = 'Ho bisogno di parlare della dichiarazione dei redditi e di alcune fatture arretrate.';
    $booking = agendaBooking($this->operator, $this->service, ['customer_notes' => $note]);

    $component = Livewire::actingAs($this->manager)->test('pages::app.bookings');

    $component->assertDontSee($note)
        ->assertSee(Str::limit($note, 18));

    $component->call('showNotes', $booking->id)
        ->assertSet('notesId', $booking->id)
        ->assertSee($note);
});

test('a booking without a note shows no button to open one', function () {
    agendaBooking($this->operator, $this->service, ['customer_notes' => null]);

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->assertDontSee('showNotes');
});

test('an operator cannot read the note of a colleague booking', function () {
    $booking = agendaBooking($this->colleague, $this->service, ['customer_notes' => 'Riservato']);

    Livewire::actingAs($this->operator)
        ->test('pages::app.bookings')
        ->call('showNotes', $booking->id)
        ->assertForbidden();
});

test('the calendar view draws the bookings of the month on show', function () {
    // Mid month on both sides: the grid of a month always shows a few days of
    // its neighbours, and a booking sitting on those would be seen twice.
    $thisMonth = Booking::factory()
        ->forSlot($this->service, $this->operator, CarbonImmutable::now('UTC')->startOfMonth()->addDays(14)->setTime(10, 0))
        ->create(['customer_name' => 'Cliente di questo mese']);

    $nextMonth = Booking::factory()
        ->forSlot($this->service, $this->operator, CarbonImmutable::now('UTC')->startOfMonth()->addMonth()->addDays(14)->setTime(10, 0))
        ->create(['customer_name' => 'Cliente del mese dopo']);

    $component = Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->set('view', 'month');

    $component->assertSee('Cliente di questo mese')
        ->assertDontSee('Cliente del mese dopo');

    $component->call('next')
        ->assertSee('Cliente del mese dopo')
        ->assertDontSee('Cliente di questo mese');

    $component->call('goToToday')
        ->assertSee('Cliente di questo mese');

    expect($thisMonth->starts_at->month)->not->toBe($nextMonth->starts_at->month);
});

test('the calendar keeps the filters of the table', function () {
    $other = Service::factory()->for($this->tenant)->create(['name' => 'Altro servizio']);

    agendaBooking($this->operator, $this->service, ['customer_name' => 'Cliente filtrato']);

    Booking::factory()
        ->forSlot($other, $this->operator, CarbonImmutable::now('UTC')->addDay()->setTime(14, 0))
        ->create(['customer_name' => 'Cliente escluso']);

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->set('view', 'month')
        ->set('service', $this->service->id)
        ->assertSee('Cliente filtrato')
        ->assertDontSee('Cliente escluso');
});

test('an operator only sees their own bookings on the calendar', function () {
    agendaBooking($this->operator, $this->service, ['customer_name' => 'Cliente mio']);
    agendaBooking($this->colleague, $this->service, ['customer_name' => 'Cliente del collega']);

    Livewire::actingAs($this->operator)
        ->test('pages::app.bookings')
        ->set('view', 'month')
        ->assertSee('Cliente mio')
        ->assertDontSee('Cliente del collega');
});

test('a crowded day opens on its own, and a booking opens its details', function () {
    $day = CarbonImmutable::now('UTC')->addDays(2)->startOfDay();

    foreach ([9, 11, 13, 15] as $index => $hour) {
        Booking::factory()
            ->forSlot($this->service, $this->operator, $day->setTime($hour, 0))
            ->create(['customer_name' => 'Cliente '.$index]);
    }

    $component = Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->set('view', 'month');

    // Only three fit in the cell, the fourth hides behind the counter.
    $component->assertSee('Cliente 0')->assertDontSee('Cliente 3');

    $component->call('showDayView', $day->setTimezone($this->tenant->timezone)->format('Y-m-d'))
        ->assertSet('view', 'day')
        ->assertSee('Cliente 3');

    $last = Booking::query()->where('customer_name', 'Cliente 3')->sole();

    $component->call('showBooking', $last->id)
        ->assertSet('detailsId', $last->id)
        ->assertSee($last->customer_email);
});

test('the week view holds one week and steps to the next', function () {
    $day = CarbonImmutable::now('UTC')->addDay()->setTime(10, 0);

    Booking::factory()->forSlot($this->service, $this->operator, $day)
        ->create(['customer_name' => 'Cliente di questa settimana']);

    Booking::factory()->forSlot($this->service, $this->operator, $day->addWeek())
        ->create(['customer_name' => 'Cliente della prossima']);

    // Pointed at the day itself: "tomorrow" belongs to next week whenever today
    // is a Sunday.
    $component = Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->set('view', 'week')
        ->set('focus', $day->setTimezone($this->tenant->timezone)->format('Y-m-d'));

    $component->assertSee('Cliente di questa settimana')
        ->assertDontSee('Cliente della prossima');

    $component->call('next')
        ->assertSee('Cliente della prossima')
        ->assertDontSee('Cliente di questa settimana');
});

test('the day view lists only the day it is pointed at', function () {
    $day = CarbonImmutable::now('UTC')->addDay()->setTime(10, 0);

    Booking::factory()->forSlot($this->service, $this->operator, $day)
        ->create(['customer_name' => 'Cliente di oggi + 1']);

    Booking::factory()->forSlot($this->service, $this->operator, $day->addDay())
        ->create(['customer_name' => 'Cliente di oggi + 2']);

    $component = Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->set('view', 'day')
        ->set('focus', $day->setTimezone($this->tenant->timezone)->format('Y-m-d'));

    $component->assertSee('Cliente di oggi + 1')
        ->assertDontSee('Cliente di oggi + 2');

    $component->call('next')
        ->assertSee('Cliente di oggi + 2')
        ->assertDontSee('Cliente di oggi + 1');
});

test('an unknown view in the URL falls back to the table', function () {
    Livewire::actingAs($this->manager)
        ->withUrlParams(['view' => 'timeline'])
        ->test('pages::app.bookings')
        ->assertSet('view', 'table');
});

test('an operator cannot open the details of a colleague booking', function () {
    $booking = agendaBooking($this->colleague, $this->service);

    Livewire::actingAs($this->operator)
        ->test('pages::app.bookings')
        ->call('showBooking', $booking->id)
        ->assertForbidden();
});

test('the week grid draws a booking at its hour and as tall as it lasts', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00', 'Europe/Rome'));
    $this->service->update(['duration_minutes' => 90]);
    Booking::factory()->forSlot($this->service, $this->operator, CarbonImmutable::parse('2026-10-07 10:00', 'Europe/Rome')->utc())
        ->create(['customer_name' => 'Cliente lungo']);

    // The grid starts at 08:00, one pixel per minute.
    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->set('view', 'week')
        ->assertSee('Cliente lungo')
        ->assertSeeHtml('top: 120px; height: 90px;');
});

test('the week grid puts overlapping bookings side by side', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00', 'Europe/Rome'));
    $slot = CarbonImmutable::parse('2026-10-07 10:00', 'Europe/Rome')->utc();
    Booking::factory()->forSlot($this->service, $this->operator, $slot)->create();
    Booking::factory()->forSlot($this->service, $this->colleague, $slot)->create();

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->set('view', 'week')
        ->assertSeeHtml('left: calc(0 * 100% / 2 + 1px); width: calc(100% / 2 - 2px);')
        ->assertSeeHtml('left: calc(1 * 100% / 2 + 1px); width: calc(100% / 2 - 2px);');
});

test('the week grid widens to an early booking', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 06:00', 'Europe/Rome'));
    Booking::factory()->forSlot($this->service, $this->operator, CarbonImmutable::parse('2026-10-07 06:30', 'Europe/Rome')->utc())->create();

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->set('view', 'week')
        ->assertSee('06:00')
        ->assertSeeHtml('top: 30px;');
});
