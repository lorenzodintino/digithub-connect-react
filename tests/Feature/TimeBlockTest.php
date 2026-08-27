<?php

use App\Actions\Availability\BlockTime;
use App\Exceptions\PeriodHasBookingsException;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TimeBlock;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
            'ends_at' => '12:00:00',
        ]);
    }
});

/**
 * Tomorrow at the given local hour, in the portal timezone.
 */
function tomorrowAt(int $hour, int $minute = 0): CarbonImmutable
{
    return CarbonImmutable::now('Europe/Rome')->addDay()->startOfDay()->setTime($hour, $minute);
}

test('blocking a free period removes its slots', function () {
    app(BlockTime::class)->handle($this->operator, tomorrowAt(10), tomorrowAt(11), 'Riunione');

    $slots = (new SlotFinder($this->service, $this->operator))->slotsForDay(tomorrowAt(0));
    $times = array_map(fn (CarbonImmutable $slot) => $slot->setTimezone('Europe/Rome')->format('H:i'), $slots);

    expect($times)->toBe(['09:00', '09:30', '11:00', '11:30'])
        ->and(TimeBlock::query()->sole()->reason)->toBe('Riunione');
});

test('blocking a period that already holds a booking is refused', function () {
    Booking::factory()->forSlot($this->service, $this->operator, tomorrowAt(10)->setTimezone('UTC'))->create();

    app(BlockTime::class)->handle($this->operator, tomorrowAt(9, 30), tomorrowAt(11));
})->throws(PeriodHasBookingsException::class);

test('a refused block leaves nothing behind', function () {
    Booking::factory()->forSlot($this->service, $this->operator, tomorrowAt(10)->setTimezone('UTC'))->create();

    try {
        app(BlockTime::class)->handle($this->operator, tomorrowAt(10), tomorrowAt(11));
    } catch (PeriodHasBookingsException) {
        // Expected.
    }

    expect(TimeBlock::count())->toBe(0);
});

test('a cancelled booking does not stand in the way of a block', function () {
    Booking::factory()
        ->forSlot($this->service, $this->operator, tomorrowAt(10)->setTimezone('UTC'))
        ->cancelled()
        ->create();

    app(BlockTime::class)->handle($this->operator, tomorrowAt(10), tomorrowAt(11));

    expect(TimeBlock::count())->toBe(1);
});

test('a block touching a booking without overlapping it is allowed', function () {
    // Booking runs 10:00 to 10:30; the block starts exactly when it ends.
    Booking::factory()->forSlot($this->service, $this->operator, tomorrowAt(10)->setTimezone('UTC'))->create();

    app(BlockTime::class)->handle($this->operator, tomorrowAt(10, 30), tomorrowAt(11));

    expect(TimeBlock::count())->toBe(1);
});

test('the availability screen reports the conflict instead of saving', function () {
    Booking::factory()->forSlot($this->service, $this->operator, tomorrowAt(10)->setTimezone('UTC'))->create();

    Livewire::actingAs($this->operator)
        ->test('pages::app.availability')
        ->set('blockDate', tomorrowAt(0)->format('Y-m-d'))
        ->set('blockStartsAt', '09:30')
        ->set('blockEndsAt', '11:00')
        ->call('addTimeBlock')
        ->assertHasErrors('blockDate');

    expect(TimeBlock::count())->toBe(0);
});

test('the availability screen saves a block on a free period', function () {
    Livewire::actingAs($this->operator)
        ->test('pages::app.availability')
        ->set('blockDate', tomorrowAt(0)->format('Y-m-d'))
        ->set('blockStartsAt', '10:00')
        ->set('blockEndsAt', '11:00')
        ->set('blockReason', 'Visita medica')
        ->call('addTimeBlock')
        ->assertHasNoErrors();

    $block = TimeBlock::query()->sole();

    expect($block->reason)->toBe('Visita medica')
        ->and($block->starts_at->setTimezone('Europe/Rome')->format('H:i'))->toBe('10:00');
});

test('a manager blocks time for one of their operators', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::app.availability')
        ->set('memberId', $this->operator->id)
        ->set('blockDate', tomorrowAt(0)->format('Y-m-d'))
        ->set('blockStartsAt', '09:00')
        ->set('blockEndsAt', '10:00')
        ->call('addTimeBlock')
        ->assertHasNoErrors();

    expect($this->operator->timeBlocks()->count())->toBe(1);
});

test('a block ending before it starts is refused', function () {
    app(BlockTime::class)->handle($this->operator, tomorrowAt(11), tomorrowAt(10));
})->throws(HttpException::class);

test('a booking cannot be placed inside an existing block', function () {
    app(BlockTime::class)->handle($this->operator, tomorrowAt(10), tomorrowAt(11));

    $finder = new SlotFinder($this->service, $this->operator);

    expect($finder->canBookAt(tomorrowAt(10)->setTimezone('UTC')))->toBeFalse()
        ->and($finder->canBookAt(tomorrowAt(11)->setTimezone('UTC')))->toBeTrue();
});
