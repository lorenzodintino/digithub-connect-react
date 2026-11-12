<?php

use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantLicense;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The licence as the people who run the platform and the portals read it:
 * the administrator page with its controls, and the manager's own box.
 */
beforeEach(function () {
    config(['services.license.enforcement' => 'on']);
    $this->admin = User::factory()->admin()->create();
    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $this->manager = User::factory()->manager($this->tenant)->create();
});

test('the administrator reads state, end, plans, last check and what is still to collect', function () {
    Plan::factory()->create(['name' => 'Pro', 'position' => 2]);
    TenantLicense::factory()->for($this->tenant)->create(['expires_at' => '2027-01-15 00:00:00', 'plan_name' => 'Pro', 'outstanding_cents' => 15000]);

    $this->actingAs($this->admin)
        ->get(route('admin.portals.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/portal')
            ->where('license.enforcement', 'on')
            ->where('license.governed', true)
            ->where('license.exempt', false)
            ->where('license.state', 'Active')
            ->where('license.color', 'emerald')
            ->where('license.expires_at', '15 Jan 2027')
            ->where('license.plan', 'Pro')
            ->where('license.local_plan', 'Pro')
            ->where('license.plan_unknown', false)
            ->where('license.balance', '€ 150,00')
            ->where('license.would_block', null)
            ->where('license.error', null)
            ->where('subscription.governed', true)
            ->where('plan.from_license', true));
});

test('the administrator is told what a refused portal would be blocked for, and that a plan name matches nothing', function () {
    TenantLicense::factory()->for($this->tenant)->expired()->create(['plan_name' => 'Platinum']);

    $this->actingAs($this->admin)->get(route('admin.portals.show', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('license.state', 'Expired')
        ->where('license.color', 'red')
        ->where('license.would_block', 'license expired')
        ->where('license.plan_unknown', true)
        ->where('license.local_plan', null));
});

test('the administrator page reads the copy even while enforcement is off, and says the portal is not governed', function () {
    config(['services.license.enforcement' => 'off']);
    $this->tenant->update(['plan_id' => Plan::factory()->create(['name' => 'Base'])->id]);
    TenantLicense::factory()->for($this->tenant)->create();
    Http::fake();

    $this->actingAs($this->admin)->get(route('admin.portals.show', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('license.enforcement', 'off')
        ->where('license.governed', false)
        ->where('subscription.governed', false)
        ->where('plan.from_license', false));

    Http::assertNothingSent();
});

test('the administrator page names a setup problem', function () {
    config(['services.license.url' => '']);
    TenantLicense::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)->get(route('admin.portals.show', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('license.configuration_problem', 'configuration'));
});

test('the last failure and a kept answer are shown', function () {
    TenantLicense::factory()->for($this->tenant)->stale(hours: 100, error: 'server_error')->create();

    $this->actingAs($this->admin)->get(route('admin.portals.show', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('license.stale', true)
        ->where('license.alarm', true)
        ->where('license.error', 'server_error'));
});

test('the manager reads the licence on the dashboard: state, dates, plan, what is due and paid, what renewing costs', function () {
    TenantLicense::factory()->for($this->tenant)->create([
        'plan_name' => 'Pro', 'starts_at' => '2026-01-15 00:00:00', 'expires_at' => '2027-01-15 00:00:00', 'grace_ends_at' => '2027-01-22 00:00:00',
        'outstanding_cents' => 15000, 'due_cents' => 35000, 'paid_cents' => 20000,
    ]);

    $this->actingAs($this->manager)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('manager.license.state', 'Active')
        ->where('manager.license.color', 'emerald')
        ->where('manager.license.plan', 'Pro')
        ->where('manager.license.starts_at', '15 Jan 2026')
        ->where('manager.license.expires_at', '15 Jan 2027')
        ->where('manager.license.lifetime', false)
        ->where('manager.license.grace_ends_at', '22 Jan 2027')
        ->where('manager.license.in_grace', false)
        ->where('manager.license.stale', false)
        ->where('manager.license.amounts', ['due' => '€ 350,00', 'paid' => '€ 200,00', 'outstanding' => '€ 150,00', 'outstanding_label' => 'Still to pay', 'owes' => true])
        ->where('manager.license.renewal', [['period' => '1 month', 'price' => '€ 35,00'], ['period' => '12 months', 'price' => '€ 350,00']])
        ->where('manager.license.tenant_id', $this->tenant->license_id)
        ->where('manager.license.checked_at', fn ($value) => is_string($value) && $value !== ''));
});

test('only the manager sees it: an operator and the administrator get no licence data', function () {
    TenantLicense::factory()->for($this->tenant)->create();
    $operator = User::factory()->operator($this->tenant)->create();

    $this->actingAs($operator)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('operator')->missing('manager'));
    $this->actingAs($this->admin)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('admin')->missing('manager'));
});

