<?php

use App\Models\Booking;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $this->manager = User::factory()->manager($this->tenant)->create();
});

/**
 * Put the portal on a plan with the given limits.
 *
 * @param  array<string, mixed>  $limits
 */
function onPlan(Tenant $tenant, array $limits): Plan
{
    $plan = Plan::factory()->create($limits);
    $tenant->update(['plan_id' => $plan->id]);

    return $plan;
}

/**
 * Create a service through the manager's page.
 */
function createServiceAsManager(User $manager, string $name): TestResponse
{
    return test()->actingAs($manager)->post(route('app.services.store'), serviceForm($manager->tenant, ['name' => $name, 'duration_minutes' => 30]));
}

test('a service under the plan limit is created', function () {
    onPlan($this->tenant, ['max_services' => 2]);
    Service::factory()->for($this->tenant)->create();

    createServiceAsManager($this->manager, 'Secondo servizio')->assertSessionHasNoErrors();

    expect(Service::query()->count())->toBe(2);
});

test('a portal without a plan has no limit', function () {
    Service::factory()->for($this->tenant)->count(30)->create();

    createServiceAsManager($this->manager, 'Servizio trentuno')->assertSessionHasNoErrors();

    expect(Service::query()->count())->toBe(31);
});

test('an operator past the plan limit is refused, and the manager does not count', function () {
    onPlan($this->tenant, ['max_operators' => 1]);
    User::factory()->operator($this->tenant)->create();

    $this->actingAs($this->manager)
        ->post(route('app.staff.store'), [
            'name' => 'Giulia Bianchi',
            'email' => 'giulia@example.com',
            'timezone' => 'Europe/Rome',
            'locale' => 'it',
            'is_bookable' => true,
            'is_active' => true,
        ])
        ->assertSessionHasErrors(['name' => 'Your plan includes up to one operator: remove it or ask for a bigger plan.']);

    expect(User::query()->where('email', 'giulia@example.com')->exists())->toBeFalse();
});

test('a plan without its own SMTP sends through the platform even with a proven server', function () {
    $this->tenant->update([
        'mail_host' => 'smtp.example.test', 'mail_username' => 'user', 'mail_password' => 'secret', 'mail_verified_at' => now(),
    ]);
    onPlan($this->tenant, ['features' => ['custom_smtp' => false]]);

    expect($this->tenant->fresh()->hasCustomMailer())->toBeFalse();

    $this->tenant->plan->update(['features' => ['custom_smtp' => true]]);

    expect($this->tenant->fresh()->hasCustomMailer())->toBeTrue();
});

test('the manager is warned near the monthly threshold and past it, without blocking bookings', function (int $bookings, string $notice) {
    onPlan($this->tenant, ['max_bookings_per_month' => 10]);
    $service = Service::factory()->for($this->tenant)->create();
    Booking::factory()->for($service)->count($bookings)->create(['user_id' => $this->manager->id]);

    $this->actingAs($this->manager)->get(route('app.services'))->assertOk()->assertSee($notice);
})->with([
    'at 80%' => [8, 'This month the portal has taken 8 of the 10 bookings in your plan.'],
    'at 100%' => [10, 'This month the portal has taken 10 bookings and reached the 10 of your plan.'],
]);

test('bookings under the warning share raise no notice', function () {
    onPlan($this->tenant, ['max_bookings_per_month' => 10]);
    $service = Service::factory()->for($this->tenant)->create();
    Booking::factory()->for($service)->count(7)->create(['user_id' => $this->manager->id]);

    $this->actingAs($this->manager)->get(route('app.services'))->assertDontSee('bookings in your plan');
});

test('last month bookings do not count towards this month', function () {
    $this->travelTo(now('Europe/Rome')->startOfMonth()->addDays(10));
    onPlan($this->tenant, ['max_bookings_per_month' => 10]);
    $service = Service::factory()->for($this->tenant)->create();
    Booking::factory()->for($service)->count(5)->create(['user_id' => $this->manager->id, 'created_at' => now()->subMonth()]);
    Booking::factory()->for($service)->count(2)->create(['user_id' => $this->manager->id]);

    expect($this->tenant->planUsage()->bookingsThisMonth())->toBe(2);
});
