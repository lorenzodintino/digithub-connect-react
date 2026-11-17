<?php

use App\Models\Booking;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaitingListEntry;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $this->manager = User::factory()->manager($this->tenant)->create();
});

/**
 * Put the portal on a plan without the given features.
 *
 * @param  list<string>  $features
 */
function without(Tenant $tenant, array $features, array $limits = []): Plan
{
    $plan = Plan::factory()->lacking($features)->create($limits);
    $tenant->update(['plan_id' => $plan->id]);

    return $plan;
}

function packageOf(Tenant $tenant, string $name): array
{
    $first = Service::factory()->for($tenant)->create(['name' => $name.' A', 'capacity' => 1]);
    $second = Service::factory()->for($tenant)->create(['name' => $name.' B', 'capacity' => 1]);
    $operator = User::factory()->operator($tenant)->create();
    $first->operators()->attach($operator);
    $second->operators()->attach($operator);

    return serviceForm($tenant, [
        'name' => $name,
        'is_package' => true,
        'package_items' => [['service_id' => $first->id, 'pause_after_minutes' => 0], ['service_id' => $second->id, 'pause_after_minutes' => 0]],
        'operator_ids' => [$operator->id],
    ]);
}

// Group services: Studio only

test('a plan without group services refuses a new group service and points to a bigger plan', function () {
    without($this->tenant, ['group_services']);

    $this->actingAs($this->manager)
        ->post(route('app.services.store'), serviceForm($this->tenant, ['name' => 'Yoga', 'capacity' => 8]))
        ->assertSessionHasErrors(['capacity' => 'Group services are not included in your plan: ask for a bigger plan.', 'plan_limit' => 'groups']);

    expect(Service::query()->where('name', 'Yoga')->exists())->toBeFalse();
});

test('a group service that already exists keeps being edited without the feature', function () {
    $service = Service::factory()->for($this->tenant)->create(['capacity' => 6]);
    without($this->tenant, ['group_services']);

    $this->actingAs($this->manager)
        ->put(route('app.services.update', $service->slug), serviceForm($service, ['capacity' => 7]))
        ->assertSessionHasNoErrors();

    expect($service->fresh()->capacity)->toBe(7);
});

test('an individual service cannot be turned into a group one without the feature', function () {
    $service = Service::factory()->for($this->tenant)->create(['capacity' => 1]);
    without($this->tenant, ['group_services']);

    $this->actingAs($this->manager)
        ->put(route('app.services.update', $service->slug), serviceForm($service, ['capacity' => 4]))
        ->assertSessionHasErrors('capacity');
});

test('a plan with group services, or no plan, allows them', function () {
    $this->actingAs($this->manager)->post(route('app.services.store'), serviceForm($this->tenant, ['name' => 'Yoga', 'capacity' => 8]))->assertSessionHasNoErrors();

    Plan::factory()->unlimited()->create();
    $this->tenant->update(['plan_id' => Plan::query()->first()->id]);

    $this->post(route('app.services.store'), serviceForm($this->tenant, ['name' => 'Pilates', 'capacity' => 8]))->assertSessionHasNoErrors();
});

// Service packages: Pro with a limit

test('a plan without packages refuses a new one', function () {
    without($this->tenant, ['packages']);

    $this->actingAs($this->manager)
        ->post(route('app.services.store'), packageOf($this->tenant, 'Benessere'))
        ->assertSessionHasErrors(['is_package' => 'Service packages are not included in your plan: ask for a bigger plan.', 'plan_limit' => 'packages']);
});

test('packages stop at the limit of the plan, and an existing one stays editable', function () {
    without($this->tenant, [], ['max_packages' => 1, 'max_services' => null]);

    $this->actingAs($this->manager)->post(route('app.services.store'), packageOf($this->tenant, 'Primo'))->assertSessionHasNoErrors();
    $this->post(route('app.services.store'), packageOf($this->tenant, 'Secondo'))
        ->assertSessionHasErrors(['is_package' => 'Your plan includes up to one package: delete it or ask for a bigger plan.', 'plan_limit' => 'packages']);

    $first = Service::query()->where('name', 'Primo')->sole();

    $this->put(route('app.services.update', $first->slug), serviceForm($first, ['name' => 'Primo rinominato']))->assertSessionHasNoErrors();

    expect($first->fresh()->name)->toBe('Primo rinominato');
});

// Waiting list: Pro with a limit

