<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);
    // Passkeys are off by default (PASSKEYS_ENABLED): these tests are about the page when they are on.
    config(['fortify.features' => [...config('fortify.features'), Features::passkeys(['confirmPassword' => true])]]);
});

test('security settings page can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('settings/security')
        ->where('twoFactor', ['enabled' => false, 'requiresConfirmation' => true])
        ->where('passkeys', []));
});

test('security settings page requires password confirmation when enabled', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('security.edit'));

    $response->assertRedirect(route('password.confirm'));
});

test('the security page speaks the language chosen on the same page', function () {
    $user = User::factory()->create(['locale' => 'en']);
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    foreach (['it', 'en'] as $locale) {
        $this->get(route('security.edit').'?lang='.$locale)
            ->assertInertia(fn (Assert $page) => $page->where('locale', $locale));
    }
});

test('security settings page renders without two factor when feature is disabled', function () {
    config(['fortify.features' => []]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/security')
            ->where('twoFactor', null)
            ->where('passkeys', null));
});

test('two factor authentication disabled when confirmation abandoned between requests', function () {
    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => null,
    ])->save();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('twoFactor.enabled', false));

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
    ]);
});

test('a setup just started survives the way back to the page, and only that visit', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    $this->from(route('security.edit'))
        ->followingRedirects()
        ->post(route('two-factor.enable'))
        ->assertInertia(fn (Assert $page) => $page->where('twoFactor.enabled', false));

    expect($user->refresh()->two_factor_secret)->not->toBeNull();

    $this->get(route('security.edit'));

    expect($user->refresh()->two_factor_secret)->toBeNull();
});

test('a wrong code keeps the setup open, and the right one confirms it', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    $this->from(route('security.edit'))->followingRedirects()->post(route('two-factor.enable'));

    $this->from(route('security.edit'))
        ->followingRedirects()
        ->post(route('two-factor.confirm'), ['code' => '000000'])
        ->assertInertia(fn (Assert $page) => $page
            ->has('errors.confirmTwoFactorAuthentication.code')
            ->where('twoFactor.enabled', false));

    expect($user->refresh()->two_factor_secret)->not->toBeNull();

    $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));

    $this->from(route('security.edit'))
        ->followingRedirects()
        ->post(route('two-factor.confirm'), ['code' => $code])
        ->assertInertia(fn (Assert $page) => $page->where('twoFactor.enabled', true));
});

test('password can be updated', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $response = $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Password updated.');

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $response = $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'wrong-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertSessionHasErrors(['current_password']);

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});
