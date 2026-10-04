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
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $this->manager = User::factory()->manager($this->tenant)->create();
});

test('resources require login', function () {
    $this->get(route('app.resources'))->assertRedirect(route('login'));
});

test('only the portal manager can open resources', function (string $role) {
    $user = match ($role) {
        'operator' => User::factory()->operator($this->tenant)->create(),
        'admin' => User::factory()->admin()->create(),
    };

    $this->actingAs($user)->get(route('app.resources'))->assertForbidden();
    Livewire::actingAs($user)->test('pages::app.resources')->assertForbidden();

    expect(Gate::forUser($user)->allows('create', Resource::class))->toBeFalse();
})->with(['operator', 'admin']);

test('the resources page shows only resources belonging to the manager portal', function () {
    Resource::factory()->for($this->tenant)->create(['name' => 'Own cabin']);
    Resource::factory()->create(['name' => 'Foreign laser']);

    $this->actingAs($this->manager)->get(route('app.resources'))->assertOk()
        ->assertSee('Own cabin')->assertDontSee('Foreign laser');
});

test('an inactive resource has an Italian status in the Italian interface', function () {
    Resource::factory()->for($this->tenant)->create(['is_active' => false]);

    $this->actingAs($this->manager)->get(route('app.resources', ['lang' => 'it']))
        ->assertSee('Inattivo')
        ->assertDontSee('Inactive');
});

test('a manager creates a resource with preparation cleanup and internal notes', function () {
    Livewire::actingAs($this->manager)->test('pages::app.resources')
        ->call('createResource')
        ->set('form.name', '  Laser 1  ')
        ->set('form.type', ResourceType::Machine->value)
        ->set('form.description', 'Laser diodo')
        ->set('form.notes', 'Chiave nel cassetto')
        ->set('form.preparation_minutes', 5)
        ->set('form.cleanup_minutes', 15)
        ->call('saveResource')->assertHasNoErrors()
        ->assertSee('Laser 1')->assertSee('Chiave nel cassetto');

    $resource = Resource::query()->sole();
    expect($resource->tenant_id)->toBe($this->tenant->id)
        ->and($resource->type)->toBe(ResourceType::Machine)
        ->and($resource->name)->toBe('Laser 1')
        ->and($resource->preparation_minutes)->toBe(5)
        ->and($resource->cleanup_minutes)->toBe(15)
        ->and($resource->is_active)->toBeTrue();
    expect(ActivityLog::query()->where('action', 'resource.created')->sole()->tenant_id)->toBe($this->tenant->id);
});

test('resource inputs reject missing names invalid types and invalid preparation periods', function () {
    Livewire::actingAs($this->manager)->test('pages::app.resources')
        ->call('createResource')->set('form.name', ' ')
        ->set('form.type', 'invalid')
        ->set('form.preparation_minutes', -1)->set('form.cleanup_minutes', 241)
        ->call('saveResource')
        ->assertHasErrors(['form.name', 'form.type', 'form.preparation_minutes', 'form.cleanup_minutes']);
    $this->assertDatabaseCount('resources', 0);
});

test('resource names are unique within their own portal', function () {
    Resource::factory()->for($this->tenant)->create(['name' => 'Cabina 1']);

    Livewire::actingAs($this->manager)->test('pages::app.resources')
        ->call('createResource')->set('form.name', ' Cabina 1 ')->call('saveResource')
        ->assertHasErrors(['form.name' => 'unique']);
    $this->assertDatabaseCount('resources', 1);
});

test('different portals may use the same resource name', function () {
    Resource::factory()->create(['name' => 'Cabina 1']);

    Livewire::actingAs($this->manager)->test('pages::app.resources')
        ->call('createResource')->set('form.name', 'Cabina 1')->call('saveResource')
        ->assertHasNoErrors();
    $this->assertDatabaseCount('resources', 2);
});

test('editing and deactivating a resource preserves its service assignments', function () {
    $resource = Resource::factory()->for($this->tenant)->create();
    $service = Service::factory()->for($this->tenant)->create();
    $requirement = ServiceResourceRequirement::factory()->for($service)->create();
    $requirement->resources()->attach($resource);

    Livewire::actingAs($this->manager)->test('pages::app.resources')
        ->call('editResource', $resource->id)->set('form.name', 'Cabina riservata')
        ->set('form.is_active', false)->call('saveResource')->assertHasNoErrors();

    expect($resource->fresh()->name)->toBe('Cabina riservata')
        ->and($resource->fresh()->is_active)->toBeFalse()
        ->and($requirement->resources()->sole()->id)->toBe($resource->id);
});

