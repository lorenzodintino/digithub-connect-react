<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

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

/**
 * The customers the agenda shows, from the table or from every day the
 * calendar draws.
 *
 * @return array<int, string>
 */
function agendaCustomers(TestResponse $response): array
{
    $rows = $response->inertiaProps('table') !== null
        ? $response->inertiaProps('table.data')
        : collect($response->inertiaProps('calendar.days'))->flatMap(fn (array $day) => $day['bookings'])->all();

    return collect($rows)->pluck('customer')->all();
}

test('a manager sees every booking of the portal', function () {
    agendaBooking($this->operator, $this->service, ['customer_name' => 'Cliente di Giulia']);
    agendaBooking($this->colleague, $this->service, ['customer_name' => 'Cliente di Luca']);

    $response = $this->actingAs($this->manager)->get(route('app.bookings'));

    $response->assertInertia(fn (Assert $page) => $page->component('app/bookings')->where('seesWholePortal', true));

    expect(agendaCustomers($response))->toEqualCanonicalizing(['Cliente di Giulia', 'Cliente di Luca']);
});

test('each booking says what the reader may do to it', function () {
    $pending = agendaBooking($this->operator, $this->service, ['status' => BookingStatus::Pending, 'confirmed_at' => null]);

    $this->actingAs($this->manager)
        ->get(route('app.bookings'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('table.data.0.uid', $pending->uid)
            ->where('table.data.0.can.confirm', true)
            ->where('table.data.0.can.cancel', true)
            ->where('table.data.0.outcomes', []));
});

test('the details of a booking open from its reference', function () {
    $booking = agendaBooking($this->operator, $this->service, ['customer_notes' => null, 'customer_phone' => '+39 333 1234567']);

    $this->actingAs($this->manager)
        ->get(route('app.bookings', ['booking' => $booking->uid]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('details.uid', $booking->uid)
            ->where('details.email', $booking->customer_email)
            ->where('details.phone', '+39 333 1234567'));
});

test('the agenda follows the manager language', function () {
    foreach (['en', 'it'] as $locale) {
        $this->actingAs($this->manager)
            ->get(route('app.bookings').'?lang='.$locale)
            ->assertInertia(fn (Assert $page) => $page->where('locale', $locale));
    }
});

test('an operator only sees their own bookings', function () {
    agendaBooking($this->operator, $this->service, ['customer_name' => 'Cliente di Giulia']);
    agendaBooking($this->colleague, $this->service, ['customer_name' => 'Cliente di Luca']);

    $response = $this->actingAs($this->operator)->get(route('app.bookings'));

    expect(agendaCustomers($response))->toBe(['Cliente di Giulia']);
});

test('bookings of another portal never appear', function () {
    $foreign = Service::factory()->create();
    $foreignOperator = User::factory()->operator($foreign->tenant)->create();
    agendaBooking($foreignOperator, $foreign, ['customer_name' => 'Cliente estraneo']);

    $response = $this->actingAs($this->manager)->get(route('app.bookings'));

    expect(agendaCustomers($response))->not->toContain('Cliente estraneo');
});

test('the manager confirms a booking waiting for approval', function () {
    $booking = agendaBooking($this->operator, $this->service, [
        'status' => BookingStatus::Pending,
        'confirmed_at' => null,
    ]);

    $this->actingAs($this->manager)
        ->post(route('app.bookings.confirm', $booking))
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success');

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
});

test('the assigned operator can confirm their own booking', function () {
    $booking = agendaBooking($this->operator, $this->service, [
        'status' => BookingStatus::Pending,
        'confirmed_at' => null,
    ]);

    $this->actingAs($this->operator)->post(route('app.bookings.confirm', $booking))->assertRedirect();

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
});

test('an operator cannot confirm a booking of a colleague', function () {
    $booking = agendaBooking($this->colleague, $this->service, [
        'status' => BookingStatus::Pending,
        'confirmed_at' => null,
    ]);

    $this->actingAs($this->operator)
        ->post(route('app.bookings.confirm', $booking))
        ->assertForbidden();

    expect($booking->refresh()->status)->toBe(BookingStatus::Pending);
});

test('cancelling from the agenda requires a reason and records the actor', function () {
    $booking = agendaBooking($this->operator, $this->service);

    $this->actingAs($this->manager)
        ->post(route('app.bookings.cancel', $booking), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($booking->refresh()->status)->not->toBe(BookingStatus::Cancelled);

    $this->post(route('app.bookings.cancel', $booking), ['reason' => 'Chiusura straordinaria'])
        ->assertSessionHasNoErrors();

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->cancelled_by_id)->toBe($this->manager->id)
        ->and($booking->cancellation_reason)->toBe('Chiusura straordinaria');
});

test('an operator cannot cancel a booking of a colleague', function () {
    $booking = agendaBooking($this->colleague, $this->service);

    $this->actingAs($this->operator)
        ->post(route('app.bookings.cancel', $booking), ['reason' => 'Non mia'])
        ->assertForbidden();

    expect($booking->refresh()->status)->not->toBe(BookingStatus::Cancelled);
});

test('the pending counter only counts what the user may act on', function () {
    agendaBooking($this->operator, $this->service, ['status' => BookingStatus::Pending, 'confirmed_at' => null]);
    agendaBooking($this->colleague, $this->service, ['status' => BookingStatus::Pending, 'confirmed_at' => null]);

    $this->actingAs($this->manager)->get(route('app.bookings'))->assertInertia(fn (Assert $page) => $page->where('pendingCount', 2));
    $this->actingAs($this->operator)->get(route('app.bookings'))->assertInertia(fn (Assert $page) => $page->where('pendingCount', 1));
});

test('only a manager can filter by operator', function () {
    agendaBooking($this->operator, $this->service, ['customer_name' => 'Cliente di Giulia']);
    agendaBooking($this->colleague, $this->service, ['customer_name' => 'Cliente di Luca']);

    $managerView = $this->actingAs($this->manager)->get(route('app.bookings', ['operator' => $this->colleague->id]));

    $managerView->assertInertia(fn (Assert $page) => $page->has('operators', 3)->where('filters.operator', (string) $this->colleague->id));
    expect(agendaCustomers($managerView))->toBe(['Cliente di Luca']);

    // An operator only ever reads their own agenda, whatever the URL says.
    $operatorView = $this->actingAs($this->operator)->get(route('app.bookings', ['operator' => $this->colleague->id]));

    $operatorView->assertInertia(fn (Assert $page) => $page->where('operators', [])->where('filters.operator', 'all'));
    expect(agendaCustomers($operatorView))->toBe(['Cliente di Giulia']);
});

test('the past filter hides upcoming bookings', function () {
    agendaBooking($this->operator, $this->service, ['customer_name' => 'Cliente futuro']);

    Booking::factory()->forSlot($this->service, $this->operator, CarbonImmutable::now('UTC')->subWeek())
        ->create(['customer_name' => 'Cliente passato']);

    $response = $this->actingAs($this->manager)->get(route('app.bookings', ['range' => 'past']));

    expect(agendaCustomers($response))->toBe(['Cliente passato']);
});

test('an operator with upcoming bookings cannot be deleted', function () {
    agendaBooking($this->operator, $this->service);

    $this->actingAs($this->manager)
        ->get(route('app.staff'))
        ->assertInertia(fn (Assert $page) => $page->where('operators.0.upcoming_count', 1));

    $this->delete(route('app.staff.destroy', $this->operator))
        ->assertSessionHasErrors(['member' => 'This operator still has one upcoming booking. Cancel or reassign it first.']);

    $this->assertDatabaseHas('users', ['id' => $this->operator->id]);
});

test('an operator without upcoming bookings can still be deleted', function () {
    Booking::factory()->forSlot($this->service, $this->operator, CarbonImmutable::now('UTC')->subWeek())->create();

    $this->actingAs($this->manager)->delete(route('app.staff.destroy', $this->operator))->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('users', ['id' => $this->operator->id]);
});

test('the search treats wildcards as plain text', function () {
    agendaBooking($this->operator, $this->service, ['customer_name' => 'Sconto 100%']);
    agendaBooking($this->colleague, $this->service, ['customer_name' => 'Mario Rossi']);

    $response = $this->actingAs($this->manager)->get(route('app.bookings', ['q' => '%']));

    expect(agendaCustomers($response))->toBe(['Sconto 100%']);
});

test('an underscore in the search matches only itself', function () {
    agendaBooking($this->operator, $this->service, ['customer_email' => 'anna_bianchi@example.test']);
    agendaBooking($this->colleague, $this->service, ['customer_email' => 'annabianchi@example.test']);

    $this->actingAs($this->manager)
        ->get(route('app.bookings', ['q' => 'anna_bianchi']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('table.data', 1)
            ->where('table.data.0.email', 'anna_bianchi@example.test'));
});

test('a long note is cut down to a preview in the table and kept whole for the modal', function () {
    $note = 'Ho bisogno di parlare della dichiarazione dei redditi e di alcune fatture arretrate.';
    agendaBooking($this->operator, $this->service, ['customer_notes' => $note]);

    $this->actingAs($this->manager)
        ->get(route('app.bookings'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('table.data.0.notes_preview', Str::limit($note, 18))
            ->where('table.data.0.notes', $note));
});

test('a booking without a note has no preview to open', function () {
    agendaBooking($this->operator, $this->service, ['customer_notes' => null]);

    $this->actingAs($this->manager)
        ->get(route('app.bookings'))
        ->assertInertia(fn (Assert $page) => $page->where('table.data.0.notes_preview', null));
});

test('an operator cannot read the note of a colleague booking', function () {
    $booking = agendaBooking($this->colleague, $this->service, ['customer_notes' => 'Riservato']);

    $this->actingAs($this->operator)
        ->get(route('app.bookings'))
        ->assertInertia(fn (Assert $page) => $page->has('table.data', 0));

    $this->get(route('app.bookings', ['booking' => $booking->uid]))->assertForbidden();
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

    $this->actingAs($this->manager);

    $month = $this->get(route('app.bookings', ['view' => 'month']));

    $month->assertInertia(fn (Assert $page) => $page->where('table', null)->where('calendar.today', now($this->tenant->timezone)->format('Y-m-d')));
    expect(agendaCustomers($month))->toBe(['Cliente di questo mese']);

    $next = $this->get(route('app.bookings', ['view' => 'month', 'd' => $month->inertiaProps('calendar.next')]));

    expect(agendaCustomers($next))->toBe(['Cliente del mese dopo']);

    $today = $this->get(route('app.bookings', ['view' => 'month', 'd' => $next->inertiaProps('calendar.today')]));

    expect(agendaCustomers($today))->toBe(['Cliente di questo mese'])
        ->and($thisMonth->starts_at->month)->not->toBe($nextMonth->starts_at->month);
});

test('the calendar keeps the filters of the table', function () {
    $other = Service::factory()->for($this->tenant)->create(['name' => 'Altro servizio']);

    agendaBooking($this->operator, $this->service, ['customer_name' => 'Cliente filtrato']);

    Booking::factory()
        ->forSlot($other, $this->operator, CarbonImmutable::now('UTC')->addDay()->setTime(14, 0))
        ->create(['customer_name' => 'Cliente escluso']);

    $response = $this->actingAs($this->manager)
        ->get(route('app.bookings', ['view' => 'week', 'd' => now('UTC')->addDay()->format('Y-m-d'), 'service' => $this->service->id]));

    expect(agendaCustomers($response))->toBe(['Cliente filtrato']);
});

test('an operator only sees their own bookings on the calendar', function () {
    agendaBooking($this->operator, $this->service, ['customer_name' => 'Cliente mio']);
    agendaBooking($this->colleague, $this->service, ['customer_name' => 'Cliente del collega']);

    $response = $this->actingAs($this->operator)
        ->get(route('app.bookings', ['view' => 'week', 'd' => now('UTC')->addDay()->format('Y-m-d')]));

    expect(agendaCustomers($response))->toBe(['Cliente mio']);
});

test('a crowded day keeps all its bookings, and a booking opens its details', function () {
    $day = CarbonImmutable::now('UTC')->addDays(2)->startOfDay();
    $date = $day->setTimezone($this->tenant->timezone)->format('Y-m-d');

    foreach ([9, 11, 13, 15] as $index => $hour) {
        Booking::factory()
            ->forSlot($this->service, $this->operator, $day->setTime($hour, 0))
            ->create(['customer_name' => 'Cliente '.$index]);
    }

    $this->actingAs($this->manager);

    // The cell shows three and a counter for the rest; the page has them all.
    $month = $this->get(route('app.bookings', ['view' => 'month', 'd' => $date]));
    $cell = collect($month->inertiaProps('calendar.days'))->firstWhere('date', $date);

    expect(collect($cell['bookings'])->pluck('customer')->all())->toBe(['Cliente 0', 'Cliente 1', 'Cliente 2', 'Cliente 3']);

    $this->get(route('app.bookings', ['view' => 'day', 'd' => $date]))
        ->assertInertia(fn (Assert $page) => $page->has('calendar.days', 1)->where('calendar.days.0.bookings.3.customer', 'Cliente 3'));

    $last = Booking::query()->where('customer_name', 'Cliente 3')->sole();

    $this->get(route('app.bookings', ['view' => 'day', 'd' => $date, 'booking' => $last->uid]))
        ->assertInertia(fn (Assert $page) => $page->where('details.email', $last->customer_email));
});

test('the week view holds one week and steps to the next', function () {
    $day = CarbonImmutable::now('UTC')->addDay()->setTime(10, 0);

    Booking::factory()->forSlot($this->service, $this->operator, $day)
        ->create(['customer_name' => 'Cliente di questa settimana']);

    Booking::factory()->forSlot($this->service, $this->operator, $day->addWeek())
        ->create(['customer_name' => 'Cliente della prossima']);

    $this->actingAs($this->manager);

    // Pointed at the day itself: "tomorrow" belongs to next week whenever today
    // is a Sunday.
    $week = $this->get(route('app.bookings', ['view' => 'week', 'd' => $day->setTimezone($this->tenant->timezone)->format('Y-m-d')]));

    $week->assertInertia(fn (Assert $page) => $page->has('calendar.days', 7));
    expect(agendaCustomers($week))->toBe(['Cliente di questa settimana']);

    $next = $this->get(route('app.bookings', ['view' => 'week', 'd' => $week->inertiaProps('calendar.next')]));

    expect(agendaCustomers($next))->toBe(['Cliente della prossima']);
});

test('the day view lists only the day it is pointed at', function () {
    $day = CarbonImmutable::now('UTC')->addDay()->setTime(10, 0);

    Booking::factory()->forSlot($this->service, $this->operator, $day)
        ->create(['customer_name' => 'Cliente di oggi + 1']);

    Booking::factory()->forSlot($this->service, $this->operator, $day->addDay())
        ->create(['customer_name' => 'Cliente di oggi + 2']);

    $this->actingAs($this->manager);

    $first = $this->get(route('app.bookings', ['view' => 'day', 'd' => $day->setTimezone($this->tenant->timezone)->format('Y-m-d')]));

    expect(agendaCustomers($first))->toBe(['Cliente di oggi + 1']);

    $next = $this->get(route('app.bookings', ['view' => 'day', 'd' => $first->inertiaProps('calendar.next')]));

    expect(agendaCustomers($next))->toBe(['Cliente di oggi + 2']);
});

test('an unknown view in the URL falls back to the table', function () {
    $this->actingAs($this->manager)
        ->get(route('app.bookings', ['view' => 'timeline']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.view', 'table')
            ->has('table.data')
            ->where('calendar', null));
});

test('an operator cannot open the details of a colleague booking', function () {
    $booking = agendaBooking($this->colleague, $this->service);

    $this->actingAs($this->operator)
        ->get(route('app.bookings', ['booking' => $booking->uid]))
        ->assertForbidden();
});

test('the week grid draws a booking at its hour and as tall as it lasts', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00', 'Europe/Rome'));
    $this->service->update(['duration_minutes' => 90]);
    Booking::factory()->forSlot($this->service, $this->operator, CarbonImmutable::parse('2026-10-07 10:00', 'Europe/Rome')->utc())
        ->create(['customer_name' => 'Cliente lungo']);

    // Minutes from midnight on a grid that starts at 08:00: the page draws one
    // pixel per minute, 120 from the top and 90 tall.
    $this->actingAs($this->manager)
        ->get(route('app.bookings', ['view' => 'week']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('calendar.grid.startHour', 8)
            ->where('calendar.grid.days.2026-10-07.0.label', 'Cliente lungo')
            ->where('calendar.grid.days.2026-10-07.0.start', 600)
            ->where('calendar.grid.days.2026-10-07.0.end', 690));
});

test('the week grid puts overlapping bookings side by side', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00', 'Europe/Rome'));
    $slot = CarbonImmutable::parse('2026-10-07 10:00', 'Europe/Rome')->utc();
    Booking::factory()->forSlot($this->service, $this->operator, $slot)->create();
    Booking::factory()->forSlot($this->service, $this->colleague, $slot)->create();

    $response = $this->actingAs($this->manager)->get(route('app.bookings', ['view' => 'week']));

    expect(collect($response->inertiaProps('calendar.grid.days.2026-10-07'))->map(fn (array $entry) => [$entry['lane'], $entry['lanes']])->all())
        ->toEqualCanonicalizing([[0, 2], [1, 2]]);
});

test('the week grid widens to an early booking', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 06:00', 'Europe/Rome'));
    Booking::factory()->forSlot($this->service, $this->operator, CarbonImmutable::parse('2026-10-07 06:30', 'Europe/Rome')->utc())->create();

    $this->actingAs($this->manager)
        ->get(route('app.bookings', ['view' => 'week']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('calendar.grid.startHour', 6)
            ->where('calendar.grid.days.2026-10-07.0.start', 390));
});