test('the card shows nothing while the License Manager does not govern the portal', function (string $case) {
    TenantLicense::factory()->for($this->tenant)->create();

    match ($case) {
        'off' => config(['services.license.enforcement' => 'off']),
        'observe' => config(['services.license.enforcement' => 'observe']),
        'exempt' => $this->tenant->update(['license_exempt' => true]),
    };

    $this->actingAs($this->manager)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('manager.license', null));
})->with(['off', 'observe', 'exempt']);

test('the settings page no longer carries a licence box of its own', function () {
    TenantLicense::factory()->for($this->tenant)->create();

    $this->actingAs($this->manager)->get(route('app.portal'))->assertInertia(fn (Assert $page) => $page->missing('license'));
});

test('a licence in tolerance says by when to renew', function () {
    TenantLicense::factory()->for($this->tenant)->inGrace()->create();

    $this->actingAs($this->manager)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('manager.license.in_grace', true)
        ->where('manager.license.color', 'amber')
        ->where('manager.license.grace_ends_at', fn ($value) => is_string($value) && $value !== ''));
});

test('a licence for life has no end and no tolerance', function () {
    TenantLicense::factory()->for($this->tenant)->create(['lifetime' => true, 'expires_at' => null, 'grace_ends_at' => null]);

    $this->actingAs($this->manager)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('manager.license.lifetime', true)
        ->where('manager.license.expires_at', null)
        ->where('manager.license.grace_ends_at', null));
});

test('a price for life reads «Lifetime»', function () {
    TenantLicense::factory()->for($this->tenant)->create(['renewal_prices' => [
        ['months' => null, 'price_cents' => 120000, 'currency' => 'EUR'],
    ]]);

    $this->actingAs($this->manager)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('manager.license.renewal', [['period' => 'Lifetime', 'price' => '€ 1.200,00']]));
});

test('what the customer has paid beyond what is due is a credit, not a negative amount', function () {
    TenantLicense::factory()->for($this->tenant)->create(['outstanding_cents' => -5000, 'due_cents' => 35000, 'paid_cents' => 40000]);

    $this->actingAs($this->manager)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('manager.license.amounts.outstanding_label', 'Credit')
        ->where('manager.license.amounts.outstanding', '€ 50,00')
        ->where('manager.license.amounts.owes', false));
});

test('a settled licence owes nothing', function () {
    TenantLicense::factory()->for($this->tenant)->create();

    $this->actingAs($this->manager)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('manager.license.amounts.outstanding', '€ 0,00')
        ->where('manager.license.amounts.owes', false));
});

test('without amounts or prices the card still shows the licence', function () {
    TenantLicense::factory()->for($this->tenant)->create(['outstanding_cents' => null, 'due_cents' => null, 'paid_cents' => null, 'renewal_prices' => null]);

    $this->actingAs($this->manager)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('manager.license.amounts', null)
        ->where('manager.license.renewal', [])
        ->where('manager.license.plan', 'Pro'));
});

test('a failed update shows the last known data and says so', function () {
    TenantLicense::factory()->for($this->tenant)->stale()->create();

    $this->actingAs($this->manager)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('manager.license.stale', true)
        ->where('manager.license.plan', 'Pro'));
});

test('the update button on the dashboard asks the License Manager again and shows the new totals and prices', function () {
    TenantLicense::factory()->for($this->tenant)->create(['outstanding_cents' => 0, 'due_cents' => 35000, 'paid_cents' => 35000]);
    Http::fake(['license.test/*' => Http::response(licenseAnswer())]);

    $this->actingAs($this->manager)
        ->post(route('license.recheck'))
        ->assertRedirect(route('dashboard'))
        ->assertInertiaFlash('toast.message', 'License checked.');

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('manager.license.amounts.outstanding', '€ 150,00')
        ->where('manager.license.amounts.paid', '€ 200,00')
        ->where('manager.license.renewal', [['period' => '1 month', 'price' => '€ 35,00'], ['period' => '12 months', 'price' => '€ 350,00']]));
    Http::assertSentCount(1);
});

