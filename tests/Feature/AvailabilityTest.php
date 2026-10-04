<?php

use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Week;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->operator = User::factory()->operator($this->tenant)->create();
});

test('an operator saves their weekly working hours', function () {
    Livewire::actingAs($this->operator)
        ->test('pages::app.availability')
        ->set('schedule.1.enabled', true)
        ->set('schedule.1.ranges', [
            ['starts_at' => '09:00', 'ends_at' => '13:00'],
            ['starts_at' => '14:00', 'ends_at' => '18:00'],
        ])
        ->set('schedule.2.enabled', false)
        ->call('saveSchedule')
        ->assertHasNoErrors();

    $rules = $this->operator->availabilityRules()->orderBy('starts_at')->get();

    expect($rules)->toHaveCount(2)
        ->and($rules->pluck('weekday')->unique()->all())->toBe([1])
        ->and($rules->first()->starts_at)->toStartWith('09:00');
});

test('saving replaces the previous weekly rules', function () {
    AvailabilityRule::factory()->for($this->operator)->onWeekday(3)->create();

    Livewire::actingAs($this->operator)
        ->test('pages::app.availability')
        ->set('schedule.1.enabled', true)
        ->set('schedule.1.ranges', [['starts_at' => '10:00', 'ends_at' => '12:00']])
        ->set('schedule.3.enabled', false)
        ->call('saveSchedule');

    expect($this->operator->availabilityRules()->pluck('weekday')->all())->toBe([1]);
});

test('a range cannot end before it starts', function () {
    Livewire::actingAs($this->operator)
        ->test('pages::app.availability')
        ->set('schedule.1.enabled', true)
        ->set('schedule.1.ranges', [['starts_at' => '18:00', 'ends_at' => '09:00']])
        ->call('saveSchedule')
        ->assertHasErrors('schedule.1.ranges.0.ends_at');

    expect($this->operator->availabilityRules()->count())->toBe(0);
});

test('overlapping ranges on the same day are rejected', function () {
    Livewire::actingAs($this->operator)
        ->test('pages::app.availability')
        ->set('schedule.1.enabled', true)
        ->set('schedule.1.ranges', [
            ['starts_at' => '09:00', 'ends_at' => '13:00'],
            ['starts_at' => '12:00', 'ends_at' => '18:00'],
        ])
        ->call('saveSchedule')
        ->assertHasErrors('schedule.1.ranges.1.starts_at');
});

test('fixing overlapping ranges clears the error from the screen', function () {
    $component = Livewire::actingAs($this->operator)
        ->test('pages::app.availability')
        ->set('schedule.1.enabled', true)
        ->set('schedule.1.ranges', [
            ['starts_at' => '09:00', 'ends_at' => '13:00'],
            ['starts_at' => '12:00', 'ends_at' => '18:00'],
        ])
        ->call('saveSchedule')
        ->assertHasErrors('schedule.1.ranges.1.starts_at');

    // Corrected, saved: the previous complaint must not stay on screen.
    $component
        ->set('schedule.1.ranges', [
            ['starts_at' => '09:00', 'ends_at' => '13:00'],
            ['starts_at' => '14:00', 'ends_at' => '18:00'],
        ])
        ->call('saveSchedule')
        ->assertHasNoErrors();

    expect($this->operator->availabilityRules()->count())->toBe(2);
});

test('a corrected time block clears its earlier conflict message', function () {
    $service = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
    ]);
    $service->operators()->attach($this->operator);

    $day = CarbonImmutable::now($this->tenant->timezone)->addDay()->startOfDay();
    Booking::factory()->forSlot($service, $this->operator, $day->setTime(10, 0)->setTimezone('UTC'))->create();

    $component = Livewire::actingAs($this->operator)
        ->test('pages::app.availability')
        ->set('blockDate', $day->format('Y-m-d'))
        ->set('blockStartsAt', '09:30')
        ->set('blockEndsAt', '11:00')
        ->call('addTimeBlock')
        ->assertHasErrors('blockDate');

    $component
        ->set('blockStartsAt', '14:00')
        ->set('blockEndsAt', '15:00')
        ->call('addTimeBlock')
        ->assertHasNoErrors();
});

test('a manager edits the availability of their own operator', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::app.availability')
        ->assertSee('label="Operator"', escape: false)
        ->set('memberId', $this->operator->id)
        ->set('schedule.5.enabled', true)
        ->set('schedule.5.ranges', [['starts_at' => '09:00', 'ends_at' => '17:00']])
        ->call('saveSchedule')
        ->assertHasNoErrors();

    expect($this->operator->availabilityRules()->count())->toBe(1);
});

test('a manager cannot edit the availability of another portal', function () {
    $foreignOperator = User::factory()->operator()->create();

    Livewire::actingAs($this->manager)
        ->test('pages::app.availability')
        ->set('memberId', $foreignOperator->id)
        ->assertForbidden();
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
    $component = Livewire::actingAs($this->operator)->test('pages::app.availability');

    foreach (Week::ordered() as $weekday) {
        $component
            ->set("schedule.{$weekday}.enabled", true)
            ->set("schedule.{$weekday}.ranges", [['starts_at' => '09:00', 'ends_at' => '13:00']]);
    }

    $component->call('saveSchedule')->assertHasNoErrors()->assertSee('Cliente delle tre');

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

    $component = Livewire::actingAs($this->operator)->test('pages::app.availability');

    foreach (Week::ordered() as $weekday) {
        $component
            ->set("schedule.{$weekday}.enabled", true)
            ->set("schedule.{$weekday}.ranges", [['starts_at' => '09:00', 'ends_at' => '18:00']]);
    }

    $component->call('saveSchedule')->assertDontSee('Cliente delle dieci');
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

    $component = Livewire::actingAs($this->operator)->test('pages::app.availability');

    foreach (Week::ordered() as $weekday) {
        $component
            ->set("schedule.{$weekday}.enabled", true)
            ->set("schedule.{$weekday}.ranges", [['starts_at' => '09:00', 'ends_at' => '13:00']]);
    }

    $component->call('saveSchedule')->assertDontSee('Cliente con eccezione');
});

test('a date override is stored for the selected day', function () {
    Livewire::actingAs($this->operator)
        ->test('pages::app.availability')
        ->set('exceptionDate', now()->addWeek()->toDateString())
        ->set('exceptionUnavailable', true)
        ->set('exceptionReason', 'Ferie')
        ->call('addException')
        ->assertHasNoErrors();

    $exception = $this->operator->availabilityExceptions()->sole();

    expect($exception->is_unavailable)->toBeTrue()
        ->and($exception->reason)->toBe('Ferie');
});

test('a date override cannot be set in the past', function () {
    Livewire::actingAs($this->operator)
        ->test('pages::app.availability')
        ->set('exceptionDate', now()->subDay()->toDateString())
        ->call('addException')
        ->assertHasErrors('exceptionDate');
});
