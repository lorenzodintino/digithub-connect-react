<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
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