test('a manager cannot modify block or delete resources from another portal', function (string $action) {
    $foreign = Resource::factory()->create();
    Livewire::actingAs($this->manager)->test('pages::app.resources')
        ->call($action, $foreign->id)->assertForbidden();

    expect($foreign->fresh())->not->toBeNull();
})->with(['editResource', 'blockResource', 'confirmDelete']);

test('saving the resource form rechecks its portal authorization', function () {
    $foreign = Resource::factory()->create();
    Livewire::actingAs($this->manager)->test('pages::app.resources')
        ->set('form.resource', $foreign)->set('form.name', 'Changed')
        ->call('saveResource')->assertForbidden();
    expect($foreign->fresh()->name)->toBe($foreign->name);
});

test('unavailability uses the portal timezone and is removed from its resource', function (string $start, string $end, string $expectedUtc) {
    $this->travelTo(CarbonImmutable::parse('2026-01-01 00:00:00', 'UTC'));
    $resource = Resource::factory()->for($this->tenant)->create();
    $this->manager->update(['timezone' => 'America/New_York']);

    $component = Livewire::actingAs($this->manager)->test('pages::app.resources')
        ->call('blockResource', $resource->id)
        ->set('blockForm.starts_at', $start)->set('blockForm.ends_at', $end)
        ->set('blockForm.reason', 'Manutenzione')
        ->call('saveBlock')->assertHasNoErrors()->assertSee('Manutenzione');
    $block = ResourceBlock::query()->sole();

    expect($block->starts_at->format('Y-m-d H:i'))->toBe($expectedUtc)
        ->and($block->tenant_id)->toBe($this->tenant->id)
        ->and($block->resource_id)->toBe($resource->id);
    $component->call('removeBlock', $block->id)->assertHasNoErrors()->assertDontSee('Manutenzione');
    $this->assertDatabaseCount('resource_blocks', 0);
})->with([
    'summer time' => ['2026-07-10T13:00', '2026-07-10T15:00', '2026-07-10 11:00'],
    'winter time' => ['2026-11-10T13:00', '2026-11-10T15:00', '2026-11-10 12:00'],
]);

test('unavailability requires a valid period', function () {
    $resource = Resource::factory()->for($this->tenant)->create();
    Livewire::actingAs($this->manager)->test('pages::app.resources')
        ->call('blockResource', $resource->id)->call('saveBlock')
        ->assertHasErrors(['blockForm.starts_at', 'blockForm.ends_at'])
        ->set('blockForm.starts_at', '2026-11-10T13:00')
        ->set('blockForm.ends_at', '2026-11-10T12:00')
        ->call('saveBlock')->assertHasErrors(['blockForm.ends_at' => 'after']);
    $this->assertDatabaseCount('resource_blocks', 0);
});

test('foreign resource blocks cannot be removed', function () {
    $block = ResourceBlock::factory()->create();
    Livewire::actingAs($this->manager)->test('pages::app.resources')
        ->call('removeBlock', $block->id)->assertForbidden();
    expect($block->fresh())->not->toBeNull();
});

test('linked resources must be removed from services before deletion', function () {
    $resource = Resource::factory()->for($this->tenant)->create();
    $service = Service::factory()->for($this->tenant)->create();
    $requirement = ServiceResourceRequirement::factory()->for($service)->create();
    $requirement->resources()->attach($resource);

    Livewire::actingAs($this->manager)->test('pages::app.resources')
        ->call('confirmDelete', $resource->id)->call('deleteResource')
        ->assertHasErrors('deletingResourceId')
        ->assertSee('Remove this resource from its services and cancel or move its upcoming bookings before deleting it.');
    expect($resource->fresh())->not->toBeNull();
});

test('an unlinked resource can be deleted together with its unavailability periods', function () {
    $resource = Resource::factory()->for($this->tenant)->create();
    $block = ResourceBlock::factory()->for($resource)->create();

    Livewire::actingAs($this->manager)->test('pages::app.resources')
        ->call('confirmDelete', $resource->id)->call('deleteResource')->assertHasNoErrors();
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

test('resource details and unavailability reasons are escaped when displayed', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00:00', 'UTC'));
    $payload = '<script>alert("resource")</script>';
    $resource = Resource::factory()->for($this->tenant)->create([
        'name' => $payload, 'description' => $payload, 'notes' => $payload,
    ]);
    ResourceBlock::factory()->for($resource)->create(['reason' => $payload]);

    Livewire::actingAs($this->manager)->test('pages::app.resources')
        ->assertSee($payload)->assertDontSee($payload, escape: false);
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
