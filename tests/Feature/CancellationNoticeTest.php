<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

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
    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('createService')
        ->set('form.name', 'Consulenza')
        ->call('saveService')
        ->assertHasNoErrors();

    expect(Service::query()->latest('id')->first()->cancellation_notice_hours)->toBe(48);
});

test('the notice is set service by service', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('editService', $this->service->slug)
        ->set('form.cancellation_notice_hours', 24)
        ->call('saveService')
        ->assertHasNoErrors();

    expect($this->service->fresh()->cancellation_notice_hours)->toBe(24);
});

test('a negative notice is refused', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('editService', $this->service->slug)
        ->set('form.cancellation_notice_hours', -1)
        ->call('saveService')
        ->assertHasErrors('form.cancellation_notice_hours');
});

test('a customer cancels while there is still time', function () {
    $booking = bookingInHours(72);

    Livewire::test('pages::portal.booking', ['tenant' => $this->tenant, 'booking' => $booking])
        ->call('cancel');

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
});

test('past the notice the customer can no longer cancel or move it', function () {
    $booking = bookingInHours(5);

    expect($booking->isCancellableByCustomer())->toBeFalse()
        ->and($booking->isCancellable())->toBeTrue();

    Livewire::test('pages::portal.booking', ['tenant' => $this->tenant, 'booking' => $booking])
        ->call('cancel')
        ->assertForbidden();

    Livewire::test('pages::portal.booking', ['tenant' => $this->tenant, 'booking' => $booking])
        ->call('startReschedule')
        ->assertForbidden();

    expect($booking->fresh()->status)->not->toBe(BookingStatus::Cancelled);
});

test('the page explains the rule and offers the contacts instead of the buttons', function () {
    $booking = bookingInHours(5);

    $this->get(route('portal.booking', [$this->tenant, $booking]))
        ->assertOk()
        ->assertSee('48 hours notice', escape: false)
        ->assertSee('+39 02 1234567')
        ->assertDontSee('Cancel booking');
});

test('the portal cancels and moves whenever it wants', function () {
    $booking = bookingInHours(5);

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->call('askToCancel', $booking->id)
        ->set('cancellationReason', 'Il cliente ha telefonato')
        ->call('cancel')
        ->assertHasNoErrors();

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
});

test('a notice of zero hours leaves the customer free until the last minute', function () {
    $this->service->update(['cancellation_notice_hours' => 0]);

    $booking = bookingInHours(1);

    expect($booking->customerChangeDeadline())->toBeNull()
        ->and($booking->isCancellableByCustomer())->toBeTrue();

    Livewire::test('pages::portal.booking', ['tenant' => $this->tenant, 'booking' => $booking])
        ->call('cancel');

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
});
