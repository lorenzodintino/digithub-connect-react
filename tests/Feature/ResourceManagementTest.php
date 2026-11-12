<?php

use App\Enums\ResourceType;
use App\Models\ActivityLog;
use App\Models\Resource;
use App\Models\ResourceBlock;
use App\Models\Service;
use App\Models\ServiceResourceRequirement;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ResourceSeeder;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $this->manager = User::factory()->manager($this->tenant)->create();
});

/**
 * Everything the resource form sends.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function resourceForm(array $changes = []): array
{
    return [
        'name' => 'Cabina 1',
        'type' => ResourceType::Room->value,
        'description' => '',
        'notes' => '',
        'preparation_minutes' => 0,
        'cleanup_minutes' => 0,
        'is_active' => true,
        ...$changes,
    ];
}

test('resources require login', function () {
    $this->get(route('app.resources'))->assertRedirect(route('login'));
});

test('only the portal manager can open resources', function (string $role) {
    $user = match ($role) {
        'operator' => User::factory()->operator($this->tenant)->create(),
        'admin' => User::factory()->admin()->create(),
    };

    $this->actingAs($user)->get(route('app.resources'))->assertForbidden();
    $this->actingAs($user)->post(route('app.resources.store'), resourceForm())->assertForbidden();

    expect(Gate::forUser($user)->allows('create', Resource::class))->toBeFalse();
    $this->assertDatabaseCount('resources', 0);
})->with(['operator', 'admin']);

test('the resources page shows only resources belonging to the manager portal', function () {
    Resource::factory()->for($this->tenant)->create(['name' => 'Own cabin']);
    Resource::factory()->create(['name' => 'Foreign laser']);

    $this->actingAs($this->manager)->get(route('app.resources'))->assertInertia(fn (Assert $page) => $page
        ->component('app/resources')
        ->has('resources', 1)
        ->where('resources.0.name', 'Own cabin'));
});

test('a resource type has an Italian label in the Italian interface', function () {
    Resource::factory()->for($this->tenant)->create(['type' => ResourceType::Machine]);

    $this->actingAs($this->manager)->get(route('app.resources', ['lang' => 'it']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('resources.0.type_label', 'Macchinario')
            ->where('types.3.label', 'Macchinario')
            ->where('locale', 'it'));
});

test('a manager creates a resource with preparation cleanup and internal notes', function () {
    $this->actingAs($this->manager)
        ->post(route('app.resources.store'), resourceForm([
            'name' => '  Laser 1  ',
            'type' => ResourceType::Machine->value,
            'description' => 'Laser diodo',
            'notes' => 'Chiave nel cassetto',
            'preparation_minutes' => 5,
            'cleanup_minutes' => 15,
        ]))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Resource created.');

    $resource = Resource::query()->sole();
    expect($resource->tenant_id)->toBe($this->tenant->id)
        ->and($resource->type)->toBe(ResourceType::Machine)
        ->and($resource->name)->toBe('Laser 1')
        ->and($resource->notes)->toBe('Chiave nel cassetto')
        ->and($resource->preparation_minutes)->toBe(5)
        ->and($resource->cleanup_minutes)->toBe(15)
        ->and($resource->is_active)->toBeTrue();
    expect(ActivityLog::query()->where('action', 'resource.created')->sole()->tenant_id)->toBe($this->tenant->id);
});

test('resource inputs reject missing names invalid types and invalid preparation periods', function () {
    $this->actingAs($this->manager)
        ->post(route('app.resources.store'), resourceForm([
            'name' => ' ',
            'type' => 'invalid',
            'preparation_minutes' => -1,
            'cleanup_minutes' => 241,
        ]))
        ->assertSessionHasErrors(['name', 'type', 'preparation_minutes', 'cleanup_minutes']);

    $this->assertDatabaseCount('resources', 0);
});

test('resource names are unique within their own portal', function () {
    Resource::factory()->for($this->tenant)->create(['name' => 'Cabina 1']);

    $this->actingAs($this->manager)
        ->post(route('app.resources.store'), resourceForm(['name' => ' Cabina 1 ']))
        ->assertSessionHasErrors(['name']);

    $this->assertDatabaseCount('resources', 1);
});

test('different portals may use the same resource name', function () {
    Resource::factory()->create(['name' => 'Cabina 1']);

    $this->actingAs($this->manager)
        ->post(route('app.resources.store'), resourceForm(['name' => 'Cabina 1']))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('resources', 2);
});

test('editing and deactivating a resource preserves its service assignments', function () {
    $resource = Resource::factory()->for($this->tenant)->create();
    $service = Service::factory()->for($this->tenant)->create();
    $requirement = ServiceResourceRequirement::factory()->for($service)->create();
    $requirement->resources()->attach($resource);

    $this->actingAs($this->manager)
        ->put(route('app.resources.update', $resource), resourceForm(['name' => 'Cabina riservata', 'is_active' => false]))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Resource updated.');

    expect($resource->fresh()->name)->toBe('Cabina riservata')
        ->and($resource->fresh()->is_active)->toBeFalse()
        ->and($requirement->resources()->sole()->id)->toBe($resource->id);
});

test('a manager cannot modify block or delete resources from another portal', function (string $action) {
    $foreign = Resource::factory()->create(['name' => 'Foreign laser']);

    $this->actingAs($this->manager);

    $response = match ($action) {
        'update' => $this->put(route('app.resources.update', $foreign), resourceForm(['name' => 'Changed'])),
        'block' => $this->post(route('app.resources.blocks.store', $foreign), ['starts_at' => '2026-11-10T13:00', 'ends_at' => '2026-11-10T15:00']),
        'delete' => $this->delete(route('app.resources.destroy', $foreign)),
    };

    $response->assertForbidden();

    expect($foreign->fresh()->name)->toBe('Foreign laser');
    $this->assertDatabaseCount('resource_blocks', 0);
})->with(['update', 'block', 'delete']);

test('unavailability uses the portal timezone and is removed from its resource', function (string $start, string $end, string $expectedUtc) {
    $this->travelTo(CarbonImmutable::parse('2026-01-01 00:00:00', 'UTC'));
    $resource = Resource::factory()->for($this->tenant)->create();
    $this->manager->update(['timezone' => 'America/New_York']);

    $this->actingAs($this->manager)
        ->post(route('app.resources.blocks.store', $resource), ['starts_at' => $start, 'ends_at' => $end, 'reason' => 'Manutenzione'])
        ->assertSessionHasNoErrors();
    $block = ResourceBlock::query()->sole();

    expect($block->starts_at->format('Y-m-d H:i'))->toBe($expectedUtc)
        ->and($block->tenant_id)->toBe($this->tenant->id)
        ->and($block->resource_id)->toBe($resource->id);

    $this->get(route('app.resources'))->assertInertia(fn (Assert $page) => $page
        ->where('resources.0.blocks.0.reason', 'Manutenzione')
        ->where('timezone', 'Europe/Rome'));

    $this->delete(route('app.resources.blocks.destroy', $block))->assertSessionHasNoErrors();
    $this->assertDatabaseCount('resource_blocks', 0);
})->with([
    'summer time' => ['2026-07-10T13:00', '2026-07-10T15:00', '2026-07-10 11:00'],
    'winter time' => ['2026-11-10T13:00', '2026-11-10T15:00', '2026-11-10 12:00'],
]);

test('unavailability requires a valid period', function () {
    $resource = Resource::factory()->for($this->tenant)->create();
    $this->actingAs($this->manager);

    $this->post(route('app.resources.blocks.store', $resource), [])
        ->assertSessionHasErrors(['starts_at', 'ends_at']);

    $this->post(route('app.resources.blocks.store', $resource), ['starts_at' => '2026-11-10T13:00', 'ends_at' => '2026-11-10T12:00'])
        ->assertSessionHasErrors(['ends_at']);

    $this->assertDatabaseCount('resource_blocks', 0);
});

test('foreign resource blocks cannot be removed', function () {
    $block = ResourceBlock::factory()->create();

    $this->actingAs($this->manager)->delete(route('app.resources.blocks.destroy', $block))->assertForbidden();

    expect($block->fresh())->not->toBeNull();
});

test('linked resources must be removed from services before deletion', function () {
    $resource = Resource::factory()->for($this->tenant)->create();
    $service = Service::factory()->for($this->tenant)->create();
    $requirement = ServiceResourceRequirement::factory()->for($service)->create();
    $requirement->resources()->attach($resource);

    $this->actingAs($this->manager)
        ->delete(route('app.resources.destroy', $resource))
        ->assertSessionHasErrors(['resource' => 'Remove this resource from its services and cancel or move its upcoming bookings before deleting it.']);

    expect($resource->fresh())->not->toBeNull();
});

test('an unlinked resource can be deleted together with its unavailability periods', function () {
    $resource = Resource::factory()->for($this->tenant)->create();
    $block = ResourceBlock::factory()->for($resource)->create();

    $this->actingAs($this->manager)
        ->delete(route('app.resources.destroy', $resource))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Resource deleted.');

    $this->assertDatabaseMissing('resources', ['id' => $resource->id]);
    $this->assertDatabaseMissing('resource_blocks', ['id' => $block->id]);
});

test('resource demo data is scoped and may be seeded twice without duplicates', function () {
    $this->tenant->update(['slug' => 'studio-rossi']);
    $this->seed(ResourceSeeder::class);
    $this->seed(ResourceSeeder::class);
    expect($this->tenant->resources()->count())->toBe(2)
        ->and($this->tenant->resources()->where('cleanup_minutes', 10)->count())->toBe(2);
});

test('resource policies allow only their own manager to edit and delete', function () {
    $own = Resource::factory()->for($this->tenant)->create();
    $foreign = Resource::factory()->create();
    $operator = User::factory()->operator($this->tenant)->create();
    $admin = User::factory()->admin()->create();

    foreach (['update', 'delete'] as $ability) {
        expect(Gate::forUser($this->manager)->allows($ability, $own))->toBeTrue();
        expect(Gate::forUser($this->manager)->allows($ability, $foreign))->toBeFalse();
        expect(Gate::forUser($operator)->allows($ability, $own))->toBeFalse();
        expect(Gate::forUser($admin)->allows($ability, $own))->toBeFalse();
    }
});

test('resource details and unavailability reasons reach the page as plain text', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00:00', 'UTC'));
    $payload = '<script>alert("resource")</script>';
    $resource = Resource::factory()->for($this->tenant)->create([
        'name' => $payload, 'description' => $payload, 'notes' => $payload,
    ]);
    ResourceBlock::factory()->for($resource)->create(['reason' => $payload]);

    $response = $this->actingAs($this->manager)->get(route('app.resources'));

    // React prints props as text; the page object itself must not open a tag.
    $response->assertDontSee($payload, escape: false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('resources.0.name', $payload)
            ->where('resources.0.blocks.0.reason', $payload));
});

test('deleting a portal also removes its linked resource configuration', function () {
    $resource = Resource::factory()->for($this->tenant)->create();
    $service = Service::factory()->for($this->tenant)->create();
    $requirement = ServiceResourceRequirement::factory()->for($service)->create();
    $requirement->resources()->attach($resource);
    ResourceBlock::factory()->for($resource)->create();

    $this->tenant->delete();

    $this->assertDatabaseCount('resources', 0);
    $this->assertDatabaseCount('resource_blocks', 0);
    $this->assertDatabaseCount('service_resource_requirements', 0);
    $this->assertDatabaseCount('resource_service_requirement', 0);
});