function waitingFor(Tenant $tenant, array $changes = []): array
{
    return [
        'name' => 'Anna Ferrari',
        'email' => 'anna@example.test',
        'phone' => '',
        'from' => CarbonImmutable::now($tenant->timezone)->toDateString(),
        'to' => CarbonImmutable::now($tenant->timezone)->addDays(20)->toDateString(),
        'privacy' => true,
        'tz' => 'Europe/Rome',
        ...$changes,
    ];
}

test('a plan without the waiting list takes nobody new and the service page does not offer it', function () {
    $service = Service::factory()->for($this->tenant)->create();
    without($this->tenant, ['waiting_list']);

    $this->post(route('portal.service.waiting-list', [$this->tenant, $service]), waitingFor($this->tenant))->assertNotFound();

    expect(WaitingListEntry::query()->count())->toBe(0);

    $this->get(route('portal.service', [$this->tenant, $service]))->assertInertia(fn (Assert $page) => $page->where('service.waiting_list_open', false));
});

test('the waiting list stops at the places of the plan, but the same person can change their dates', function () {
    $service = Service::factory()->for($this->tenant)->create();
    without($this->tenant, [], ['max_waiting_list' => 2]);

    foreach (['a@example.test', 'b@example.test'] as $email) {
        $this->post(route('portal.service.waiting-list', [$this->tenant, $service]), waitingFor($this->tenant, ['email' => $email]))->assertSessionHasNoErrors();
    }

    $this->post(route('portal.service.waiting-list', [$this->tenant, $service]), waitingFor($this->tenant, ['email' => 'c@example.test']))
        ->assertSessionHasErrors(['email' => 'The waiting list is full for now. Try again later.']);

    $this->post(route('portal.service.waiting-list', [$this->tenant, $service]), waitingFor($this->tenant, ['email' => 'a@example.test']))->assertSessionHasNoErrors();

    expect(WaitingListEntry::query()->count())->toBe(2);

    $this->get(route('portal.service', [$this->tenant, $service]))->assertInertia(fn (Assert $page) => $page->where('service.waiting_list_open', false));
});

test('a plan with a waiting list that is not full offers it', function () {
    $service = Service::factory()->for($this->tenant)->create();
    without($this->tenant, [], ['max_waiting_list' => 5]);

    $this->get(route('portal.service', [$this->tenant, $service]))->assertInertia(fn (Assert $page) => $page->where('service.waiting_list_open', true));
});

// Calendar buttons and embedding: Pro

function bookingOf(Tenant $tenant): Booking
{
    $service = Service::factory()->for($tenant)->create(['duration_minutes' => 30, 'slot_interval_minutes' => 30, 'min_notice_minutes' => 0]);
    $operator = User::factory()->operator($tenant)->create();
    $service->operators()->attach($operator);

    foreach (range(0, 6) as $weekday) {
        $operator->availabilityRules()->create(['weekday' => $weekday, 'starts_at' => '09:00:00', 'ends_at' => '12:00:00']);
    }

    $slot = (new SlotFinder($service, $operator))->slotsForDay(CarbonImmutable::now($tenant->timezone)->addDay()->startOfDay())[0];

    return Booking::factory()->forSlot($service, $operator, $slot)->create();
}

test('a plan without the calendar buttons shows none and refuses the file', function () {
    $booking = bookingOf($this->tenant);
    without($this->tenant, ['calendar_buttons']);

    $this->get(route('portal.booking', [$this->tenant, $booking]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('booking.calendar_links', null));
    $this->get(route('portal.booking.calendar', [$this->tenant, $booking]))->assertNotFound();
});

test('a plan with the calendar buttons shows them', function () {
    $booking = bookingOf($this->tenant);
    without($this->tenant, ['waiting_list']);

    $this->get(route('portal.booking', [$this->tenant, $booking]))->assertInertia(fn (Assert $page) => $page->has('booking.calendar_links.google'));
    $this->get(route('portal.booking.calendar', [$this->tenant, $booking]))->assertOk();
});

test('a plan without embedding draws the whole page even inside an iframe', function () {
    without($this->tenant, ['embed']);

    $this->get(route('portal.show', [$this->tenant, 'embed' => 1]))->assertInertia(fn (Assert $page) => $page->where('portal.embed', false));

    $this->actingAs($this->manager)->get(route('app.portal'))->assertInertia(fn (Assert $page) => $page->where('embedAvailable', false));
});

test('a plan with embedding, or no plan, honours embed=1', function () {
    $this->get(route('portal.show', [$this->tenant, 'embed' => 1]))->assertInertia(fn (Assert $page) => $page->where('portal.embed', true));

    $this->actingAs($this->manager)->get(route('app.portal'))->assertInertia(fn (Assert $page) => $page->where('embedAvailable', true));
});
