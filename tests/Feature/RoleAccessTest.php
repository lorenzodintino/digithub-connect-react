<?php

use App\Models\Tenant;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('admin.portals'))->assertRedirect(route('login'));
    $this->get(route('app.services'))->assertRedirect(route('login'));
});

test('only administrators reach the platform screens', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.portals'))
        ->assertOk();

    $this->actingAs(User::factory()->manager($tenant)->create())
        ->get(route('admin.portals'))
        ->assertForbidden();

    $this->actingAs(User::factory()->operator($tenant)->create())
        ->get(route('admin.users'))
        ->assertForbidden();
});

test('only managers reach the portal configuration screens', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs(User::factory()->manager($tenant)->create())
        ->get(route('app.services'))
        ->assertOk();

    $this->actingAs(User::factory()->operator($tenant)->create())
        ->get(route('app.services'))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('app.staff'))
        ->assertForbidden();
});

test('managers and operators both reach the availability screen', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs(User::factory()->manager($tenant)->create())
        ->get(route('app.availability'))
        ->assertOk();

    $this->actingAs(User::factory()->operator($tenant)->create())
        ->get(route('app.availability'))
        ->assertOk();
});

test('disabled accounts are logged out', function () {
    $user = User::factory()->operator()->inactive()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('members of a suspended portal are logged out', function () {
    $tenant = Tenant::factory()->inactive()->create();

    $this->actingAs(User::factory()->manager($tenant)->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
