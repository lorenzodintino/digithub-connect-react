<?php

use App\Enums\UserRole;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->manager = User::factory()->manager($this->tenant)->create();
});

test('a manager creates an operator inside their own portal', function () {
    $service = Service::factory()->for($this->tenant)->create();

    $component = Livewire::actingAs($this->manager)
        ->test('pages::app.staff')
        ->call('createMember')
        ->set('form.name', 'Giulia Bianchi')
        ->set('form.email', 'giulia@example.com')
        ->set('form.service_ids', [$service->id])
        ->call('saveMember')
        ->assertHasNoErrors();

    $operator = User::query()->where('email', 'giulia@example.com')->sole();

    expect($operator->tenant_id)->toBe($this->tenant->id)
        ->and($operator->role)->toBe(UserRole::Default)
        ->and($operator->is_bookable)->toBeTrue()
        ->and($operator->services->pluck('id')->all())->toBe([$service->id])
        ->and($component->get('generatedPassword'))->toBeString()->not->toBeEmpty();
});

test('services from another portal are never attached to an operator', function () {
    $foreignService = Service::factory()->create();

    Livewire::actingAs($this->manager)
        ->test('pages::app.staff')
        ->call('createMember')
        ->set('form.name', 'Marco Neri')
        ->set('form.email', 'marco@example.com')
        ->set('form.service_ids', [$foreignService->id])
        ->call('saveMember')
        ->assertHasNoErrors();

    expect(User::query()->where('email', 'marco@example.com')->sole()->services)->toBeEmpty();
});

test('an operator email must be unique', function () {
    User::factory()->operator($this->tenant)->create(['email' => 'taken@example.com']);

    Livewire::actingAs($this->manager)
        ->test('pages::app.staff')
        ->call('createMember')
        ->set('form.name', 'Someone')
        ->set('form.email', 'taken@example.com')
        ->call('saveMember')
        ->assertHasErrors(['form.email']);
});

test('a manager cannot edit an operator of another portal', function () {
    $foreignOperator = User::factory()->operator()->create();

    Livewire::actingAs($this->manager)
        ->test('pages::app.staff')
        ->call('editMember', $foreignOperator)
        ->assertForbidden();
});

test('a manager can make themselves bookable', function () {
    expect($this->manager->is_bookable)->toBeFalse();

    Livewire::actingAs($this->manager)
        ->test('pages::app.staff')
        ->call('toggleManagerBookable');

    expect($this->manager->fresh()->is_bookable)->toBeTrue();
});

test('a manager deletes an operator of their portal', function () {
    $operator = User::factory()->operator($this->tenant)->create();

    Livewire::actingAs($this->manager)
        ->test('pages::app.staff')
        ->call('confirmDelete', $operator->id)
        ->call('deleteMember');

    $this->assertDatabaseMissing('users', ['id' => $operator->id]);
});
