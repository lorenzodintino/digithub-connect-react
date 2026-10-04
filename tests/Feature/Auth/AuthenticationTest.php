<?php

use App\Models\User;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

test('invalid credentials have an Italian message without exposing whether the account exists', function (bool $existingAccount) {
    $user = User::factory()->create();

    $this->withSession(['locale' => 'it'])->post(route('login.store'), [
        'email' => $existingAccount ? $user->email : 'unknown@example.test',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors(['email' => 'Le credenziali non sono corrette.']);

    $this->assertGuest();
})->with([true, false]);

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('the login limit returns a readable Italian error', function () {
    foreach (range(1, 6) as $attempt) {
        $response = $this->withSession(['locale' => 'it'])->post(route('login.store'), [
            'email' => 'limited@example.test',
            'password' => 'wrong-password',
        ]);
    }

    $response->assertTooManyRequests()->assertSee('Troppe richieste: attendi e riprova');
    $this->assertGuest();
});
