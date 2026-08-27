<?php

use App\Actions\Availability\SetDateOverride;
use App\Exceptions\PeriodHasBookingsException;
use App\Models\AvailabilityException;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->operator = User::factory()->operator($this->tenant)->create();

    $this->service = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 30,
        'slot_interval_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
    ]);

    $this->service->operators()->attach($this->operator);

    foreach (range(0, 6) as $weekday) {
        $this->operator->availabilityRules()->create([
            'weekday' => $weekday,
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
        ]);
    }
});

/**
 * Tomorrow at the given local hour, in the portal timezone.
 */
function overrideDayAt(int $hour, int $minute = 0): CarbonImmutable
{
    return CarbonImmutable::now('Europe/Rome')->addDay()->startOfDay()->setTime($hour, $minute);
}

/**
 * The date the tests operate on, as Y-m-d in the portal timezone.
 */
function overrideDate(): string
{
    return overrideDayAt(0)->format('Y-m-d');
}

test('closing a free day is saved and wipes its slots', function () {
    app(SetDateOverride::class)->handle($this->operator, overrideDate(), true, reason: 'Ferie');

    expect(AvailabilityException::query()->sole()->reason)->toBe('Ferie')
        ->and((new SlotFinder($this->service, $this->operator))->slotsForDay(overrideDayAt(0)))->toBe([]);
});

test('closing a day that holds a booking is refused', function () {
    Booking::factory()->forSlot($this->service, $this->operator, overrideDayAt(10)->setTimezone('UTC'))->create();

    app(SetDateOverride::class)->handle($this->operator, overrideDate(), true);
})->throws(PeriodHasBookingsException::class);

test('a refused override leaves nothing behind', function () {
    Booking::factory()->forSlot($this->service, $this->operator, overrideDayAt(10)->setTimezone('UTC'))->create();

    try {
        app(SetDateOverride::class)->handle($this->operator, overrideDate(), true);
    } catch (PeriodHasBookingsException) {
        // Expected.
    }

    expect(AvailabilityException::count())->toBe(0);
});

test('a cancelled booking does not keep a day open', function () {
    Booking::factory()
        ->forSlot($this->service, $this->operator, overrideDayAt(10)->setTimezone('UTC'))
        ->cancelled()
        ->create();

    app(SetDateOverride::class)->handle($this->operator, overrideDate(), true);

    expect(AvailabilityException::count())->toBe(1);
});

test('custom hours that still cover the booking are accepted', function () {
    Booking::factory()->forSlot($this->service, $this->operator, overrideDayAt(10)->setTimezone('UTC'))->create();

    app(SetDateOverride::class)->handle($this->operator, overrideDate(), false, '09:00', '12:00');

    expect(AvailabilityException::count())->toBe(1);
});

test('custom hours that would strand a booking are refused', function () {
    // Booked at 10:00, but the day would only stay open from 14:00.
    Booking::factory()->forSlot($this->service, $this->operator, overrideDayAt(10)->setTimezone('UTC'))->create();

    app(SetDateOverride::class)->handle($this->operator, overrideDate(), false, '14:00', '17:00');
})->throws(PeriodHasBookingsException::class);

test('custom hours ending mid booking are refused', function () {
    // Booking runs 10:00 to 10:30; closing at 10:15 would cut it in half.
    Booking::factory()->forSlot($this->service, $this->operator, overrideDayAt(10)->setTimezone('UTC'))->create();

    app(SetDateOverride::class)->handle($this->operator, overrideDate(), false, '09:00', '10:15');
})->throws(PeriodHasBookingsException::class);

test('custom hours ending exactly when the booking ends are accepted', function () {
    Booking::factory()->forSlot($this->service, $this->operator, overrideDayAt(10)->setTimezone('UTC'))->create();

    app(SetDateOverride::class)->handle($this->operator, overrideDate(), false, '09:00', '10:30');

    expect(AvailabilityException::count())->toBe(1);
});

test('a booking on another day never blocks the override', function () {
    Booking::factory()
        ->forSlot($this->service, $this->operator, overrideDayAt(10)->addDays(3)->setTimezone('UTC'))
        ->create();

    app(SetDateOverride::class)->handle($this->operator, overrideDate(), true);

    expect(AvailabilityException::count())->toBe(1);
});

test('the availability screen reports the conflict instead of saving', function () {
    Booking::factory()->forSlot($this->service, $this->operator, overrideDayAt(10)->setTimezone('UTC'))->create();

    Livewire::actingAs($this->operator)
        ->test('pages::app.availability')
        ->set('exceptionDate', overrideDate())
        ->set('exceptionUnavailable', true)
        ->call('addException')
        ->assertHasErrors('exceptionDate');

    expect(AvailabilityException::count())->toBe(0);
});

test('the availability screen saves an override on a free day', function () {
    Livewire::actingAs($this->operator)
        ->test('pages::app.availability')
        ->set('exceptionDate', overrideDate())
        ->set('exceptionUnavailable', true)
        ->set('exceptionReason', 'Ferie')
        ->call('addException')
        ->assertHasNoErrors();

    expect(AvailabilityException::query()->sole()->reason)->toBe('Ferie');
});

test('a manager sets an override for one of their operators', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::app.availability')
        ->set('memberId', $this->operator->id)
        ->set('exceptionDate', overrideDate())
        ->set('exceptionUnavailable', false)
        ->set('exceptionStartsAt', '10:00')
        ->set('exceptionEndsAt', '14:00')
        ->call('addException')
        ->assertHasNoErrors();

    expect($this->operator->availabilityExceptions()->count())->toBe(1);
});
