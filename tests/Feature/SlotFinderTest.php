<?php

use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TimeBlock;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;

/**
 * Build a portal with one operator working the given weekday hours.
 *
 * @param  array<int, array{0: int, 1: string, 2: string}>  $rules
 * @return array{0: Service, 1: User}
 */
function portalWithHours(array $rules = [[1, '09:00', '13:00']], array $serviceAttributes = [], string $timezone = 'Europe/Rome'): array
{
    $tenant = Tenant::factory()->create(['timezone' => $timezone]);

    $service = Service::factory()->for($tenant)->create([
        'duration_minutes' => 30,
        'slot_interval_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
        'max_advance_days' => 60,
        ...$serviceAttributes,
    ]);

    $operator = User::factory()->operator($tenant)->create();
    $service->operators()->attach($operator);

    foreach ($rules as [$weekday, $startsAt, $endsAt]) {
        $operator->availabilityRules()->create([
            'weekday' => $weekday,
            'starts_at' => $startsAt.':00',
            'ends_at' => $endsAt.':00',
        ]);
    }

    return [$service, $operator];
}

/**
 * The next occurrence of a weekday, as a local date in the portal timezone.
 */
function nextWeekday(int $weekday, string $timezone = 'Europe/Rome'): CarbonImmutable
{
    $day = CarbonImmutable::now($timezone)->addDay()->startOfDay();

    while ($day->dayOfWeek !== $weekday) {
        $day = $day->addDay();
    }

    return $day;
}

/**
 * Render slots as local H:i strings for readable assertions.
 *
 * @param  array<int, CarbonImmutable>  $slots
 * @return array<int, string>
 */
function localTimes(array $slots, string $timezone = 'Europe/Rome'): array
{
    return array_map(fn (CarbonImmutable $slot) => $slot->setTimezone($timezone)->format('H:i'), $slots);
}

test('a working window is split into slots of the service duration', function () {
    [$service, $operator] = portalWithHours();

    $slots = (new SlotFinder($service, $operator))->slotsForDay(nextWeekday(1));

    expect(localTimes($slots))->toBe(['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30']);
});

test('the slot interval is independent from the duration', function () {
    [$service, $operator] = portalWithHours([[1, '09:00', '10:00']], [
        'duration_minutes' => 30,
        'slot_interval_minutes' => 15,
    ]);

    $slots = (new SlotFinder($service, $operator))->slotsForDay(nextWeekday(1));

    expect(localTimes($slots))->toBe(['09:00', '09:15', '09:30']);
});

test('a slot that would run past closing time is not offered', function () {
    [$service, $operator] = portalWithHours([[1, '09:00', '10:00']], [
        'duration_minutes' => 45,
        'slot_interval_minutes' => 30,
    ]);

    $slots = (new SlotFinder($service, $operator))->slotsForDay(nextWeekday(1));

    expect(localTimes($slots))->toBe(['09:00']);
});

test('two windows on the same day produce two blocks of slots', function () {
    [$service, $operator] = portalWithHours([[1, '09:00', '10:00'], [1, '14:00', '15:00']], [
        'slot_interval_minutes' => 60,
        'duration_minutes' => 60,
    ]);

    $slots = (new SlotFinder($service, $operator))->slotsForDay(nextWeekday(1));

    expect(localTimes($slots))->toBe(['09:00', '14:00']);
});

test('a day without working hours has no slots', function () {
    [$service, $operator] = portalWithHours();

    expect((new SlotFinder($service, $operator))->slotsForDay(nextWeekday(2)))->toBe([]);
});

test('an existing booking removes its slot', function () {
    [$service, $operator] = portalWithHours([[1, '09:00', '11:00']]);
    $monday = nextWeekday(1);

    Booking::factory()->forSlot($service, $operator, $monday->setTime(10, 0)->setTimezone('UTC'))->create();

    $slots = (new SlotFinder($service, $operator))->slotsForDay($monday);

    expect(localTimes($slots))->toBe(['09:00', '09:30', '10:30']);
});

