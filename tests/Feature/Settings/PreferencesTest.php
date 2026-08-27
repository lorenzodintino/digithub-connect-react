<?php

use App\Models\User;
use Livewire\Livewire;

test('a user sets their own timezone and language', function () {
    $user = User::factory()->operator()->create(['timezone' => 'Europe/Rome', 'locale' => 'it']);

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('timezone', 'Europe/London')
        ->set('locale', 'en')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->timezone)->toBe('Europe/London')
        ->and($user->locale)->toBe('en');
});

test('a timezone outside the offered list is rejected', function () {
    $user = User::factory()->operator()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('timezone', 'Mars/Olympus_Mons')
        ->call('updateProfileInformation')
        ->assertHasErrors('timezone');
});

test('an unsupported language is rejected', function () {
    $user = User::factory()->operator()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('locale', 'de')
        ->call('updateProfileInformation')
        ->assertHasErrors('locale');
});

test('the language saved on the profile wins over an older session choice', function () {
    $user = User::factory()->operator()->create(['locale' => 'en']);

    // The switcher stored Italian in the session on a previous request.
    $this->actingAs($user)->withSession(['locale' => 'it']);

    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('locale', 'en')
        ->call('updateProfileInformation');

    expect(session('locale'))->toBe('en');

    $this->actingAs($user)
        ->get(route('app.availability'))
        ->assertOk()
        ->assertSee('Date overrides');
});

test('the language switcher is remembered on the account', function () {
    $user = User::factory()->manager()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->get(route('app.services', ['lang' => 'it']))
        ->assertOk()
        ->assertSee('Nuovo servizio');

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
        ->assertSee('Accedi');
});
