<?php

use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;

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

test('the public portal page lists bookable services', function () {
    $service = publishedService($this->tenant, $this->operator, ['name' => 'Prima consulenza']);

    $this->get(route('portal.show', $this->tenant))
        ->assertOk()
        ->assertSee('Studio Rossi')
        ->assertSee($service->name);
});

test('services without a bookable operator stay hidden', function () {
    $orphan = Service::factory()->for($this->tenant)->create(['name' => 'Servizio senza operatori']);

    $this->get(route('portal.show', $this->tenant))
        ->assertOk()
        ->assertDontSee($orphan->name);
});

test('hidden services stay off the public page', function () {
    $hidden = publishedService($this->tenant, $this->operator, ['name' => 'Servizio nascosto', 'is_active' => false]);

    $this->get(route('portal.show', $this->tenant))
        ->assertOk()
        ->assertDontSee($hidden->name);

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

    Livewire\Livewire::test('pages::portal.service', ['tenant' => $this->tenant, 'service' => $service])
        ->assertSee($service->name)
        ->assertSee($this->operator->name)
        ->call('selectDate', $tomorrow)
        ->assertSee('09:00')
        ->assertSee('10:00');
});

test('a service of another portal is not reachable through this portal', function () {
    $foreignService = Service::factory()->create();

    $this->get(route('portal.service', [$this->tenant->slug, $foreignService->slug]))->assertNotFound();
});

test('the embed view drops the site chrome', function () {
    publishedService($this->tenant, $this->operator);

    $this->get(route('portal.show', $this->tenant).'?embed=1')
        ->assertOk()
        ->assertDontSee('Powered by');
});
