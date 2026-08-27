<?php

use App\Models\Tenant;
use App\Models\User;

test('the interface language follows the lang query parameter', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)
        ->get(route('app.services', ['lang' => 'it']))
        ->assertOk()
        ->assertSee('Nuovo servizio');
});

test('the chosen language is remembered for the next request', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)->get(route('app.services', ['lang' => 'it']));

    $this->actingAs($manager)
        ->get(route('app.services'))
        ->assertOk()
        ->assertSee('Nuovo servizio');
});

test('an unsupported language falls back to the language stored on the account', function () {
    $italian = User::factory()->manager()->create(['locale' => 'it']);
    $english = User::factory()->manager()->create(['locale' => 'en']);

    $this->actingAs($italian)
        ->get(route('app.services', ['lang' => 'de']))
        ->assertOk()
        ->assertSee('Nuovo servizio');

    $this->actingAs($english)
        ->get(route('app.services', ['lang' => 'de']))
        ->assertOk()
        ->assertSee('New service');
});

test('a public portal page defaults to the language of its portal', function () {
    $tenant = Tenant::factory()->create(['locale' => 'it']);

    $this->get(route('portal.show', $tenant))
        ->assertOk()
        ->assertSee('Scegli un servizio');
});
