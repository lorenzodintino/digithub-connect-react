<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page
        ->component('settings/profile')
        ->where('profile.email', $user->email));
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->patch(route('profile.update'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'timezone' => $user->timezone,
        'locale' => $user->locale,
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'))
        ->assertInertiaFlash('toast.message', 'Profile updated.');

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->patch(route('profile.update'), [
        'name' => 'Test User',
        'email' => $user->email,
        'timezone' => $user->timezone,
        'locale' => $user->locale,
    ]);

    $response->assertSessionHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->delete(route('profile.destroy'), ['password' => 'password']);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->delete(route('profile.destroy'), ['password' => 'wrong-password']);

    $response->assertSessionHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});
