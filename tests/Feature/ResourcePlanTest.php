<?php

use App\Models\Plan;
use App\Models\Resource;
use App\Models\ResourceBlock;
use App\Models\Service;
use App\Models\ServiceResourceRequirement;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->manager = User::factory()->manager($this->tenant)->create();
});

/**
 * @return array<string, mixed>
 */
function newResource(array $changes = []): array
{
    return ['name' => 'Cabina 1', 'type' => 'room', 'description' => '', 'notes' => '', 'preparation_minutes' => 0, 'cleanup_minutes' => 0, 'is_active' => true, ...$changes];
}

function withoutResources(Tenant $tenant): Plan
{
    $plan = Plan::factory()->lacking(['resources'])->create();
    $tenant->update(['plan_id' => $plan->id]);

    return $plan;
}

test('the three starting plans: Base has no resources, Pro and Studio do', function () {
    (new PlanSeeder)->run();

    expect(Plan::query()->where('name', 'Base')->sole()->allows('resources'))->toBeFalse()
        ->and(Plan::query()->where('name', 'Pro')->sole()->allows('resources'))->toBeTrue()
        ->and(Plan::query()->where('name', 'Studio')->sole()->allows('resources'))->toBeTrue();
});

test('a portal without a plan, or on a plan with resources, manages them as before', function () {
    $this->actingAs($this->manager)->post(route('app.resources.store'), newResource())->assertSessionHasNoErrors();

    $plan = Plan::factory()->create();
    $this->tenant->update(['plan_id' => $plan->id]);

    $this->post(route('app.resources.store'), newResource(['name' => 'Cabina 2']))->assertSessionHasNoErrors();

    expect(Resource::query()->count())->toBe(2);
});

test('a plan without resources refuses to create or edit one', function () {
    $resource = Resource::factory()->for($this->tenant)->create(['name' => 'Cabina esistente']);
    withoutResources($this->tenant);

    $this->actingAs($this->manager)->post(route('app.resources.store'), newResource())->assertForbidden();
    $this->put(route('app.resources.update', $resource), newResource(['name' => 'Cambiata']))->assertForbidden();

    expect(Resource::query()->count())->toBe(1)
        ->and($resource->fresh()->name)->toBe('Cabina esistente');
});

test('what restricts the agenda stays allowed without resources: unavailability and deletion', function () {
    $resource = Resource::factory()->for($this->tenant)->create();
    withoutResources($this->tenant);

    $this->actingAs($this->manager)
        ->post(route('app.resources.blocks.store', $resource), ['starts_at' => '2027-03-10T13:00', 'ends_at' => '2027-03-10T15:00', 'reason' => 'Guasto'])
        ->assertSessionHasNoErrors();

    $block = ResourceBlock::query()->sole();

    $this->delete(route('app.resources.blocks.destroy', $block))->assertSessionHasNoErrors();
    $this->delete(route('app.resources.destroy', $resource))->assertSessionHasNoErrors();

    expect(Resource::query()->count())->toBe(0);
});

test('the resources page says it is locked and still lists what exists', function () {
    Resource::factory()->for($this->tenant)->create(['name' => 'Cabina esistente']);
    withoutResources($this->tenant);

    $this->actingAs($this->manager)
        ->get(route('app.resources'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locked', true)->where('resources.0.name', 'Cabina esistente'));
});

test('the requirements of a service are kept as they are, whatever the form sends', function () {
    $resource = Resource::factory()->for($this->tenant)->create();
    $other = Resource::factory()->for($this->tenant)->create();
    $service = Service::factory()->for($this->tenant)->create();
    $requirement = ServiceResourceRequirement::factory()->for($service)->create(['name' => 'Cabina']);
    $requirement->resources()->attach($resource);
    withoutResources($this->tenant);

    $this->actingAs($this->manager)
        ->put(route('app.services.update', $service->slug), serviceForm($service, ['name' => 'Rinominato', 'resource_requirements' => [['name' => 'Altro', 'resource_ids' => [$other->id]]]]))
        ->assertSessionHasNoErrors();

    $requirements = $service->fresh()->resourceRequirements()->with('resources')->get();

    expect($service->fresh()->name)->toBe('Rinominato')
        ->and($requirements)->toHaveCount(1)
        ->and($requirements->sole()->name)->toBe('Cabina')
        ->and($requirements->sole()->resources->sole()->id)->toBe($resource->id);

    $this->get(route('app.services'))->assertInertia(fn (Assert $page) => $page->where('resourcesLocked', true));
});

test('a new service on a plan without resources is created with none', function () {
    $resource = Resource::factory()->for($this->tenant)->create();
    withoutResources($this->tenant);

    $this->actingAs($this->manager)
        ->post(route('app.services.store'), serviceForm($this->tenant, ['name' => 'Senza risorse', 'resource_requirements' => [['name' => 'Cabina', 'resource_ids' => [$resource->id]]]]))
        ->assertSessionHasNoErrors();

    expect(Service::query()->where('name', 'Senza risorse')->sole()->resourceRequirements()->count())->toBe(0);
});

test('the layout tells the sidebar the plan includes resources', function () {
    $this->actingAs($this->manager)->get(route('app.resources'))->assertInertia(fn (Assert $page) => $page->where('auth.user.tenant.features.resources', true));
});

test('the layout tells the sidebar a plan without resources locks them', function () {
    withoutResources($this->tenant);

    $this->actingAs($this->manager->fresh())->get(route('app.resources'))->assertInertia(fn (Assert $page) => $page->where('auth.user.tenant.features.resources', false));
});

test('the administrator sets resources on a plan', function () {
    $admin = User::factory()->admin()->create();
    $plan = Plan::factory()->lacking(['resources'])->create();

    $this->actingAs($admin)
        ->put(route('admin.plans.update', $plan), [
            'name' => $plan->name,
            'price' => '39',
            'features' => ['resources' => true, 'custom_smtp' => true],
            'is_active' => true,
            'position' => 2,
        ])
        ->assertSessionHasNoErrors();

    expect($plan->fresh()->allows('resources'))->toBeTrue();
});
