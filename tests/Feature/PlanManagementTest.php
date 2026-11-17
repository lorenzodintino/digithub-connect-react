<?php

use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Plans are defined in the License Manager and copied by `plans:sync`: the administrator reads them
 * here and cannot change them. What each plan limits is checked below and in PlanLimitsTest.
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('the administrator reads the plans with their limits, what they include and how many portals sit on each', function () {
    $plan = Plan::factory()->lacking(['group_services'])->create(['name' => 'Pro', 'position' => 2, 'price_cents' => 3900, 'max_services' => 15, 'max_operators' => null, 'max_waiting_list' => 5]);
    Tenant::factory()->create(['plan_id' => $plan->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.plans'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plans')
            ->where('plans.0.name', 'Pro')
            ->where('plans.0.price', '€ 39,00')
            ->where('plans.0.services', '15')
            ->where('plans.0.operators', 'Unlimited')
            ->where('plans.0.waiting_list', '5')
            ->where('plans.0.portals_count', 1)
            ->where('plans.0.included', fn ($included) => collect($included)->contains('Shared resources') && ! collect($included)->contains('Group services'))
            ->where('syncedAt', null));
});

test('nothing in the application writes a plan but the synchronisation', function () {
    foreach (['admin.plans.store', 'admin.plans.update', 'admin.plans.toggle', 'admin.plans.destroy'] as $name) {
        expect(Route::has($name))->toBeFalse("the route {$name} should not exist");
    }

    $plan = Plan::factory()->create();

    $this->actingAs($this->admin);

    expect($this->admin->can('create', Plan::class))->toBeFalse()
        ->and($this->admin->can('update', $plan))->toBeFalse()
        ->and($this->admin->can('delete', $plan))->toBeFalse()
        ->and($this->admin->can('viewAny', Plan::class))->toBeTrue();
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
    $manager = User::factory()->manager(Tenant::factory()->create())->create();

    $this->actingAs($manager)->get(route('admin.plans'))->assertForbidden();
});
