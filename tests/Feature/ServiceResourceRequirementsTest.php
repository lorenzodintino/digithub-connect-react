<?php

use App\Models\Resource;
use App\Models\ResourceBlock;
use App\Models\Service;
use App\Models\ServiceResourceRequirement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->manager = User::factory()->manager($this->tenant)->create();
});

test('a manager creates a service requiring a cabin alternative and a specific machine', function () {
    $cabins = Resource::factory()->count(2)->for($this->tenant)->create();
    $machine = Resource::factory()->for($this->tenant)->create(['name' => 'Laser']);

    $this->actingAs($this->manager)->post(route('app.services.store'), serviceForm($this->tenant, [
        'name' => 'Trattamento laser',
        'resource_requirements' => [
            ['name' => 'Cabina', 'resource_ids' => $cabins->pluck('id')->all()],
            ['name' => 'Macchinario', 'resource_ids' => [$machine->id]],
        ],
    ]))->assertSessionHasNoErrors();

    $service = Service::query()->sole();
    $requirements = $service->resourceRequirements()->with('resources')->get();
    expect($requirements)->toHaveCount(2)
        ->and($requirements[0]->name)->toBe('Cabina')
        ->and($requirements[0]->tenant_id)->toBe($this->tenant->id)
        ->and($requirements[0]->resources->pluck('id')->all())->toBe($cabins->pluck('id')->all())
        ->and($requirements[1]->resources->sole()->id)->toBe($machine->id);
});

test('service editing reloads alternatives and allows removal of a requirement', function () {
    $resource = Resource::factory()->for($this->tenant)->inactive()->create();
    $service = Service::factory()->for($this->tenant)->create();
    $requirement = ServiceResourceRequirement::factory()->for($service)->create(['name' => 'Cabina']);
    $requirement->resources()->attach($resource);

    $this->actingAs($this->manager)
        ->get(route('app.services', ['edit' => $service->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('editing.form.resource_requirements', [['name' => 'Cabina', 'resource_ids' => [$resource->id]]])
            ->where('resources.0.is_active', false));

    $this->put(route('app.services.update', $service->slug), serviceForm($service))->assertSessionHasNoErrors();
    expect($service->resourceRequirements()->count())->toBe(1);

    $this->put(route('app.services.update', $service->slug), serviceForm($service, ['resource_requirements' => []]))->assertSessionHasNoErrors();
    expect($service->resourceRequirements()->count())->toBe(0)
        ->and($resource->requirements()->count())->toBe(0);
});

test('service requirements reject foreign resources without changing the service', function () {
    $foreign = Resource::factory()->create();
    $service = Service::factory()->for($this->tenant)->create(['name' => 'Original']);

    $this->actingAs($this->manager)->put(route('app.services.update', $service->slug), serviceForm($service, [
        'name' => 'Changed',
        'resource_requirements' => [['name' => 'Laser', 'resource_ids' => [$foreign->id]]],
    ]))->assertSessionHasErrors(['resource_requirements.0.resource_ids.0']);

    expect($service->fresh()->name)->toBe('Original')
        ->and($service->resourceRequirements()->count())->toBe(0);
});

test('service requirements need a name and at least one alternative', function () {
    $this->actingAs($this->manager)->post(route('app.services.store'), serviceForm($this->tenant, [
        'name' => 'Trattamento',
        'resource_requirements' => [['name' => '', 'resource_ids' => []]],
    ]))->assertSessionHasErrors(['resource_requirements.0.name', 'resource_requirements.0.resource_ids']);

    $this->assertDatabaseCount('services', 0);
});

test('a physical resource cannot be repeated in multiple requirements', function () {
    $resource = Resource::factory()->for($this->tenant)->create();

    $this->actingAs($this->manager)->post(route('app.services.store'), serviceForm($this->tenant, [
        'name' => 'Trattamento',
        'resource_requirements' => [
            ['name' => 'Primo', 'resource_ids' => [$resource->id]],
            ['name' => 'Secondo', 'resource_ids' => [$resource->id]],
        ],
    ]))->assertSessionHasErrors(['resource_requirements.0.resource_ids.0']);

    $this->assertDatabaseCount('services', 0);
});

test('requirements are removed with their service and resources remain available', function () {
    $resource = Resource::factory()->for($this->tenant)->create();
    $service = Service::factory()->for($this->tenant)->create();
    $requirement = ServiceResourceRequirement::factory()->for($service)->create();
    $requirement->resources()->attach($resource);

    $this->actingAs($this->manager)->delete(route('app.services.destroy', $service->slug))->assertSessionHasNoErrors();

    expect($resource->fresh())->not->toBeNull()
        ->and($resource->requirements()->count())->toBe(0);
});

test('resource requirements restrict public availability without exposing internal notes', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00:00', 'UTC'));
    $operator = User::factory()->operator($this->tenant)->create();
    $service = Service::factory()->for($this->tenant)->create(['min_notice_minutes' => 0]);
    $service->operators()->attach($operator);
    $operator->availabilityRules()->create(['weekday' => 5, 'starts_at' => '09:00', 'ends_at' => '12:00']);
    $start = CarbonImmutable::parse('2026-10-02', $this->tenant->timezone);
    $finder = new SlotFinder($service, $operator);
    $originalSlots = $finder->slotsBetween($start, $start);

    $resource = Resource::factory()->for($this->tenant)->inactive()->create(['notes' => 'Private resource details']);
    ResourceBlock::factory()->for($resource)->create(['starts_at' => $start->utc(), 'ends_at' => $start->addDay()->utc()]);
    $requirement = ServiceResourceRequirement::factory()->for($service)->create();
    $requirement->resources()->attach($resource);

    expect($originalSlots)->not->toBeEmpty()
        ->and((new SlotFinder($service, $operator))->slotsBetween($start, $start))->toBeEmpty();

    $this->get(route('portal.service', [$this->tenant, $service]))->assertOk()
        ->assertDontSee('Private resource details');
});

test('requirements reject unexpected fields and nonexistent resources', function () {
    $this->actingAs($this->manager)->post(route('app.services.store'), serviceForm($this->tenant, [
        'name' => 'Trattamento',
        'resource_requirements' => [['name' => 'Laser', 'resource_ids' => [999999], 'tenant_id' => 999999]],
    ]))->assertSessionHasErrors(['resource_requirements.0', 'resource_requirements.0.resource_ids.0']);

    $this->assertDatabaseCount('services', 0);
});
