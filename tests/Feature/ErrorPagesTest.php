<?php

use App\Models\Tenant;
use App\Models\User;

test('an admin hitting a portal screen gets an explained 403', function () {
    $this->actingAs(User::factory()->admin()->create(['name' => 'Platform Admin']))
        ->get(route('app.bookings'))
        ->assertForbidden()
        ->assertSee('This page is not for your account')
        ->assertSee('Platform Admin')
        ->assertSee('Administrator')
        ->assertSee(route('dashboard'));
});

test('the 403 page explains the admin case in particular', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('app.bookings'))
        ->assertSee('you manage portals from the admin area instead', escape: false);
});

test('an operator hitting a manager screen gets the same explanation', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs(User::factory()->operator($tenant)->create())
        ->get(route('app.services'))
        ->assertForbidden()
        ->assertSee('This page is not for your account')
        ->assertDontSee('you manage portals from the admin area instead', escape: false);
});

test('a missing page is explained rather than left bare', function () {
    $this->get('/questa-pagina-non-esiste')
        ->assertNotFound()
        ->assertSee('This page does not exist')
        ->assertSee(route('home'));
});

test('a missing public booking page keeps the visitor moving', function () {
    $tenant = Tenant::factory()->create();

    $this->get(route('portal.service', [$tenant->slug, 'servizio-inventato']))
        ->assertNotFound()
        ->assertSee('This page does not exist');
});

test('error pages never leak the signed in banner to guests', function () {
    $this->get('/questa-pagina-non-esiste')
        ->assertNotFound()
        ->assertDontSee('You are signed in as');
});
