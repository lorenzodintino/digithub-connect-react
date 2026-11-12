<?php

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('an administrator provisions a portal together with its manager', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.portals.store'), portalForm())
        ->assertSessionHasNoErrors();

    $tenant = Tenant::query()->where('slug', 'studio-bianchi')->sole();

    expect($tenant->name)->toBe('Studio Bianchi')
        ->and($tenant->manager)->not->toBeNull()
        ->and($tenant->manager->email)->toBe('anna@example.com')
        ->and($tenant->manager->role)->toBe(UserRole::Manager)
        ->and($tenant->manager->is_bookable)->toBeFalse();
});

test('the generated manager password is returned once and lets the manager log in', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.portals.store'), portalForm(['name' => 'Studio Verdi', 'slug' => 'studio-verdi', 'manager_name' => 'Luca Verdi', 'manager_email' => 'luca@example.com']))
        ->assertInertiaFlash('credentials.email', 'luca@example.com');

    $password = session()->get(SessionKey::FLASH_DATA)['credentials']['password'] ?? null;

    expect($password)->toBeString()->not->toBeEmpty();

    auth()->logout();

    $this->post(route('login.store'), [
        'email' => 'luca@example.com',
        'password' => $password,
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

test('a portal slug must be unique', function () {
    Tenant::factory()->create(['slug' => 'taken']);

    $this->actingAs($this->admin)
        ->post(route('admin.portals.store'), portalForm(['name' => 'Another', 'slug' => 'taken', 'manager_email' => 'someone@example.com']))
        ->assertSessionHasErrors(['slug']);

    expect(Tenant::query()->where('slug', 'taken')->count())->toBe(1);
});

test('the manager account is asked for only when the portal is new', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.portals.store'), portalForm(['manager_name' => '', 'manager_email' => '']))
        ->assertSessionHasErrors(['manager_name', 'manager_email']);

    $tenant = Tenant::factory()->create();

    $this->put(route('admin.portals.update', $tenant), portalForm(['slug' => $tenant->slug, 'name' => 'Nuovo nome', 'manager_name' => '', 'manager_email' => '']))
        ->assertSessionHasNoErrors();

    expect($tenant->fresh()->name)->toBe('Nuovo nome');
});

test('the list carries what the edit form and the delete warning need', function () {
    $tenant = Tenant::factory()->create(['name' => 'Studio Rossi', 'contact_phone' => '+39 02 1234567']);
    $operator = User::factory()->operator($tenant)->create();
    $service = Service::factory()->for($tenant)->create();
    Booking::factory()->forSlot($service, $operator, CarbonImmutable::now('UTC')->addDay()->setTime(10, 0))->create();

    $this->actingAs($this->admin)
        ->get(route('admin.portals', ['q' => 'rossi']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/portals')
            ->has('portals.data', 1)
            ->where('portals.data.0.form.contact_phone', '+39 02 1234567')
            ->where('portals.data.0.upcoming_count', 1));
});

test('an administrator suspends and deletes a portal', function () {
    $tenant = Tenant::factory()->create();
    $manager = User::factory()->manager($tenant)->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.portals.toggle', $tenant))
        ->assertInertiaFlash('toast.message', 'Portal suspended.');

    expect($tenant->fresh()->is_active)->toBeFalse();

    $this->delete(route('admin.portals.destroy', $tenant))->assertRedirect(route('admin.portals'));

    $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    $this->assertDatabaseMissing('users', ['id' => $manager->id]);
});

test('a manager cannot reach the portal management', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)
        ->post(route('admin.portals.store'), portalForm())
        ->assertForbidden();

    $this->delete(route('admin.portals.destroy', $manager->tenant))->assertForbidden();

    expect(Tenant::query()->where('slug', 'studio-bianchi')->exists())->toBeFalse()
        ->and($manager->tenant->fresh())->not->toBeNull();
});
