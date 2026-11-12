<?php

use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the interface language follows the lang query parameter', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)
        ->get(route('app.services', ['lang' => 'it']))
        ->assertOk()
        ->assertSee('<html lang="it">', escape: false);
});

test('the chosen language is remembered for the next request', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)->get(route('app.services', ['lang' => 'it']));

    $this->actingAs($manager)
        ->get(route('app.services'))
        ->assertOk()
        ->assertSee('<html lang="it">', escape: false);
});

test('an unsupported language falls back to the language stored on the account', function () {
    $italian = User::factory()->manager()->create(['locale' => 'it']);
    $english = User::factory()->manager()->create(['locale' => 'en']);

    $this->actingAs($italian)
        ->get(route('app.services', ['lang' => 'de']))
        ->assertOk()
        ->assertSee('<html lang="it">', escape: false);

    $this->actingAs($english)
        ->get(route('app.services', ['lang' => 'de']))
        ->assertOk()
        ->assertSee('<html lang="en">', escape: false);
});

test('a public portal page defaults to the language of its portal', function () {
    $tenant = Tenant::factory()->create(['locale' => 'it']);

    $this->get(route('portal.show', $tenant))
        ->assertOk()
        ->assertSee('<html lang="it">', escape: false)
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'it'));
});
