<?php

use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Everything the profile form sends, with the person's current values.
 *
 * @param  array<string, string>  $changes
 * @return array<string, string|null>
 */
function publicProfileForm(User $user, array $changes = []): array
{
    return [
        'name' => $user->name,
        'email' => $user->email,
        'timezone' => $user->timezone,
        'locale' => $user->locale,
        'phone' => $user->phone,
        'job_title' => $user->job_title,
        'bio' => $user->bio,
        ...$changes,
    ];
}

test('an operator updates the details shown on the public page', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)
        ->patch(route('profile.update'), publicProfileForm($operator, [
            'name' => 'Giulia Bianchi',
            'phone' => '+39 02 1234567',
            'job_title' => 'Commercialista',
            'bio' => 'Seguo piccole imprese e professionisti.',
        ]))
        ->assertSessionHasNoErrors();

    $operator->refresh();

    expect($operator->job_title)->toBe('Commercialista')
        ->and($operator->phone)->toBe('+39 02 1234567')
        ->and($operator->bio)->toBe('Seguo piccole imprese e professionisti.');
});

test('the updated details reach the public service page', function () {
    $tenant = Tenant::factory()->create();
    $operator = User::factory()->operator($tenant)->create();
    $service = Service::factory()->for($tenant)->create();
    $service->operators()->attach($operator);

    $this->actingAs($operator)->patch(route('profile.update'), publicProfileForm($operator, [
        'job_title' => 'Consulente del lavoro',
        'bio' => 'Dieci anni di esperienza.',
    ]));

    auth()->logout();

    $this->get(route('portal.service', [$tenant, $service]))
        ->assertOk()
        ->assertSee('Consulente del lavoro')
        ->assertSee('Dieci anni di esperienza.');
});

test('clearing an optional field stores null instead of an empty string', function () {
    $operator = User::factory()->operator()->create(['job_title' => 'Da rimuovere']);

    $this->actingAs($operator)
        ->patch(route('profile.update'), publicProfileForm($operator, ['job_title' => '']))
        ->assertSessionHasNoErrors();

    expect($operator->refresh()->job_title)->toBeNull();
});

test('the public profile fields are validated', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)
        ->patch(route('profile.update'), publicProfileForm($operator, [
            'job_title' => str_repeat('a', 121),
            'bio' => str_repeat('b', 1001),
        ]))
        ->assertSessionHasErrors(['job_title', 'bio']);
});

test('a platform administrator has no public profile section', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('hasPublicProfile', false));
});

test('a platform administrator cannot write public profile details', function () {
    $admin = User::factory()->admin()->create(['job_title' => null]);

    $this->actingAs($admin)
        ->patch(route('profile.update'), publicProfileForm($admin, ['job_title' => 'Amministratore']))
        ->assertSessionHasNoErrors();

    expect($admin->refresh()->job_title)->toBeNull();
});

test('a manager can also edit their public profile', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)
        ->patch(route('profile.update'), publicProfileForm($manager, ['job_title' => 'Titolare']))
        ->assertSessionHasNoErrors();

    expect($manager->refresh()->job_title)->toBe('Titolare');
});
