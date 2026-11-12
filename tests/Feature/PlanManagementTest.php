<?php

use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

/**
 * The plan form as the page sends it.
 *
 * @return array<string, mixed>
 */
function planForm(array $overrides = []): array
{
    return [
        'name' => 'Studio',
        'description' => '',
        'price' => '',
        'max_services' => '',
        'max_operators' => '',
        'max_bookings_per_month' => '',
        'allows_custom_smtp' => false,
        'is_active' => true,
        'position' => '0',
        ...$overrides,
    ];
}

test('an administrator creates a plan with its limits', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.plans.store'), planForm([
            'name' => 'Pro',
            'description' => 'Per uno studio con più persone.',
            'price' => '39',
            'max_services' => '15',
            'max_operators' => '5',
            'max_bookings_per_month' => '1000',
            'allows_custom_smtp' => true,
        ]))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Plan created.');

    $plan = Plan::query()->sole();

    expect($plan->name)->toBe('Pro')
        ->and($plan->price_cents)->toBe(3900)
        ->and($plan->max_services)->toBe(15)
        ->and($plan->allows_custom_smtp)->toBeTrue();
});

test('an empty limit means no cap on that number', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.plans.store'), planForm())
        ->assertSessionHasNoErrors();

    $plan = Plan::query()->sole();

    expect($plan->max_services)->toBeNull()
        ->and($plan->limitLabel($plan->max_services))->toBe('Unlimited');
});

test('a plan name is required and the limits must be sane', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.plans.store'), planForm(['name' => '', 'max_services' => '0']))
        ->assertSessionHasErrors(['name', 'max_services']);

    expect(Plan::query()->exists())->toBeFalse();
});

test('a plan is retired instead of deleted while portals sit on it', function () {
    $plan = Plan::factory()->create();
    Tenant::factory()->create(['plan_id' => $plan->id]);

    $this->actingAs($this->admin)->patch(route('admin.plans.toggle', $plan))->assertInertiaFlash('toast.message', 'Plan retired.');

    expect($plan->fresh()->is_active)->toBeFalse();

    $this->get(route('admin.plans'))->assertInertia(fn (Assert $page) => $page->where('plans.0.can_delete', false)->where('plans.0.portals_count', 1));

    $this->delete(route('admin.plans.destroy', $plan))->assertForbidden();

    expect(Plan::query()->whereKey($plan->id)->exists())->toBeTrue();
});

test('a plan nobody uses can be deleted', function () {
    $plan = Plan::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.plans.destroy', $plan))
        ->assertInertiaFlash('toast.message', 'Plan deleted.');

    expect(Plan::query()->whereKey($plan->id)->exists())->toBeFalse();
});

test('an administrator puts a portal on a plan', function () {
    $plan = Plan::factory()->create(['name' => 'Pro']);
    $portal = Tenant::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.portals'))
        ->assertInertia(fn (Assert $page) => $page->where('plans.0.id', (string) $plan->id));

    $this->put(route('admin.portals.update', $portal), [...portalForm(['name' => $portal->name, 'slug' => $portal->slug]), 'plan_id' => (string) $plan->id])
        ->assertSessionHasNoErrors();

    expect($portal->fresh()->plan->name)->toBe('Pro');
});

test('the plan limits refuse a service past the limit', function () {
    $plan = Plan::factory()->create(['max_services' => 1]);
    $portal = Tenant::factory()->create(['plan_id' => $plan->id]);
    $manager = User::factory()->manager($portal)->create();

    Service::factory()->for($portal)->create();

    $this->actingAs($manager)
        ->post(route('app.services.store'), serviceForm($portal, ['name' => 'Secondo servizio']))
        ->assertSessionHasErrors(['name' => 'Your plan includes up to one service: delete it or ask for a bigger plan.']);

    expect(Service::query()->where('tenant_id', $portal->id)->count())->toBe(1);
});

test('a manager cannot reach the plans page', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)->get(route('admin.plans'))->assertForbidden();
    $this->post(route('admin.plans.store'), planForm(['name' => 'Furbo']))->assertForbidden();

    expect(Plan::query()->exists())->toBeFalse();
});
