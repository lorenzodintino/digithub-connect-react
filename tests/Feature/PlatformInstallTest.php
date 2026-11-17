<?php

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;

/**
 * `platform:install` is how a live server gets its first administrator, instead of `db:seed` (which would create a demo portal and an
 * administrator with a known password).
 */
function installPlatform(array $options = [], string $password = 'Una-Password-Lunga-123!', ?string $confirmation = null)
{
    return test()->artisan('platform:install', [
        '--name' => 'Lorenzo',
        '--email' => 'lorenzo@example.com',
        ...$options,
    ])
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $confirmation ?? $password);
}

test('it creates an administrator who can sign in, and no plan: they come from the License Manager', function () {
    installPlatform()->assertSuccessful();

    $admin = User::query()->where('email', 'lorenzo@example.com')->firstOrFail();

    expect($admin->role)->toBe(UserRole::Admin)
        ->and($admin->tenant_id)->toBeNull()
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->is_bookable)->toBeFalse()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Plan::query()->count())->toBe(0);

    $this->post(route('login.store'), ['email' => 'lorenzo@example.com', 'password' => 'Una-Password-Lunga-123!'])
        ->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($admin);
});

test('it asks for name and email when they are not given as options', function () {
    $this->artisan('platform:install')
        ->expectsQuestion('Administrator name', 'Lorenzo')
        ->expectsQuestion('Administrator email', 'lorenzo@example.com')
        ->expectsQuestion('Password', 'Una-Password-Lunga-123!')
        ->expectsQuestion('Confirm password', 'Una-Password-Lunga-123!')
        ->assertSuccessful();

    expect(User::query()->where('email', 'lorenzo@example.com')->exists())->toBeTrue();
});

test('it refuses to run again once an administrator exists', function () {
    User::factory()->admin()->create();

    $this->artisan('platform:install')
        ->expectsOutput('A platform administrator already exists. Nothing was changed.')
        ->assertFailed();

    expect(User::query()->count())->toBe(1)
        ->and(Plan::query()->count())->toBe(0);
});

test('it creates nothing when the input is not valid', function (array $options, string $password, ?string $confirmation) {
    User::factory()->create(['email' => 'taken@example.com']);

    installPlatform($options, $password, $confirmation)->assertFailed();

    expect(User::query()->where('role', UserRole::Admin)->exists())->toBeFalse()
        ->and(Plan::query()->count())->toBe(0);
})->with([
    'passwords that differ' => [[], 'Una-Password-Lunga-123!', 'Un-Altra-Password-456!'],
    'password too short' => [[], 'corta', null],
    'email already used' => [['--email' => 'taken@example.com'], 'Una-Password-Lunga-123!', null],
    'not an email' => [['--email' => 'non-una-email'], 'Una-Password-Lunga-123!', null],
]);
