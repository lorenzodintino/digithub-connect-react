<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

/**
 * Passkeys are hidden altogether until PASSKEYS_ENABLED says otherwise:
 * no route, no button on the sign-in and confirmation pages, no section in
 * the security settings. Two-factor authentication is what protects accounts.
 */
function passkeysFromEnvironment(?string $value): array
{
    $previous = [getenv('PASSKEYS_ENABLED'), $_ENV['PASSKEYS_ENABLED'] ?? null, $_SERVER['PASSKEYS_ENABLED'] ?? null];

    if ($value === null) {
        putenv('PASSKEYS_ENABLED');
        unset($_ENV['PASSKEYS_ENABLED'], $_SERVER['PASSKEYS_ENABLED']);
    } else {
        putenv("PASSKEYS_ENABLED={$value}");
        $_ENV['PASSKEYS_ENABLED'] = $_SERVER['PASSKEYS_ENABLED'] = $value;
    }

    try {
        return (require config_path('fortify.php'))['features'];
    } finally {
        $previous[0] === false ? putenv('PASSKEYS_ENABLED') : putenv("PASSKEYS_ENABLED={$previous[0]}");
        $previous[1] === null ? $_ENV['PASSKEYS_ENABLED'] = 'false' : $_ENV['PASSKEYS_ENABLED'] = $previous[1];
        $previous[2] === null ? $_SERVER['PASSKEYS_ENABLED'] = 'false' : $_SERVER['PASSKEYS_ENABLED'] = $previous[2];
    }
}

test('passkeys are off unless the environment turns them on', function (?string $value, bool $on) {
    config(['fortify.features' => passkeysFromEnvironment($value)]);

    expect(Features::enabled(Features::passkeys()))->toBe($on)
        // The rest of the security features do not depend on the switch.
        ->and(Features::enabled(Features::twoFactorAuthentication()))->toBeTrue();
})->with([
    'not set' => [null, false],
    'false' => ['false', false],
    'a typo' => ['yes please', false],
    'true' => ['true', true],
    '1' => ['1', true],
]);

test('while they are off Fortify registers no passkey route', function () {
    expect(Route::has('passkey.login'))->toBeFalse()
        ->and(Route::has('passkey.login-options'))->toBeFalse()
        ->and(Route::has('passkey.confirm'))->toBeFalse()
        ->and(Route::has('passkey.store'))->toBeFalse()
        ->and(Route::has('passkey.destroy'))->toBeFalse();
});

test('the sign-in and confirmation pages carry no passkey button', function () {
    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->component('auth/login')->where('passkeys', null));

    $this->actingAs(User::factory()->create())
        ->get(route('password.confirm'))
        ->assertInertia(fn (Assert $page) => $page->component('auth/confirm-password')->where('passkeys', null));
});

test('the security page has no passkey section while they are off', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page->component('settings/security')->where('passkeys', null));
});

test('the address that announces them answers not found', function () {
    $this->get(route('well-known.passkeys'))->assertNotFound();
});

test('with the routes in place the pages receive their addresses from the server', function () {
    // What Fortify registers when PASSKEYS_ENABLED is true, named as it names them.
    foreach (['passkeys/login/options' => 'passkey.login-options', 'passkeys/login' => 'passkey.login', 'passkeys/confirm/options' => 'passkey.confirm-options', 'passkeys/confirm' => 'passkey.confirm'] as $uri => $name) {
        Route::get($uri, fn () => '')->name($name);
    }
    app('router')->getRoutes()->refreshNameLookups();

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
        ->where('passkeys', ['options' => '/passkeys/login/options', 'submit' => '/passkeys/login']));

    $this->actingAs(User::factory()->create())
        ->get(route('password.confirm'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('passkeys', ['options' => '/passkeys/confirm/options', 'submit' => '/passkeys/confirm']));
});

test('the address that announces them answers when they are on', function () {
    config(['fortify.features' => [...config('fortify.features'), Features::passkeys(['confirmPassword' => true])]]);

    $this->get(route('well-known.passkeys'))->assertOk()->assertJsonPath('manage', route('security.edit'));
});
