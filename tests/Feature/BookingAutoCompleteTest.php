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
    $this->operator = User::factory()->operator($this->tenant)->create();
    $this->service = Service::factory()->for($this->tenant)->create(['duration_minutes' => 30]);
});

/**
 * A booking that ended the given number of hours ago.
 */
function bookingEndedHoursAgo(int $hours, array $attributes = []): Booking
{
    $startsAt = CarbonImmutable::now('UTC')->subHours($hours)->subMinutes(30);

    return Booking::factory()
        ->forSlot(test()->service, test()->operator, $startsAt)
        ->create($attributes);
}

test('a confirmed appointment is closed once the grace period is over', function () {
    $booking = bookingEndedHoursAgo(25, ['status' => BookingStatus::Confirmed]);

    $this->artisan('bookings:complete-past')->assertSuccessful();

    expect($booking->fresh()->status)->toBe(BookingStatus::Completed);
});

test('an appointment that ended a moment ago is left alone', function () {
    $booking = bookingEndedHoursAgo(2, ['status' => BookingStatus::Confirmed]);

    $this->artisan('bookings:complete-past')->assertSuccessful();

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

test('only confirmed appointments are closed', function () {
    $cancelled = bookingEndedHoursAgo(30, ['status' => BookingStatus::Cancelled]);
    $pending = bookingEndedHoursAgo(30, ['status' => BookingStatus::Pending]);
    $noShow = bookingEndedHoursAgo(30, ['status' => BookingStatus::NoShow]);

    $this->artisan('bookings:complete-past')->assertSuccessful();

    expect($cancelled->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($pending->fresh()->status)->toBe(BookingStatus::Pending)
        ->and($noShow->fresh()->status)->toBe(BookingStatus::NoShow);
});

test('a closed appointment can still be turned into a no show by hand', function () {
    $booking = bookingEndedHoursAgo(25, ['status' => BookingStatus::Confirmed]);

    $this->artisan('bookings:complete-past')->assertSuccessful();

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->call('recordOutcome', $booking->uid, BookingStatus::NoShow->value)
        ->assertHasNoErrors();

    expect($booking->fresh()->status)->toBe(BookingStatus::NoShow);
});
