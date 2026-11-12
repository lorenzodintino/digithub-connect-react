<?php

use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Week;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->operator = User::factory()->operator($this->tenant)->create();
});

/**
 * The weekly hours as the page sends them: every day of the week, the ones
 * listed open with their ranges, the others closed.
 *
 * @param  array<int, array<int, array{0: string, 1: string}>>  $open  weekday => [[from, to], …]
 * @return array<int, array{enabled: bool, ranges: array<int, array{starts_at: string, ends_at: string}>}>
 */
function weeklyHours(array $open): array
{
    return collect(Week::ordered())->mapWithKeys(fn (int $weekday) => [$weekday => [
        'enabled' => isset($open[$weekday]),
        'ranges' => array_map(fn (array $range) => ['starts_at' => $range[0], 'ends_at' => $range[1]], $open[$weekday] ?? [['09:00', '18:00']]),
    ]])->all();
}

/**
 * The same hours on every day of the week.
 *
 * @return array<int, array{enabled: bool, ranges: array<int, array{starts_at: string, ends_at: string}>}>
 */
function everyDay(string $from, string $to): array
{
    return weeklyHours(array_fill_keys(Week::ordered(), [[$from, $to]]));
}

test('the page loads the weekly hours of the signed in person', function () {
    AvailabilityRule::factory()->for($this->operator)->onWeekday(1)->between('09:00', '13:00')->create();

    $this->actingAs($this->operator)->get(route('app.availability'))->assertInertia(fn (Assert $page) => $page
        ->component('app/availability')
        ->where('member.id', $this->operator->id)
        ->where('members', [])
        ->where('schedule.0.weekday', 1)
        ->where('schedule.0.enabled', true)
        ->where('schedule.0.ranges', [['starts_at' => '09:00', 'ends_at' => '13:00']])
        ->where('schedule.1.enabled', false));
});

test('an operator saves their weekly working hours', function () {
    $this->actingAs($this->operator)
        ->put(route('app.availability.schedule'), [
            'member' => $this->operator->id,
            'schedule' => weeklyHours([1 => [['09:00', '13:00'], ['14:00', '18:00']]]),
        ])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Availability saved.');

    $rules = $this->operator->availabilityRules()->orderBy('starts_at')->get();

    expect($rules)->toHaveCount(2)
        ->and($rules->pluck('weekday')->unique()->all())->toBe([1])
        ->and($rules->first()->starts_at)->toStartWith('09:00');
});

test('saving replaces the previous weekly rules', function () {
    AvailabilityRule::factory()->for($this->operator)->onWeekday(3)->create();

    $this->actingAs($this->operator)->put(route('app.availability.schedule'), [
        'member' => $this->operator->id,
        'schedule' => weeklyHours([1 => [['10:00', '12:00']]]),
    ]);

    expect($this->operator->availabilityRules()->pluck('weekday')->all())->toBe([1]);
});

test('a range cannot end before it starts', function () {
    $this->actingAs($this->operator)
        ->put(route('app.availability.schedule'), [
            'member' => $this->operator->id,
            'schedule' => weeklyHours([1 => [['18:00', '09:00']]]),
        ])
        ->assertSessionHasErrors(['schedule.1.ranges.0.ends_at' => 'The end time must be after the start time on Monday.']);

    expect($this->operator->availabilityRules()->count())->toBe(0);
});

test('overlapping ranges on the same day are rejected', function () {
    $this->actingAs($this->operator)
        ->put(route('app.availability.schedule'), [
            'member' => $this->operator->id,
            'schedule' => weeklyHours([1 => [['09:00', '13:00'], ['12:00', '18:00']]]),
        ])
        ->assertSessionHasErrors(['schedule.1.ranges.1.starts_at' => 'Time ranges overlap on Monday.']);
});

test('fixed overlapping ranges save without the earlier complaint', function () {
    $this->actingAs($this->operator);

    $this->put(route('app.availability.schedule'), [
        'member' => $this->operator->id,
        'schedule' => weeklyHours([1 => [['09:00', '13:00'], ['12:00', '18:00']]]),
    ])->assertSessionHasErrors('schedule.1.ranges.1.starts_at');

    // Corrected, saved: the previous complaint must not come back.
    $this->put(route('app.availability.schedule'), [
        'member' => $this->operator->id,
        'schedule' => weeklyHours([1 => [['09:00', '13:00'], ['14:00', '18:00']]]),
    ])->assertSessionHasNoErrors();

    expect($this->operator->availabilityRules()->count())->toBe(2);
});

test('a corrected time block saves after its earlier conflict', function () {
    $service = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
    ]);
    $service->operators()->attach($this->operator);

    $day = CarbonImmutable::now($this->tenant->timezone)->addDay()->startOfDay();
    Booking::factory()->forSlot($service, $this->operator, $day->setTime(10, 0)->setTimezone('UTC'))->create();

    $this->actingAs($this->operator);
    $block = ['member' => $this->operator->id, 'date' => $day->format('Y-m-d'), 'reason' => ''];

    $this->post(route('app.availability.blocks.store'), [...$block, 'starts_at' => '09:30', 'ends_at' => '11:00'])
        ->assertSessionHasErrors('date');

    $this->post(route('app.availability.blocks.store'), [...$block, 'starts_at' => '14:00', 'ends_at' => '15:00'])
        ->assertSessionHasNoErrors();
});

