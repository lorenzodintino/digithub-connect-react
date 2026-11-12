<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a user sets their own timezone and language', function () {
    $user = User::factory()->operator()->create(['timezone' => 'Europe/Rome', 'locale' => 'it']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => 'Europe/London',
            'locale' => 'en',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->timezone)->toBe('Europe/London')
        ->and($user->locale)->toBe('en');
});

test('a timezone outside the offered list is rejected', function () {
    $user = User::factory()->operator()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => 'Mars/Olympus_Mons',
            'locale' => $user->locale,
        ])
        ->assertSessionHasErrors('timezone');
});

test('an unsupported language is rejected', function () {
    $user = User::factory()->operator()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => $user->timezone,
            'locale' => 'de',
        ])
        ->assertSessionHasErrors('locale');
});

test('the language saved on the profile wins over an older session choice', function () {
    $user = User::factory()->operator()->create(['locale' => 'en']);

    // The switcher stored Italian in the session on a previous request.
    $this->actingAs($user)->withSession(['locale' => 'it']);

    $this->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'timezone' => $user->timezone,
        'locale' => 'en',
    ])->assertInertiaFlash('toast.message', 'Profile updated.');

    expect(session('locale'))->toBe('en');

    $this->actingAs($user)
        ->get(route('app.availability'))
        ->assertSee('<html lang="en">', escape: false)
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

test('the language switcher is remembered on the account', function () {
    $user = User::factory()->manager()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->get(route('app.services', ['lang' => 'it']))
        ->assertSee('<html lang="it">', escape: false);

    expect($user->refresh()->locale)->toBe('it');
});

test('an unsupported lang parameter never reaches the account', function () {
    $user = User::factory()->manager()->create(['locale' => 'en']);

    $this->actingAs($user)->get(route('app.services', ['lang' => 'de']))->assertOk();

    expect($user->refresh()->locale)->toBe('en');
});

test('a guest switching language does not break the request', function () {
    $this->get(route('login', ['lang' => 'it']))
        ->assertOk()
        ->assertSee('<html lang="it">', escape: false)
        ->assertInertia(fn (Assert $page) => $page->component('auth/login')->where('locale', 'it'));
});
