<?php

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('an administrator provisions a portal together with its manager', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.portals')
        ->call('createPortal')
        ->set('form.name', 'Studio Bianchi')
        ->set('form.slug', 'studio-bianchi')
        ->set('form.manager_name', 'Anna Bianchi')
        ->set('form.manager_email', 'anna@example.com')
        ->call('savePortal')
        ->assertHasNoErrors();

    $tenant = Tenant::query()->where('slug', 'studio-bianchi')->sole();

    expect($tenant->name)->toBe('Studio Bianchi')
        ->and($tenant->manager)->not->toBeNull()
        ->and($tenant->manager->email)->toBe('anna@example.com')
        ->and($tenant->manager->role)->toBe(UserRole::Manager)
        ->and($tenant->manager->is_bookable)->toBeFalse();
});

test('the generated manager password is returned once and lets the manager log in', function () {
    $component = Livewire::actingAs($this->admin)
        ->test('pages::admin.portals')
        ->call('createPortal')
        ->set('form.name', 'Studio Verdi')
        ->set('form.slug', 'studio-verdi')
        ->set('form.manager_name', 'Luca Verdi')
        ->set('form.manager_email', 'luca@example.com')
        ->call('savePortal');

    $password = $component->get('generatedPassword');

    expect($password)->toBeString()->not->toBeEmpty();

    $this->post(route('login.store'), [
        'email' => 'luca@example.com',
        'password' => $password,
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

test('a portal slug must be unique', function () {
    Tenant::factory()->create(['slug' => 'taken']);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.portals')
        ->call('createPortal')
        ->set('form.name', 'Another')
        ->set('form.slug', 'taken')
        ->set('form.manager_name', 'Someone')
        ->set('form.manager_email', 'someone@example.com')
        ->call('savePortal')
        ->assertHasErrors(['form.slug']);
});

test('an administrator suspends and deletes a portal', function () {
    $tenant = Tenant::factory()->create();
    $manager = User::factory()->manager($tenant)->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.portals')
        ->call('toggleActive', $tenant)
        ->call('confirmDelete', $tenant->id)
        ->call('deletePortal');

    $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    $this->assertDatabaseMissing('users', ['id' => $manager->id]);
});

test('a manager cannot reach the portal management component', function () {
    $manager = User::factory()->manager()->create();

    Livewire::actingAs($manager)
        ->test('pages::admin.portals')
        ->call('createPortal')
        ->assertForbidden();
});