test('a cancelled booking frees its slot again', function () {
    [$service, $operator] = portalWithHours([[1, '09:00', '10:00']]);
    $monday = nextWeekday(1);

    Booking::factory()
        ->forSlot($service, $operator, $monday->setTime(9, 0)->setTimezone('UTC'))
        ->cancelled()
        ->create();

    expect(localTimes((new SlotFinder($service, $operator))->slotsForDay($monday)))->toBe(['09:00', '09:30']);
});

test('buffers widen the window a booking takes away', function () {
    [$service, $operator] = portalWithHours([[1, '09:00', '12:00']], [
        'buffer_before_minutes' => 15,
        'buffer_after_minutes' => 15,
    ]);
    $monday = nextWeekday(1);

    Booking::factory()->forSlot($service, $operator, $monday->setTime(10, 0)->setTimezone('UTC'))->create();

    // 09:30 would end at 10:00 but its 15 minute tail runs into the booking's own
    // 15 minute head, and 10:30 starts inside the booking's tail.
    expect(localTimes((new SlotFinder($service, $operator))->slotsForDay($monday)))
        ->toBe(['09:00', '11:00', '11:30']);
});

test('a time block removes the slots it covers', function () {
    [$service, $operator] = portalWithHours([[1, '09:00', '12:00']]);
    $monday = nextWeekday(1);

    TimeBlock::factory()->for($operator)->between(
        $monday->setTime(10, 0)->setTimezone('UTC'),
        $monday->setTime(11, 0)->setTimezone('UTC'),
    )->create();

    expect(localTimes((new SlotFinder($service, $operator))->slotsForDay($monday)))
        ->toBe(['09:00', '09:30', '11:00', '11:30']);
});

test('a closed date override wipes the day', function () {
    [$service, $operator] = portalWithHours();
    $monday = nextWeekday(1);

    $operator->availabilityExceptions()->create([
        'date' => $monday->format('Y-m-d'),
        'is_unavailable' => true,
        'reason' => 'Ferie',
    ]);

    expect((new SlotFinder($service, $operator))->slotsForDay($monday))->toBe([]);
});

test('a date override with custom hours replaces the weekly hours', function () {
    [$service, $operator] = portalWithHours([[1, '09:00', '18:00']]);
    $monday = nextWeekday(1);

    $operator->availabilityExceptions()->create([
        'date' => $monday->format('Y-m-d'),
        'is_unavailable' => false,
        'starts_at' => '15:00:00',
        'ends_at' => '16:00:00',
    ]);

    expect(localTimes((new SlotFinder($service, $operator))->slotsForDay($monday)))->toBe(['15:00', '15:30']);
});

test('the minimum notice hides slots that are too close', function () {
    $timezone = 'Europe/Rome';
    $now = CarbonImmutable::now($timezone)->startOfDay()->addHours(9);
    CarbonImmutable::setTestNow($now->setTimezone('UTC'));

    [$service, $operator] = portalWithHours([[$now->dayOfWeek, '09:00', '13:00']], [
        'min_notice_minutes' => 120,
    ]);

    $slots = (new SlotFinder($service, $operator))->slotsForDay($now);

    expect(localTimes($slots))->toBe(['11:00', '11:30', '12:00', '12:30']);

    CarbonImmutable::setTestNow();
});

test('the booking window hides days too far ahead', function () {
    // Every weekday is open, so the cut-off is the only thing limiting the range.
    $everyDay = array_map(fn (int $weekday) => [$weekday, '09:00', '10:00'], range(0, 6));

    [$service, $operator] = portalWithHours($everyDay, ['max_advance_days' => 3]);

    $finder = new SlotFinder($service, $operator);
    $from = CarbonImmutable::now('Europe/Rome')->startOfDay();

    $days = array_keys($finder->slotsBetween($from, $from->addDays(30)));

    expect($days)->not->toBeEmpty()
        ->and(CarbonImmutable::parse(end($days), 'Europe/Rome'))
        ->toBeLessThanOrEqual($from->addDays(3)->endOfDay());
});

