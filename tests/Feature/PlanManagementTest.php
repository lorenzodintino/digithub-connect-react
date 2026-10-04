<?php

use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('an administrator creates a plan with its limits', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.plans')
        ->call('createPlan')
        ->set('form.name', 'Pro')
        ->set('form.description', 'Per uno studio con più persone.')
        ->set('form.price', '39')
        ->set('form.max_services', '15')
        ->set('form.max_operators', '5')
        ->set('form.max_bookings_per_month', '1000')
        ->set('form.allows_custom_smtp', true)
        ->call('savePlan')
        ->assertHasNoErrors();

    $plan = Plan::query()->sole();

    expect($plan->name)->toBe('Pro')
        ->and($plan->price_cents)->toBe(3900)
        ->and($plan->max_services)->toBe(15)
        ->and($plan->allows_custom_smtp)->toBeTrue();
});

test('an empty limit means no cap on that number', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.plans')
        ->call('createPlan')
        ->set('form.name', 'Studio')
        ->set('form.max_services', '')
        ->set('form.max_operators', '')
        ->set('form.max_bookings_per_month', '')
        ->call('savePlan')
        ->assertHasNoErrors();

    $plan = Plan::query()->sole();

    expect($plan->max_services)->toBeNull()
        ->and($plan->limitLabel($plan->max_services))->toBe('Unlimited');
});

test('a plan name is required and the limits must be sane', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.plans')
        ->call('createPlan')
        ->set('form.name', '')
        ->set('form.max_services', '0')
        ->call('savePlan')
        ->assertHasErrors(['form.name', 'form.max_services']);
});

test('a plan is retired instead of deleted while portals sit on it', function () {
    $plan = Plan::factory()->create();
    Tenant::factory()->create(['plan_id' => $plan->id]);

    $component = Livewire::actingAs($this->admin)->test('pages::admin.plans');

    $component->call('toggleActive', $plan->id);

    expect($plan->fresh()->is_active)->toBeFalse();

    $component->call('confirmDelete', $plan->id)
        ->call('deletePlan')
        ->assertForbidden();

    expect(Plan::query()->whereKey($plan->id)->exists())->toBeTrue();
});

test('a plan nobody uses can be deleted', function () {
    $plan = Plan::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.plans')
        ->call('confirmDelete', $plan->id)
        ->call('deletePlan');

    expect(Plan::query()->whereKey($plan->id)->exists())->toBeFalse();
});

test('an administrator puts a portal on a plan', function () {
    $plan = Plan::factory()->create(['name' => 'Pro']);
    $portal = Tenant::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.portals')
        ->call('editPortal', $portal->slug)
        ->set('form.plan_id', (string) $plan->id)
        ->call('savePortal')
        ->assertHasNoErrors();

    expect($portal->fresh()->plan->name)->toBe('Pro');
});

test('the plan limits refuse a service past the limit', function () {
    $plan = Plan::factory()->create(['max_services' => 1]);
    $portal = Tenant::factory()->create(['plan_id' => $plan->id]);
    $manager = User::factory()->manager($portal)->create();

    Service::factory()->for($portal)->create();

    Livewire::actingAs($manager)
        ->test('pages::app.services')
        ->call('createService')
        ->set('form.name', 'Secondo servizio')
        ->call('saveService')
        ->assertHasErrors(['form.name'])
        ->assertSee('Your plan includes up to one service: delete it or ask for a bigger plan.');

    expect(Service::query()->where('tenant_id', $portal->id)->count())->toBe(1);
});

test('a manager cannot reach the plans page', function () {
    $manager = User::factory()->manager()->create();

    Livewire::actingAs($manager)
        ->test('pages::admin.plans')
        ->call('createPlan')
        ->assertForbidden();

    $this->actingAs($manager)->get(route('admin.plans'))->assertForbidden();
});
