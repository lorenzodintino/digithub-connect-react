<?php

use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\ConfirmBooking;
use App\Actions\Bookings\CreateBooking;
use App\Actions\Bookings\RescheduleBooking;
use App\Enums\BookingStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A portal open every weekday morning, with one operator on one service.
 *
 * @return array{0: Service, 1: User}
 */
function bookablePortal(array $serviceAttributes = []): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);

    $service = Service::factory()->for($tenant)->create([
        'duration_minutes' => 30,
        'slot_interval_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
        'max_advance_days' => 60,
        'requires_confirmation' => false,
        'price_cents' => 5000,
        ...$serviceAttributes,
    ]);

    $operator = User::factory()->operator($tenant)->create();
    $service->operators()->attach($operator);

    foreach (range(0, 6) as $weekday) {
        $operator->availabilityRules()->create([
            'weekday' => $weekday,
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
        ]);
    }

    return [$service, $operator];
}

/**
 * The first slot available tomorrow.
 */
function firstSlot(Service $service, User $operator): CarbonImmutable
{
    $tomorrow = CarbonImmutable::now($service->tenant->timezone)->addDay()->startOfDay();

    return (new SlotFinder($service, $operator))->slotsForDay($tomorrow)[0];
}

/**
 * The customer payload used across these tests.
 *
 * @return array<string, string>
 */
function customerPayload(array $overrides = []): array
{
    return [
        'name' => 'Mario Bianchi',
        'email' => 'mario@example.com',
        'phone' => '+39 333 1234567',
        'notes' => 'Prima visita',
        'timezone' => 'Europe/London',
        ...$overrides,
    ];
}

test('a booking is created on a free slot and snapshots the price', function () {
    [$service, $operator] = bookablePortal();
    $slot = firstSlot($service, $operator);

    $booking = app(CreateBooking::class)->handle($service, $operator, $slot, customerPayload());

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->confirmed_at)->not->toBeNull()
        ->and($booking->starts_at->equalTo($slot))->toBeTrue()
        ->and($booking->ends_at->equalTo($slot->addMinutes(30)))->toBeTrue()
        ->and($booking->price_cents)->toBe(5000)
        ->and($booking->customer_timezone)->toBe('Europe/London')
        ->and($booking->uid)->toHaveLength(32);
});

test('a service needing approval produces a pending booking', function () {
    [$service, $operator] = bookablePortal(['requires_confirmation' => true]);

    $booking = app(CreateBooking::class)->handle($service, $operator, firstSlot($service, $operator), customerPayload());

    expect($booking->status)->toBe(BookingStatus::Pending)
        ->and($booking->confirmed_at)->toBeNull();
});

test('the booked slot disappears from the available ones', function () {
    [$service, $operator] = bookablePortal();
    $slot = firstSlot($service, $operator);

    app(CreateBooking::class)->handle($service, $operator, $slot, customerPayload());

    $remaining = (new SlotFinder($service, $operator))->slotsForDay($slot->setTimezone('Europe/Rome'));

    expect(collect($remaining)->contains(fn (CarbonImmutable $s) => $s->equalTo($slot)))->toBeFalse();
});

test('booking the same slot twice is refused', function () {
    [$service, $operator] = bookablePortal();
    $slot = firstSlot($service, $operator);

    app(CreateBooking::class)->handle($service, $operator, $slot, customerPayload());

    app(CreateBooking::class)->handle($service, $operator, $slot, customerPayload(['email' => 'altro@example.com']));
})->throws(SlotUnavailableException::class);

test('a slot outside the working hours is refused', function () {
    [$service, $operator] = bookablePortal();
    $midnight = CarbonImmutable::now('Europe/Rome')->addDay()->startOfDay()->setTimezone('UTC');

    app(CreateBooking::class)->handle($service, $operator, $midnight, customerPayload());
})->throws(SlotUnavailableException::class);

test('an operator from another portal cannot be booked', function () {
    [$service] = bookablePortal();
    $stranger = User::factory()->operator()->create();

    app(CreateBooking::class)->handle($service, $stranger, CarbonImmutable::now()->addDay(), customerPayload());
})->throws(HttpException::class);