test('the administrator checks a portal now, skipping the cache, even with enforcement off', function () {
    config(['services.license.enforcement' => 'off']);
    Http::fake(['license.test/*' => Http::response(licenseAnswer(license: ['planType' => ['id' => 'pt', 'name' => 'Studio', 'prices' => []]]))]);

    $this->actingAs($this->admin)
        ->post(route('admin.portals.license.refresh', $this->tenant))
        ->assertInertiaFlash('toast.message', 'License checked.');

    expect($this->tenant->fresh()->license->plan_name)->toBe('Studio');
    Http::assertSentCount(1);
});

test('a check that gets no valid answer says so and keeps the last one', function () {
    TenantLicense::factory()->for($this->tenant)->create();
    Http::fake(['license.test/*' => Http::response(['error' => ['code' => 'server_error', 'message' => 'x']], 500)]);

    $this->actingAs($this->admin)
        ->post(route('admin.portals.license.refresh', $this->tenant))
        ->assertInertiaFlash('toast.message', 'The License Manager did not give a valid answer (server_error). The last known answer is kept.');

    expect($this->tenant->fresh()->license->allowed)->toBeTrue();
});

test('checking twice in a row spends only one call', function () {
    Http::fake(['license.test/*' => Http::response(licenseAnswer())]);

    $this->actingAs($this->admin)->post(route('admin.portals.license.refresh', $this->tenant));
    $this->post(route('admin.portals.license.refresh', $this->tenant))->assertInertiaFlash('toast.message', 'Wait a few seconds before checking again.');

    Http::assertSentCount(1);
});

test('the administrator takes a portal out of the licence and brings it back, and the register is the platform\'s', function () {
    TenantLicense::factory()->for($this->tenant)->revoked()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.portals.license.exempt', $this->tenant), ['exempt' => true])
        ->assertInertiaFlash('toast.message', 'This portal is now outside the license.');

    expect($this->tenant->fresh()->license_exempt)->toBeTrue();

    $log = ActivityLog::query()->where('action', 'portal.license_exempt')->sole();
    expect($log->tenant_id)->toBeNull()->and($log->context)->toBe(['portal_id' => $this->tenant->id, 'exempt' => true]);

    $this->put(route('admin.portals.license.exempt', $this->tenant), ['exempt' => false])->assertInertiaFlash('toast.message', 'This portal is under the license again.');

    expect($this->tenant->fresh()->license_exempt)->toBeFalse();
});

test('saving the same exemption twice writes the register once', function () {
    $this->actingAs($this->admin);

    $this->put(route('admin.portals.license.exempt', $this->tenant), ['exempt' => true]);
    $this->put(route('admin.portals.license.exempt', $this->tenant), ['exempt' => true]);

    expect(ActivityLog::query()->where('action', 'portal.license_exempt')->count())->toBe(1);
});

test('the exemption needs a value', function () {
    $this->actingAs($this->admin)->put(route('admin.portals.license.exempt', $this->tenant), [])->assertSessionHasErrors('exempt');
});

test('a manager cannot touch the licence controls of the administrator', function () {
    TenantLicense::factory()->for($this->tenant)->create();
    Http::fake();

    $this->actingAs($this->manager);

    $this->post(route('admin.portals.license.refresh', $this->tenant))->assertForbidden();
    $this->put(route('admin.portals.license.exempt', $this->tenant), ['exempt' => true])->assertForbidden();

    expect($this->tenant->fresh()->license_exempt)->toBeFalse();
    Http::assertNothingSent();
});

test('the licence copy stays out of the portal export', function () {
    TenantLicense::factory()->for($this->tenant)->create(['plan_name' => 'PianoRiservato', 'outstanding_cents' => 98765]);

    $response = $this->actingAs($this->manager)->get(route('app.portal.export'));
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $contents = '';
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $contents .= $zip->getNameIndex($index).$zip->getFromIndex($index);
    }
    $zip->close();

    expect($contents)->not->toContain('PianoRiservato')->not->toContain('98765')->not->toContain('tenant_licenses');
});
