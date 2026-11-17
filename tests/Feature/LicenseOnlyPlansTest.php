<?php

use App\Enums\LicenseEnforcement;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantLicense;
use App\Models\User;
use App\Services\LicenseService;
use App\Support\LayoutNotices;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Plans, prices, dates and payments belong to the License Manager. The administrator keeps one power over
 * a portal: to switch it off, whatever its licence says. A portal without a valid licence is blocked by the
 * License Manager, not by a plan of its own.
 */
beforeEach(function () {
    config(['services.license.enforcement' => 'on']);

    $this->admin = User::factory()->admin()->create();
    $this->tenant = Tenant::factory()->create();
    $this->manager = User::factory()->manager($this->tenant)->create();
});

test('no manual way to give a plan or record a payment is left', function () {
    foreach (['admin.portals.paid-until', 'admin.plans.store', 'admin.plans.update', 'admin.plans.toggle', 'admin.plans.destroy'] as $name) {
        expect(Route::has($name))->toBeFalse("the route {$name} should not exist");
    }

    expect(array_keys(Artisan::all()))->not->toContain('portals:check-subscriptions')->not->toContain('licenses:suspended-portals')
        ->and(Schema::hasColumn('tenants', 'paid_until'))->toBeFalse()
        ->and(Schema::hasColumn('tenants', 'subscription_suspended_at'))->toBeFalse()
        ->and(Schema::hasColumn('tenants', 'subscription_reminders_sent'))->toBeFalse();
});

test('the portal form of the administrator has no plan, and a plan sent anyway changes nothing', function () {
    $base = Plan::factory()->create(['name' => 'Base', 'position' => 1]);
    $pro = Plan::factory()->create(['name' => 'Pro', 'position' => 2]);
    $this->tenant->update(['plan_id' => $base->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.portals'))
        ->assertInertia(fn (Assert $page) => $page->missing('plans')->where('portals.data.0.form', fn ($form) => ! array_key_exists('plan_id', $form->toArray())));

    $this->put(route('admin.portals.update', $this->tenant), [...portalForm(['name' => $this->tenant->name, 'slug' => $this->tenant->slug]), 'plan_id' => (string) $pro->id])
        ->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->plan_id)->toBe($base->id);
});

test('the portal detail of the administrator shows the plan of the licence and no paid period', function () {
    TenantLicense::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.portals.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->missing('subscription')->where('plan.from_license', true));
});

test('the manager reads the plan of the licence and no paid period of their own', function () {
    Plan::factory()->create(['name' => 'Pro', 'position' => 2]);
    TenantLicense::factory()->for($this->tenant)->create();

    $this->actingAs($this->manager)
        ->get(route('app.portal'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('plan.name', 'Pro')
            ->missing('plan.paid_until')
            ->missing('plan.state'));
});

test('the administrator can still switch a portal off, and then it is blocked in everything even with a valid licence', function () {
    TenantLicense::factory()->for($this->tenant)->create();

    $this->actingAs($this->manager)->get(route('app.services'))->assertOk();
    $this->get(route('portal.show', $this->tenant))->assertOk();

    $this->actingAs($this->admin)->patch(route('admin.portals.toggle', $this->tenant))->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->is_active)->toBeFalse();

    // The manager is out, the public pages are closed, and a good answer of the License Manager does not reopen it.
    auth()->logout();
    $this->get(route('portal.show', $this->tenant))->assertNotFound();
    $this->actingAs($this->manager->fresh())->get(route('app.services'))->assertRedirect(route('login'));

    app(LicenseService::class)->forget($this->tenant);
    expect($this->tenant->fresh()->is_active)->toBeFalse();
});

test('a portal without a licence is blocked by the License Manager, not by a plan', function (string $case) {
    if ($case === 'no licence at all') {
        TenantLicense::factory()->for($this->tenant)->notActivated()->create();
    } else {
        TenantLicense::factory()->for($this->tenant)->expired()->create();
    }

    $this->actingAs($this->manager)->get(route('app.services'))->assertRedirect(route('license.blocked'));
    $this->get(route('portal.show', $this->tenant->fresh()))->assertNotFound();

    expect($this->tenant->fresh()->effectivePlan()->featureMap())->toBe(array_fill_keys(Plan::FEATURES, false));
})->with(['no licence at all', 'a licence expired past its tolerance']);

test('on a live server the administrator is told when licences are not enforced', function (string $mode, bool $told) {
    app()->detectEnvironment(fn () => 'production');
    config(['services.license.enforcement' => $mode]);

    $notice = collect(LayoutNotices::for($this->admin))->firstWhere('key', 'license-enforcement');

    if ($told) {
        expect($notice['tone'])->toBe('red')->and($notice['message'])->toContain($mode);
    } else {
        expect($notice)->toBeNull();
    }

    expect(collect(LayoutNotices::for($this->manager))->firstWhere('key', 'license-enforcement'))->toBeNull();
})->with([
    'off' => ['off', true],
    'observe' => ['observe', true],
    'on' => ['on', false],
]);

test('outside a live server nobody is told about the enforcement mode', function () {
    config(['services.license.enforcement' => 'off']);

    expect(collect(LayoutNotices::for($this->admin))->firstWhere('key', 'license-enforcement'))->toBeNull()
        ->and(app(LicenseService::class)->enforcement())->toBe(LicenseEnforcement::Off);
});

test('the switch «outside the license» works: the portal runs with no limit whatever the License Manager says', function () {
    Http::fake();
    TenantLicense::factory()->for($this->tenant)->revoked()->create();

    // Under the licence a revoked portal is blocked.
    $this->actingAs($this->manager)->get(route('app.services'))->assertRedirect(route('license.blocked'));

    // The administrator takes it out of the licence: it opens, with no limit and every feature.
    $this->actingAs($this->admin)->put(route('admin.portals.license.exempt', $this->tenant), ['exempt' => true])->assertSessionHasNoErrors();

    $tenant = $this->tenant->fresh();

    expect($tenant->license_exempt)->toBeTrue()
        ->and($tenant->effectivePlan())->toBeNull()
        ->and($tenant->planUsage()->serviceLimit())->toBeNull()
        ->and($tenant->planUsage()->operatorLimit())->toBeNull()
        ->and($tenant->planAllows('resources'))->toBeTrue()
        ->and($tenant->planAllows('group_services'))->toBeTrue()
        ->and($tenant->planAllows('embed'))->toBeTrue();

    $this->actingAs($this->manager->fresh())->get(route('app.services'))->assertOk();
    $this->get(route('portal.show', $tenant))->assertOk();

    // The License Manager is never asked about it, not even by the scheduled refresh.
    $this->artisan('licenses:refresh')->assertSuccessful();
    Http::assertNothingSent();

    // Back under the licence, the block is back.
    $this->actingAs($this->admin)->put(route('admin.portals.license.exempt', $this->tenant), ['exempt' => false])->assertSessionHasNoErrors();
    $this->actingAs($this->manager->fresh())->get(route('app.services'))->assertRedirect(route('license.blocked'));
});

test('a portal outside the licence does not take the plan the License Manager names for it', function () {
    Plan::factory()->create(['name' => 'Base', 'position' => 1, 'max_services' => 1]);
    TenantLicense::factory()->for($this->tenant)->onPlan('Base')->withAttributes(['max_servizi' => 1])->create();
    $this->tenant->update(['license_exempt' => true]);

    expect($this->tenant->fresh()->effectivePlan())->toBeNull()
        ->and($this->tenant->fresh()->planUsage()->serviceLimit())->toBeNull();
});