test('cancelling frees the slot and records who did it', function () {
    [$service, $operator] = bookablePortal();
    $slot = firstSlot($service, $operator);
    $manager = User::factory()->manager($service->tenant)->create();

    $booking = app(CreateBooking::class)->handle($service, $operator, $slot, customerPayload());

    $booking = app(CancelBooking::class)->handle($booking, $manager, 'Chiusura straordinaria');

    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->cancelled_by_id)->toBe($manager->id)
        ->and($booking->cancellation_reason)->toBe('Chiusura straordinaria');

    $slots = (new SlotFinder($service, $operator))->slotsForDay($slot->setTimezone('Europe/Rome'));

    expect(collect($slots)->contains(fn (CarbonImmutable $s) => $s->equalTo($slot)))->toBeTrue();
});

test('a customer cancellation leaves no actor behind', function () {
    [$service, $operator] = bookablePortal();

    $booking = app(CreateBooking::class)->handle($service, $operator, firstSlot($service, $operator), customerPayload());
    $booking = app(CancelBooking::class)->handle($booking);

    expect($booking->cancelled_by_id)->toBeNull()
        ->and($booking->cancellation_reason)->toBeNull();
});

test('a booking already cancelled cannot be cancelled again', function () {
    [$service, $operator] = bookablePortal();

    $booking = app(CreateBooking::class)->handle($service, $operator, firstSlot($service, $operator), customerPayload());
    app(CancelBooking::class)->handle($booking);

    app(CancelBooking::class)->handle($booking->refresh());
})->throws(HttpException::class);

test('a past booking cannot be cancelled', function () {
    $booking = Booking::factory()->create([
        'starts_at' => CarbonImmutable::now('UTC')->subHour(),
        'ends_at' => CarbonImmutable::now('UTC')->subMinutes(30),
    ]);

    expect($booking->isCancellable())->toBeFalse();
});

test('rescheduling moves the booking and keeps its public link', function () {
    [$service, $operator] = bookablePortal();
    $slot = firstSlot($service, $operator);

    $booking = app(CreateBooking::class)->handle($service, $operator, $slot, customerPayload());
    $uid = $booking->uid;

    $target = $slot->addHour();
    $booking = app(RescheduleBooking::class)->handle($booking, $target);

    expect($booking->uid)->toBe($uid)
        ->and($booking->starts_at->equalTo($target))->toBeTrue()
        ->and($booking->original_starts_at->equalTo($slot))->toBeTrue()
        ->and($booking->rescheduled_at)->not->toBeNull();
});

test('rescheduling onto the slot it already occupies is allowed', function () {
    [$service, $operator] = bookablePortal();
    $slot = firstSlot($service, $operator);

    $booking = app(CreateBooking::class)->handle($service, $operator, $slot, customerPayload());
    $booking = app(RescheduleBooking::class)->handle($booking, $slot);

    expect($booking->starts_at->equalTo($slot))->toBeTrue();
});

test('rescheduling onto a taken slot is refused', function () {
    [$service, $operator] = bookablePortal();
    $slot = firstSlot($service, $operator);

    $first = app(CreateBooking::class)->handle($service, $operator, $slot, customerPayload());
    $second = app(CreateBooking::class)->handle($service, $operator, $slot->addHour(), customerPayload(['email' => 'due@example.com']));

    app(RescheduleBooking::class)->handle($second, $first->starts_at);
})->throws(SlotUnavailableException::class);

test('confirming a pending booking marks it confirmed', function () {
    [$service, $operator] = bookablePortal(['requires_confirmation' => true]);

    $booking = app(CreateBooking::class)->handle($service, $operator, firstSlot($service, $operator), customerPayload());
    $booking = app(ConfirmBooking::class)->handle($booking);

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->confirmed_at)->not->toBeNull();
});

test('a pending booking already blocks its slot', function () {
    [$service, $operator] = bookablePortal(['requires_confirmation' => true]);
    $slot = firstSlot($service, $operator);

    app(CreateBooking::class)->handle($service, $operator, $slot, customerPayload());

    expect((new SlotFinder($service, $operator))->canBookAt($slot))->toBeFalse();
});
