<?php

use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

test('an operator updates the details shown on the public page', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator);

    Livewire::test('pages::settings.profile')
        ->set('name', 'Giulia Bianchi')
        ->set('email', $operator->email)
        ->set('phone', '+39 02 1234567')
        ->set('job_title', 'Commercialista')
        ->set('bio', 'Seguo piccole imprese e professionisti.')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

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

    $this->actingAs($operator);

    Livewire::test('pages::settings.profile')
        ->set('name', $operator->name)
        ->set('email', $operator->email)
        ->set('job_title', 'Consulente del lavoro')
        ->set('bio', 'Dieci anni di esperienza.')
        ->call('updateProfileInformation');

    auth()->logout();

    $this->get(route('portal.service', [$tenant, $service]))
        ->assertOk()
        ->assertSee('Consulente del lavoro')
        ->assertSee('Dieci anni di esperienza.');
});

test('clearing an optional field stores null instead of an empty string', function () {
    $operator = User::factory()->operator()->create(['job_title' => 'Da rimuovere']);

    $this->actingAs($operator);

    Livewire::test('pages::settings.profile')
        ->set('name', $operator->name)
        ->set('email', $operator->email)
        ->set('job_title', '')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($operator->refresh()->job_title)->toBeNull();
});

test('the public profile fields are validated', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator);

    Livewire::test('pages::settings.profile')
        ->set('name', $operator->name)
        ->set('email', $operator->email)
        ->set('job_title', str_repeat('a', 121))
        ->set('bio', str_repeat('b', 1001))
        ->call('updateProfileInformation')
        ->assertHasErrors(['job_title', 'bio']);
});

test('a platform administrator has no public profile section', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test('pages::settings.profile')
        ->assertSet('hasPublicProfile', false)
        ->assertDontSee('Public profile');
});

test('a manager can also edit their public profile', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager);

    Livewire::test('pages::settings.profile')
        ->set('name', $manager->name)
        ->set('email', $manager->email)
        ->set('job_title', 'Titolare')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($manager->refresh()->job_title)->toBe('Titolare');
});