test('slots are returned in UTC and shift with the portal timezone', function () {
    [$service, $operator] = portalWithHours([[1, '09:00', '10:00']], [], 'Europe/Rome');

    $slot = (new SlotFinder($service, $operator))->slotsForDay(nextWeekday(1))[0];

    expect($slot->timezone->getName())->toBe('UTC')
        ->and($slot->setTimezone('Europe/Rome')->format('H:i'))->toBe('09:00')
        ->and($slot->setTimezone('Europe/London')->format('H:i'))->toBe('08:00');
});

test('an operator who is not bookable offers nothing', function () {
    [$service, $operator] = portalWithHours();
    $operator->update(['is_bookable' => false]);

    expect((new SlotFinder($service, $operator->fresh()))->slotsForDay(nextWeekday(1)))->toBe([]);
});

test('a hidden service offers nothing', function () {
    [$service, $operator] = portalWithHours();
    $service->update(['is_active' => false]);

    expect((new SlotFinder($service->fresh(), $operator))->slotsForDay(nextWeekday(1)))->toBe([]);
});

test('canBookAt accepts a real slot and refuses anything else', function () {
    [$service, $operator] = portalWithHours([[1, '09:00', '10:00']]);
    $monday = nextWeekday(1);
    $finder = new SlotFinder($service, $operator);

    expect($finder->canBookAt($monday->setTime(9, 0)->setTimezone('UTC')))->toBeTrue()
        ->and($finder->canBookAt($monday->setTime(9, 10)->setTimezone('UTC')))->toBeFalse()
        ->and($finder->canBookAt($monday->setTime(20, 0)->setTimezone('UTC')))->toBeFalse();
});

test('a booking being rescheduled does not block its own slot', function () {
    [$service, $operator] = portalWithHours([[1, '09:00', '10:00']]);
    $monday = nextWeekday(1);

    $booking = Booking::factory()
        ->forSlot($service, $operator, $monday->setTime(9, 0)->setTimezone('UTC'))
        ->create();

    $finder = new SlotFinder($service, $operator);

    expect($finder->canBookAt($monday->setTime(9, 0)->setTimezone('UTC')))->toBeFalse()
        ->and($finder->ignoring($booking)->canBookAt($monday->setTime(9, 0)->setTimezone('UTC')))->toBeTrue();
});

test('a booking on one service blocks the operator on every other service', function () {
    // Two services of different length, one operator, both open at 09:00.
    [$short, $operator] = portalWithHours([[1, '09:00', '11:00']], [
        'duration_minutes' => 15,
        'slot_interval_minutes' => 15,
    ]);

    $long = Service::factory()->for($short->tenant)->create([
        'duration_minutes' => 30,
        'slot_interval_minutes' => 15,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
        'max_advance_days' => 60,
    ]);

    $long->operators()->attach($operator);

    $monday = nextWeekday(1);

    Booking::factory()->forSlot($short, $operator, $monday->setTime(9, 0)->setTimezone('UTC'))->create();

    // The agenda belongs to the operator, not to the service: 09:00 is gone for
    // both, and the longer service starts again once the short one is over.
    expect(localTimes((new SlotFinder($long, $operator))->slotsForDay($monday)))
        ->toBe(['09:15', '09:30', '09:45', '10:00', '10:15', '10:30'])
        ->and(localTimes((new SlotFinder($short, $operator))->slotsForDay($monday)))
        ->toBe(['09:15', '09:30', '09:45', '10:00', '10:15', '10:30', '10:45']);
});

test('a colleague stays free when someone else is booked', function () {
    [$service, $operator] = portalWithHours([[1, '09:00', '10:00']]);

    $colleague = User::factory()->operator($service->tenant)->create();
    $service->operators()->attach($colleague);

    $colleague->availabilityRules()->create([
        'weekday' => 1,
        'starts_at' => '09:00:00',
        'ends_at' => '10:00:00',
    ]);

    $monday = nextWeekday(1);

    Booking::factory()->forSlot($service, $operator, $monday->setTime(9, 0)->setTimezone('UTC'))->create();

    expect(localTimes((new SlotFinder($service, $operator))->slotsForDay($monday)))->toBe(['09:30'])
        ->and(localTimes((new SlotFinder($service, $colleague))->slotsForDay($monday)))->toBe(['09:00', '09:30']);
});
