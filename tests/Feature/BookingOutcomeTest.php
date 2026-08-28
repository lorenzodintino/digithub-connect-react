<?php

use App\Actions\Bookings\RecordOutcome;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->operator = User::factory()->operator($this->tenant)->create();
    $this->stranger = User::factory()->operator($this->tenant)->create();
    $this->service = Service::factory()->for($this->tenant)->create();
});

/**
 * A booking of the given operator, finished or still to come.
 */
function outcomeBooking(User $operator, Service $service, bool $past = true, array $attributes = []): Booking
{
    $startsAt = CarbonImmutable::now('UTC')->addDays($past ? -1 : 1)->setTime(10, 0);

    return Booking::factory()->forSlot($service, $operator, $startsAt)->create($attributes);
}

test('a finished appointment can be marked completed', function () {
    $booking = outcomeBooking($this->operator, $this->service);

    Livewire::actingAs($this->operator)
        ->test('pages::app.bookings', ['range' => 'past'])
        ->call('recordOutcome', $booking->uid, BookingStatus::Completed->value);

    expect($booking->fresh()->status)->toBe(BookingStatus::Completed);
});

test('a customer who never turned up is marked no show', function () {
    $booking = outcomeBooking($this->operator, $this->service);

    Livewire::actingAs($this->operator)
        ->test('pages::app.bookings', ['range' => 'past'])
        ->call('recordOutcome', $booking->uid, BookingStatus::NoShow->value);

    expect($booking->fresh()->status)->toBe(BookingStatus::NoShow);
});

test('a wrong outcome can be corrected into the other one', function () {
    $booking = outcomeBooking($this->operator, $this->service, attributes: ['status' => BookingStatus::NoShow]);

    app(RecordOutcome::class)->handle($booking, BookingStatus::Completed);

    expect($booking->fresh()->status)->toBe(BookingStatus::Completed);
});

test('an appointment still to come has no outcome yet', function () {
    $booking = outcomeBooking($this->operator, $this->service, past: false);

    expect($booking->acceptsOutcome())->toBeFalse();

    app(RecordOutcome::class)->handle($booking, BookingStatus::Completed);
})->throws(HttpException::class);

test('a cancelled booking is never given an outcome', function () {
    $booking = outcomeBooking($this->operator, $this->service, attributes: ['status' => BookingStatus::Cancelled]);

    expect($booking->acceptsOutcome())->toBeFalse();

    app(RecordOutcome::class)->handle($booking, BookingStatus::Completed);
})->throws(HttpException::class);

test('only the outcome statuses are accepted', function () {
    $booking = outcomeBooking($this->operator, $this->service);

    app(RecordOutcome::class)->handle($booking, BookingStatus::Confirmed);
})->throws(HttpException::class);

test('an operator cannot touch a colleague booking', function () {
    $booking = outcomeBooking($this->stranger, $this->service);

    Livewire::actingAs($this->operator)
        ->test('pages::app.bookings', ['range' => 'past'])
        ->call('recordOutcome', $booking->uid, BookingStatus::Completed->value)
        ->assertForbidden();

    expect($booking->fresh()->status)->not->toBe(BookingStatus::Completed);
});

test('the manager records the outcome for anybody in the portal', function () {
    $booking = outcomeBooking($this->operator, $this->service);

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings', ['range' => 'past'])
        ->call('recordOutcome', $booking->uid, BookingStatus::Completed->value);

    expect($booking->fresh()->status)->toBe(BookingStatus::Completed);
});

test('the agenda offers the outcome only on finished appointments', function () {
    outcomeBooking($this->operator, $this->service, attributes: ['customer_name' => 'Cliente passato']);

    Livewire::actingAs($this->operator)
        ->test('pages::app.bookings', ['range' => 'past'])
        ->assertSee('Cliente passato')
        ->assertSee(__('Mark as completed'));

    outcomeBooking($this->operator, $this->service, past: false, attributes: ['customer_name' => 'Cliente futuro']);

    Livewire::actingAs($this->operator)
        ->test('pages::app.bookings')
        ->assertSee('Cliente futuro')
        ->assertDontSee(__('Mark as completed'));
});

test('a no show frees the slot it used to block', function () {
    $booking = outcomeBooking($this->operator, $this->service);

    expect(Booking::query()->blocking()->count())->toBe(1);

    app(RecordOutcome::class)->handle($booking, BookingStatus::NoShow);

    expect(Booking::query()->blocking()->count())->toBe(0);
});
