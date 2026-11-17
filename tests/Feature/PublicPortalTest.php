<?php

use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Studio Rossi', 'slug' => 'studio-rossi']);
    $this->operator = User::factory()->operator($this->tenant)->create();
});

function publishedService(Tenant $tenant, User $operator, array $attributes = []): Service
{
    $service = Service::factory()->for($tenant)->create($attributes);

    $service->operators()->attach($operator);

    return $service;
}

test('the public pages share the vendor named and linked in the footer', function () {
    $this->get(route('portal.show', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('vendor.name', 'DigitHub')
            ->where('vendor.url', 'https://www.digithub.it'));
});

test('the public portal page lists bookable services', function () {
    $service = publishedService($this->tenant, $this->operator, ['name' => 'Prima consulenza']);

    $this->get(route('portal.show', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/show')
            ->where('portal.name', 'Studio Rossi')
            ->where('services.0.name', $service->name)
            ->where('services.0.url', route('portal.service', [$this->tenant, $service])));
});

test('services without a bookable operator stay hidden', function () {
    $orphan = Service::factory()->for($this->tenant)->create(['name' => 'Servizio senza operatori']);

    $this->get(route('portal.show', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('services', 0));

    expect($orphan->name)->not->toBeEmpty();
});

test('hidden services stay off the public page', function () {
    $hidden = publishedService($this->tenant, $this->operator, ['name' => 'Servizio nascosto', 'is_active' => false]);

    $this->get(route('portal.show', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('services', 0));

    $this->get(route('portal.service', [$this->tenant, $hidden]))->assertNotFound();
});

test('a suspended portal is not reachable', function () {
    $this->tenant->update(['is_active' => false]);

    $this->get(route('portal.show', $this->tenant))->assertNotFound();
});

test('the service page offers the slots built from the weekly hours', function () {
    $service = publishedService($this->tenant, $this->operator, [
        'name' => 'Consulenza fiscale',
        'duration_minutes' => 60,
        'slot_interval_minutes' => 60,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
    ]);

    foreach (range(0, 6) as $weekday) {
        $this->operator->availabilityRules()->create([
            'weekday' => $weekday,
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
        ]);
    }

    $tomorrow = CarbonImmutable::now($this->tenant->timezone)->addDay()->format('Y-m-d');

    $page = $this->get(route('portal.service', [$this->tenant, $service, 'd' => $tomorrow]));

    $page->assertInertia(fn (Assert $page) => $page
        ->where('service.name', $service->name)
        ->where('operators.0.name', $this->operator->name));

    expect(offeredTimes($page))->toBe(['09:00', '10:00']);
});

test('a service of another portal is not reachable through this portal', function () {
    $foreignService = Service::factory()->create();

    $this->get(route('portal.service', [$this->tenant->slug, $foreignService->slug]))->assertNotFound();
});

test('the embed view drops the site chrome', function () {
    publishedService($this->tenant, $this->operator);

    $this->get(route('portal.show', $this->tenant).'?embed=1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('portal.embed', true)
            // Every link of the page keeps it embedded.
            ->where('services.0.url', route('portal.service', [$this->tenant, Service::query()->sole(), 'embed' => 1])));

    $this->get(route('portal.show', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('portal.embed', false));
});

test('the days of the next month drawn in the grid are bookable too', function () {
    $service = publishedService($this->tenant, $this->operator, [
        'duration_minutes' => 60,
        'slot_interval_minutes' => 60,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
    ]);

    foreach (range(0, 6) as $weekday) {
        $this->operator->availabilityRules()->create([
            'weekday' => $weekday,
            'starts_at' => '09:00:00',
            'ends_at' => '11:00:00',
        ]);
    }

    // The last day of August: the calendar of the month still shows the first
    // days of September, and those have to be as bookable as they look.
    $this->travelTo(CarbonImmutable::parse('2026-08-31 08:00', $this->tenant->timezone));

    $page = $this->get(route('portal.service', [$this->tenant, $service, 'd' => '2026-09-01']));

    $page->assertInertia(fn (Assert $page) => $page->where('calendar.days', fn ($days) => collect($days)->firstWhere('date', '2026-09-01')['available'] === true));

    expect(offeredTimes($page))->toBe(['09:00', '10:00']);
});