test('a manager edits the availability of their own operator', function () {
    $this->actingAs($this->manager)
        ->get(route('app.availability', ['member' => $this->operator->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('member.id', $this->operator->id)
            ->has('members', 2));

    $this->put(route('app.availability.schedule'), [
        'member' => $this->operator->id,
        'schedule' => weeklyHours([5 => [['09:00', '17:00']]]),
    ])->assertSessionHasNoErrors();

    expect($this->operator->availabilityRules()->count())->toBe(1);
});

test('a manager cannot edit the availability of another portal', function () {
    $foreignOperator = User::factory()->operator()->create();

    $this->actingAs($this->manager);

    $this->get(route('app.availability', ['member' => $foreignOperator->id]))->assertForbidden();
    $this->put(route('app.availability.schedule'), [
        'member' => $foreignOperator->id,
        'schedule' => weeklyHours([1 => [['09:00', '17:00']]]),
    ])->assertForbidden();

    expect($foreignOperator->availabilityRules()->count())->toBe(0);
});

test('an operator cannot edit a colleague', function () {
    $colleague = User::factory()->operator($this->tenant)->create();

    $this->actingAs($this->operator)->get(route('app.availability', ['member' => $colleague->id]))->assertForbidden();
});

test('narrowing the hours saves anyway and reports the uncovered bookings', function () {
    $service = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 30,
        'slot_interval_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
    ]);
    $service->operators()->attach($this->operator);

    foreach (range(0, 6) as $weekday) {
        $this->operator->availabilityRules()->create([
            'weekday' => $weekday,
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
        ]);
    }

    $slot = CarbonImmutable::now($this->tenant->timezone)->addDay()->startOfDay()->setTime(15, 0);
    Booking::factory()->forSlot($service, $this->operator, $slot->setTimezone('UTC'))
        ->create(['customer_name' => 'Cliente delle tre']);

    // The new hours stop at 13:00, leaving the 15:00 booking outside them.
    $this->actingAs($this->operator)
        ->put(route('app.availability.schedule'), ['member' => $this->operator->id, 'schedule' => everyDay('09:00', '13:00')])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'warning')
        ->assertInertiaFlash('toast.message', 'Saved, but one booking now falls outside your hours.');

    $this->get(route('app.availability'))->assertInertia(fn (Assert $page) => $page
        ->where('uncovered.count', 1)
        ->where('uncovered.items.0.line', fn (string $line) => str_contains($line, 'Cliente delle tre')));

    expect($this->operator->availabilityRules()->count())->toBe(7)
        ->and($this->operator->availabilityRules()->first()->ends_at)->toStartWith('13:00');
});

test('hours that still cover every booking raise no warning', function () {
    $service = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
    ]);
    $service->operators()->attach($this->operator);

    $slot = CarbonImmutable::now($this->tenant->timezone)->addDay()->startOfDay()->setTime(10, 0);
    Booking::factory()->forSlot($service, $this->operator, $slot->setTimezone('UTC'))
        ->create(['customer_name' => 'Cliente delle dieci']);

    $this->actingAs($this->operator)
        ->put(route('app.availability.schedule'), ['member' => $this->operator->id, 'schedule' => everyDay('09:00', '18:00')])
        ->assertInertiaFlash('toast.type', 'success');

    $this->get(route('app.availability'))->assertInertia(fn (Assert $page) => $page->where('uncovered.count', 0));
});

test('a booking on a day with its own override is not reported as uncovered', function () {
    $service = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
    ]);
    $service->operators()->attach($this->operator);

    $day = CarbonImmutable::now($this->tenant->timezone)->addDay()->startOfDay();

    Booking::factory()->forSlot($service, $this->operator, $day->setTime(15, 0)->setTimezone('UTC'))
        ->create(['customer_name' => 'Cliente con eccezione']);

    // That single day keeps its own hours, so the weekly rules do not apply.
    $this->operator->availabilityExceptions()->create([
        'date' => $day->format('Y-m-d'),
        'is_unavailable' => false,
        'starts_at' => '14:00:00',
        'ends_at' => '18:00:00',
    ]);

    $this->actingAs($this->operator)
        ->put(route('app.availability.schedule'), ['member' => $this->operator->id, 'schedule' => everyDay('09:00', '13:00')])
        ->assertInertiaFlash('toast.type', 'success');

    $this->get(route('app.availability'))->assertInertia(fn (Assert $page) => $page->where('uncovered.count', 0));
});

test('a date override is stored for the selected day', function () {
    $this->actingAs($this->operator)
        ->post(route('app.availability.exceptions.store'), [
            'member' => $this->operator->id,
            'date' => now()->addWeek()->toDateString(),
            'unavailable' => true,
            'reason' => 'Ferie',
        ])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Date override saved.');

    $exception = $this->operator->availabilityExceptions()->sole();

    expect($exception->is_unavailable)->toBeTrue()
        ->and($exception->reason)->toBe('Ferie');
});

test('a date override cannot be set in the past', function () {
    $this->actingAs($this->operator)
        ->post(route('app.availability.exceptions.store'), [
            'member' => $this->operator->id,
            'date' => now()->subDay()->toDateString(),
            'unavailable' => true,
        ])
        ->assertSessionHasErrors('date');
});

test('a colleague cannot remove somebody else\'s override or block', function () {
    $colleague = User::factory()->operator($this->tenant)->create();
    $exception = $colleague->availabilityExceptions()->create(['date' => now()->addWeek()->toDateString(), 'is_unavailable' => true]);

    $this->actingAs($this->operator)->delete(route('app.availability.exceptions.destroy', $exception))->assertForbidden();

    $this->assertModelExists($exception);
});
