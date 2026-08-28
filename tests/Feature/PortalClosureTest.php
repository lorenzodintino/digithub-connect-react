<?php

use App\Models\Booking;
use App\Models\PortalClosure;
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
    $this->colleague = User::factory()->operator($this->tenant)->create();

    $this->service = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 30,
        'slot_interval_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
        'max_advance_days' => 60,
    ]);

    $this->service->operators()->attach([$this->operator->id, $this->colleague->id]);

    foreach ([$this->operator, $this->colleague] as $member) {
        foreach (range(0, 6) as $weekday) {
            $member->availabilityRules()->create([
                'weekday' => $weekday,
                'starts_at' => '09:00:00',
                'ends_at' => '11:00:00',
            ]);
        }
    }

    $this->closedDay = CarbonImmutable::now($this->tenant->timezone)->addDays(3)->startOfDay();
});

test('a closure wipes the day for every operator of the portal', function () {
    PortalClosure::factory()->for($this->tenant)->create([
        'starts_on' => $this->closedDay->toDateString(),
        'ends_on' => $this->closedDay->toDateString(),
    ]);

    expect((new SlotFinder($this->service, $this->operator))->slotsForDay($this->closedDay))->toBe([])
        ->and((new SlotFinder($this->service, $this->colleague))->slotsForDay($this->closedDay))->toBe([]);

    // The day after is untouched.
    expect((new SlotFinder($this->service, $this->operator))->slotsForDay($this->closedDay->addDay()))->not->toBeEmpty();
});

test('a closure of another portal changes nothing here', function () {
    $other = Tenant::factory()->create(['timezone' => 'Europe/Rome']);

    PortalClosure::factory()->for($other)->create([
        'starts_on' => $this->closedDay->toDateString(),
        'ends_on' => $this->closedDay->toDateString(),
    ]);

    expect((new SlotFinder($this->service, $this->operator))->slotsForDay($this->closedDay))->not->toBeEmpty();
});

test('a closed day cannot be booked even by hitting the slot directly', function () {
    $slot = (new SlotFinder($this->service, $this->operator))->slotsForDay($this->closedDay)[0];

    PortalClosure::factory()->for($this->tenant)->create([
        'starts_on' => $this->closedDay->toDateString(),
        'ends_on' => $this->closedDay->addDays(6)->toDateString(),
    ]);

    expect((new SlotFinder($this->service, $this->operator))->canBookAt($slot))->toBeFalse();
});

test('a manager closes the portal for a stretch of days', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::app.availability')
        ->set('closureStartsOn', $this->closedDay->toDateString())
        ->set('closureEndsOn', $this->closedDay->addDays(13)->toDateString())
        ->set('closureReason', 'Ferie di agosto')
        ->call('addClosure')
        ->assertHasNoErrors();

    $closure = PortalClosure::query()->sole();

    expect($closure->tenant_id)->toBe($this->tenant->id)
        ->and($closure->days())->toBe(14)
        ->and($closure->reason)->toBe('Ferie di agosto');
});

test('a closure over existing bookings is refused and says how many', function () {
    Booking::factory()
        ->forSlot($this->service, $this->operator, $this->closedDay->setTime(9, 0)->setTimezone('UTC'))
        ->create();

    Livewire::actingAs($this->manager)
        ->test('pages::app.availability')
        ->set('closureStartsOn', $this->closedDay->toDateString())
        ->set('closureEndsOn', $this->closedDay->toDateString())
        ->call('addClosure')
        ->assertHasErrors('closureStartsOn');

    expect(PortalClosure::query()->count())->toBe(0);
});

test('the last day is not allowed to come before the first', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::app.availability')
        ->set('closureStartsOn', $this->closedDay->toDateString())
        ->set('closureEndsOn', $this->closedDay->subDay()->toDateString())
        ->call('addClosure')
        ->assertHasErrors('closureEndsOn');
});

test('a manager reopens a closed stretch', function () {
    $closure = PortalClosure::factory()->for($this->tenant)->create([
        'starts_on' => $this->closedDay->toDateString(),
        'ends_on' => $this->closedDay->toDateString(),
    ]);

    Livewire::actingAs($this->manager)
        ->test('pages::app.availability')
        ->call('removeClosure', $closure->id);

    expect(PortalClosure::query()->count())->toBe(0)
        ->and((new SlotFinder($this->service, $this->operator))->slotsForDay($this->closedDay))->not->toBeEmpty();
});

test('an operator can neither add nor remove a closure', function () {
    $closure = PortalClosure::factory()->for($this->tenant)->create([
        'starts_on' => $this->closedDay->toDateString(),
        'ends_on' => $this->closedDay->toDateString(),
    ]);

    Livewire::actingAs($this->operator)
        ->test('pages::app.availability')
        ->set('closureStartsOn', $this->closedDay->toDateString())
        ->set('closureEndsOn', $this->closedDay->toDateString())
        ->call('addClosure')
        ->assertForbidden();

    Livewire::actingAs($this->operator)
        ->test('pages::app.availability')
        ->call('removeClosure', $closure->id)
        ->assertForbidden();

    expect(PortalClosure::query()->count())->toBe(1);
});
