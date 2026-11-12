<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'timezone' => 'Europe/Rome',
        'contact_phone' => '+39 02 1234567',
    ]);

    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->operator = User::factory()->operator($this->tenant)->create();

    $this->service = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 30,
        'slot_interval_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
        'cancellation_notice_hours' => 48,
    ]);

    $this->service->operators()->attach($this->operator);

    foreach (range(0, 6) as $weekday) {
        $this->operator->availabilityRules()->create([
            'weekday' => $weekday,
            'starts_at' => '09:00:00',
            'ends_at' => '18:00:00',
        ]);
    }
});

/**
 * A booking starting the given number of hours from now.
 */
function bookingInHours(int $hours, array $attributes = []): Booking
{
    return Booking::factory()
        ->forSlot(test()->service, test()->operator, CarbonImmutable::now('UTC')->addHours($hours))
        ->create($attributes);
}

test('a new service asks for two days of notice by default', function () {
    $this->actingAs($this->manager)
        ->post(route('app.services.store'), serviceForm($this->tenant, ['name' => 'Consulenza']))
        ->assertSessionHasNoErrors();

    expect(Service::query()->latest('id')->first()->cancellation_notice_hours)->toBe(48);
});

test('the notice is set service by service', function () {
    $this->actingAs($this->manager)
        ->put(route('app.services.update', $this->service->slug), serviceForm($this->service, ['cancellation_notice_hours' => 24]))
        ->assertSessionHasNoErrors();

    expect($this->service->fresh()->cancellation_notice_hours)->toBe(24);
});

test('a negative notice is refused', function () {
    $this->actingAs($this->manager)
        ->put(route('app.services.update', $this->service->slug), serviceForm($this->service, ['cancellation_notice_hours' => -1]))
        ->assertSessionHasErrors('cancellation_notice_hours');
});

test('a customer cancels while there is still time', function () {
    $booking = bookingInHours(72);

    $this->post(route('portal.booking.cancel', [$this->tenant, $booking]))->assertRedirect();

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
});

test('past the notice the customer can no longer cancel or move it', function () {
    $booking = bookingInHours(5);

    expect($booking->isCancellableByCustomer())->toBeFalse()
        ->and($booking->isCancellable())->toBeTrue();

    $this->post(route('portal.booking.cancel', [$this->tenant, $booking]))->assertForbidden();
    $this->post(route('portal.booking.reschedule', [$this->tenant, $booking]), ['slot' => $booking->starts_at->addDay()->toIso8601String()])->assertForbidden();

    expect($booking->fresh()->status)->not->toBe(BookingStatus::Cancelled);
});

test('the page explains the rule and offers the contacts instead of the buttons', function () {
    $booking = bookingInHours(5);

    $this->get(route('portal.booking', [$this->tenant, $booking]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('booking.cancellable', true)
            ->where('booking.can', ['cancel' => false, 'reschedule' => false])
            ->where('booking.notice_hours', 48)
            ->where('booking.contact.phone', '+39 02 1234567'));
});

test('the portal cancels and moves whenever it wants', function () {
    $booking = bookingInHours(5);

    $this->actingAs($this->manager)
        ->post(route('app.bookings.cancel', $booking), ['reason' => 'Il cliente ha telefonato'])
        ->assertSessionHasNoErrors();

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
});

test('a notice of zero hours leaves the customer free until the last minute', function () {
    $this->service->update(['cancellation_notice_hours' => 0]);

    $booking = bookingInHours(1);

    expect($booking->customerChangeDeadline())->toBeNull()
        ->and($booking->isCancellableByCustomer())->toBeTrue();

    $this->post(route('portal.booking.cancel', [$this->tenant, $booking]))->assertRedirect();

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
});
