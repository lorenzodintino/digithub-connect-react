<?php

use App\Mail\Platform\SubscriptionNoticeMail;
use App\Models\Booking;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantLicense;
use App\Models\User;
use App\Services\Impersonation;
use App\Services\LicenseService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * What the licence does to a portal: who is stopped, where, with what words,
 * and what is left alone (the administrator, the administrator's own
 * suspension, exempt portals, an observing platform). No call is ever made to
 * the License Manager here: every portal has a fresh copy of its answer, and
 * a call that slipped in would fail the test (Http::preventStrayRequests).
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00', 'UTC'));
    config(['services.license.enforcement' => 'on']);
    $this->admin = User::factory()->admin()->create();
    $this->tenant = Tenant::factory()->create(['name' => 'Studio Rossi', 'timezone' => 'Europe/Rome']);
    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->operator = User::factory()->operator($this->tenant)->create();
});

/**
 * Whether a notice with this key is among the ones every back-office screen carries.
 */
function hasNotice(Assert $page, string $key, ?string $tone = null): bool
{
    $notices = collect($page->toArray()['props']['notices'] ?? []);

    return $notices->contains(fn (array $notice) => $notice['key'] === $key && ($tone === null || $notice['tone'] === $tone));
}

test('a valid licence leaves the back-office alone', function () {
    TenantLicense::factory()->for($this->tenant)->create();

    $this->actingAs($this->manager)->get(route('app.services'))->assertOk();
    $this->actingAs($this->operator)->get(route('app.bookings'))->assertOk();
});

test('a refused licence sends managers and operators to the blocked screen from everywhere', function (string $state) {
    TenantLicense::factory()->for($this->tenant)->{$state}()->create();

    foreach ([$this->manager, $this->operator] as $member) {
        $this->actingAs($member)->get(route('dashboard'))->assertRedirect(route('license.blocked'));
        $this->get(route('app.bookings'))->assertRedirect(route('license.blocked'));
    }

    $this->actingAs($this->manager)->get(route('app.services'))->assertRedirect(route('license.blocked'));
    $this->post(route('app.staff.store'), ['name' => 'Nuova', 'email' => 'nuova@example.test'])->assertRedirect(route('license.blocked'));

    expect(User::query()->where('email', 'nuova@example.test')->exists())->toBeFalse();
})->with(['expired', 'suspended', 'revoked', 'notActivated', 'neverAnswered']);

test('nobody is logged out: the screen says why, with the end date and what to pass on', function () {
    TenantLicense::factory()->for($this->tenant)->expired()->create(['expires_at' => CarbonImmutable::parse('2026-09-10 12:00', 'UTC')]);

    $this->actingAs($this->manager)
        ->get(route('license.blocked'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('license/blocked')
            ->where('title', 'License not valid')
            ->where('message', 'The license expired on 10 Sep 2026.')
            ->where('isManager', true)
            ->where('tenantId', $this->tenant->license_id)
            ->where('supportEmail', config('mail.from.address')));

    $this->assertAuthenticatedAs($this->manager);

    config(['services.license.support_email' => 'assistenza@piattaforma.example']);

    $this->get(route('license.blocked'))->assertInertia(fn (Assert $page) => $page->where('supportEmail', 'assistenza@piattaforma.example'));
});

test('an operator is told only to ask the manager: no platform contacts, no portal id', function () {
    TenantLicense::factory()->for($this->tenant)->suspended()->create();
    config(['services.license.support_email' => 'assistenza@piattaforma.example']);

    $this->actingAs($this->operator)
        ->get(route('license.blocked'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('isManager', false)
            ->where('tenantId', null)
            ->where('supportEmail', null)
            ->where('message', 'The license of Studio Rossi is not valid at the moment. Contact the person who manages the portal.'));
});

test('a portal the License Manager does not know shows the portal id to pass on', function () {
    TenantLicense::factory()->for($this->tenant)->notActivated()->create();

    $this->actingAs($this->manager)->get(route('license.blocked'))->assertInertia(fn (Assert $page) => $page
        ->where('title', 'License not active')
        ->where('message', 'The license of this portal is not active yet.')
        ->where('tenantId', $this->tenant->license_id));
});

test('when nothing could be verified the screen says to try again', function () {
    TenantLicense::factory()->for($this->tenant)->neverAnswered()->create();

    $this->actingAs($this->manager)->get(route('license.blocked'))->assertInertia(fn (Assert $page) => $page
        ->where('title', 'License not verifiable')
        ->where('message', 'We cannot verify the license right now. Try again in a few minutes.'));
});

test('a blocked manager can still reach the export, the account settings and the way out', function () {
    TenantLicense::factory()->for($this->tenant)->expired()->create();

    $this->actingAs($this->manager);

    $this->get(route('app.portal.export'))->assertOk()->assertDownload();
    $this->get(route('profile.edit'))->assertOk();
    $this->get(route('appearance.edit'))->assertOk();
    $this->post(route('logout'))->assertRedirect();
    $this->assertGuest();
});

test('the blocked screen is not a place to linger when the licence is fine', function () {
    TenantLicense::factory()->for($this->tenant)->create();

    $this->actingAs($this->manager)->get(route('license.blocked'))->assertRedirect(route('dashboard'));
});

test('the manager asks the License Manager again, and is let in when it now says yes', function () {
    TenantLicense::factory()->for($this->tenant)->notActivated()->create();
    Http::fake(['license.test/*' => Http::response(licenseAnswer())]);

    $this->actingAs($this->manager)
        ->post(route('license.recheck'))
        ->assertRedirect(route('dashboard'))
        ->assertInertiaFlash('toast.message', 'License checked.');

    $this->get(route('app.services'))->assertOk();

    // A second click right away does not spend another call.
    $this->post(route('license.recheck'))->assertInertiaFlash('toast.message', 'Wait a few seconds before checking again.');
    Http::assertSentCount(1);
});

test('only a manager whose portal is governed can ask again', function () {
    TenantLicense::factory()->for($this->tenant)->notActivated()->create();

    $this->actingAs($this->operator)->post(route('license.recheck'))->assertNotFound();

    config(['services.license.enforcement' => 'off']);
    $this->actingAs($this->manager)->post(route('license.recheck'))->assertNotFound();
    Http::assertNothingSent();
});

test('the platform administrator is never stopped', function () {
    TenantLicense::factory()->for($this->tenant)->revoked()->create();

    $this->actingAs($this->admin)->get(route('admin.portals.show', $this->tenant))->assertOk();
    $this->get(route('dashboard'))->assertOk();
});

test('an administrator standing in for a manager goes past the block and the screen says so in red', function () {
    TenantLicense::factory()->for($this->tenant)->expired()->create();
    $this->actingAs($this->admin);
    app(Impersonation::class)->start($this->admin, $this->manager);

    $this->get(route('app.services'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => expect(hasNotice($page, 'license-bypass', 'red'))->toBeTrue()
            && expect(hasNotice($page, 'impersonation'))->toBeTrue());
});

test('the warning about a bypass is on every screen of the impersonation, with the reason', function () {
    TenantLicense::factory()->for($this->tenant)->suspended()->create();
    $this->actingAs($this->admin);
    app(Impersonation::class)->start($this->admin, $this->manager);

    $notice = collect($this->get(route('app.bookings'))->inertiaProps('notices'))->firstWhere('key', 'license-bypass');

    expect($notice['message'])->toContain('license suspended');
});

test('there is no warning when the portal has nothing to bypass', function () {
    TenantLicense::factory()->for($this->tenant)->create();
    $this->actingAs($this->admin);
    app(Impersonation::class)->start($this->admin, $this->manager);

    $this->get(route('app.services'))->assertInertia(fn (Assert $page) => expect(hasNotice($page, 'license-bypass'))->toBeFalse());
});

test('a real manager never gets past a block, not even with an impersonation of somebody else elsewhere', function () {
    TenantLicense::factory()->for($this->tenant)->expired()->create();

    $this->actingAs($this->manager)->get(route('app.services'))->assertRedirect(route('license.blocked'));
    expect(app(Impersonation::class)->isActive())->toBeFalse();

    // Once the impersonation ends, the portal is closed again for the manager.
    $this->actingAs($this->admin);
    app(Impersonation::class)->start($this->admin, $this->manager);
    $this->get(route('app.services'))->assertOk();
    app(Impersonation::class)->stop();
    $this->actingAs($this->manager)->get(route('app.services'))->assertRedirect(route('license.blocked'));
});

test('the public pages of a refused portal close like those of a suspended one, and the customer can still manage a booking', function () {
    TenantLicense::factory()->for($this->tenant)->expired()->create();
    $service = Service::factory()->for($this->tenant)->create();
    $operator = User::factory()->operator($this->tenant)->create();
    $booking = Booking::factory()->forSlot($service, $operator, CarbonImmutable::now('UTC')->addDays(3)->setTime(10, 0))->create();

    $this->get(route('portal.show', $this->tenant))->assertNotFound();
    $this->get(route('portal.service', [$this->tenant, $service]))->assertNotFound();

    $this->get(route('portal.booking', [$this->tenant, $booking]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('booking.suspended', true)->where('booking.can', ['cancel' => true, 'reschedule' => false]));
});

test('a blocked manager who visits the public page of their own portal gets what any visitor gets, not the blocked screen', function () {
    TenantLicense::factory()->for($this->tenant)->expired()->create();

    $this->actingAs($this->manager)->get(route('portal.show', $this->tenant))->assertNotFound();
    $this->get(route('home'))->assertOk();
});

test('the public pages stay open while the licence is fine, in tolerance, or its state is unknown', function (string $state) {
    TenantLicense::factory()->for($this->tenant)->{$state}()->create();

    $this->get(route('portal.show', $this->tenant))->assertOk();
})->with(['inGrace', 'neverAnswered', 'stale']);

test('with enforcement off a refused licence changes nothing', function () {
    config(['services.license.enforcement' => 'off']);
    TenantLicense::factory()->for($this->tenant)->revoked()->create();

    $this->actingAs($this->manager)->get(route('app.services'))->assertOk();
    $this->get(route('portal.show', $this->tenant))->assertOk();
    Http::assertNothingSent();
});

test('while observing, nobody is blocked and the log says once what would have happened', function () {
    config(['services.license.enforcement' => 'observe']);
    Log::spy();
    TenantLicense::factory()->for($this->tenant)->expired()->create();

    $this->actingAs($this->manager)->get(route('app.services'))->assertOk();
    $this->get(route('app.bookings'))->assertOk();
    $this->get(route('portal.show', $this->tenant))->assertOk();

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context) => $message === 'License would block the portal.' && $context === ['tenant' => $this->tenant->id, 'reason' => 'expired'])
        ->once();
});

test('an exempt portal is not touched by the licence', function () {
    $this->tenant->update(['license_exempt' => true]);
    TenantLicense::factory()->for($this->tenant)->revoked()->create();

    $this->actingAs($this->manager)->get(route('app.services'))->assertOk();
    $this->get(route('portal.show', $this->tenant))->assertOk();
});

test('a portal switched off by hand stays switched off even with a valid licence, and the licence never flips it', function () {
    TenantLicense::factory()->for($this->tenant)->create();
    $this->tenant->update(['is_active' => false]);

    $this->get(route('portal.show', $this->tenant))->assertNotFound();
    $this->actingAs($this->manager)->get(route('app.services'))->assertRedirect(route('login'));

    Http::fake(['license.test/*' => Http::response(licenseAnswer())]);
    app(LicenseService::class)->refresh($this->tenant);

    expect($this->tenant->fresh()->is_active)->toBeFalse();
});

test('a refused licence never switches the portal off: it comes back by itself when the licence does', function () {
    TenantLicense::factory()->for($this->tenant)->create();
    Http::fake(['license.test/*' => Http::sequence()
        ->push(licenseAnswer(['allowed' => false, 'reason' => 'expired']))
        ->push(licenseAnswer())]);
    $license = app(LicenseService::class);

    $license->refresh($this->tenant);
    expect($this->tenant->fresh()->is_active)->toBeTrue();
    $this->actingAs($this->manager)->get(route('app.services'))->assertRedirect(route('license.blocked'));

    Cache::flush();
    $license->refresh($this->tenant->fresh());
    $this->get(route('app.services'))->assertOk();
});

test('the manager is warned in tolerance, and as the end gets near', function (string $state, ?int $days, string $tone) {
    TenantLicense::factory()->for($this->tenant)->{$state}(...($days === null ? [] : [$days]))->create();

    $this->actingAs($this->manager)->get(route('app.services'))->assertOk()
        ->assertInertia(fn (Assert $page) => expect(hasNotice($page, 'license', $tone))->toBeTrue());
})->with([
    'in tolerance' => ['inGrace', null, 'red'],
    'fourteen days left' => ['expiring', 14, 'amber'],
    'ten days left' => ['expiring', 10, 'amber'],
    'four days left' => ['expiring', 4, 'amber'],
    'three days left' => ['expiring', 3, 'red'],
    'two days left' => ['expiring', 2, 'red'],
]);

test('no warning while the licence is comfortably valid, and none for an operator', function () {
    TenantLicense::factory()->for($this->tenant)->create();

    $this->actingAs($this->manager)->get(route('app.services'))->assertInertia(fn (Assert $page) => expect(hasNotice($page, 'license'))->toBeFalse());

    $this->tenant->license->update(['state' => 'EXPIRING_7', 'expires_at' => now()->addDays(2)]);
    Cache::flush();

    $this->actingAs($this->operator)->get(route('app.bookings'))->assertInertia(fn (Assert $page) => expect(hasNotice($page, 'license'))->toBeFalse());
});

test('the licence replaces the paid period notice of a governed portal, and only of that', function () {
    $this->tenant->forceFill(['paid_until' => '2026-10-05'])->save();
    TenantLicense::factory()->for($this->tenant)->create();

    $this->actingAs($this->manager)->get(route('app.services'))->assertInertia(fn (Assert $page) => expect(hasNotice($page, 'paid-period'))->toBeFalse());

    // The signed-in user keeps the portal it loaded on the first request: change it through them.
    $this->manager->tenant->update(['license_exempt' => true]);

    $this->get(route('app.services'))->assertInertia(fn (Assert $page) => expect(hasNotice($page, 'paid-period', 'red'))->toBeTrue());
});

test('the administrator is told when the platform setup cannot reach the License Manager, and nobody else', function () {
    Cache::put('license.platform-problem', 'unauthorized', 600);
    TenantLicense::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)->get(route('admin.portals'))->assertInertia(fn (Assert $page) => expect(hasNotice($page, 'license-platform', 'red'))->toBeTrue());
    $this->actingAs($this->manager)->get(route('app.services'))->assertInertia(fn (Assert $page) => expect(hasNotice($page, 'license-platform'))->toBeFalse());
});

test('the plan of a governed portal is the licence\'s and the form cannot change it', function () {
    $base = Plan::factory()->create(['name' => 'Base', 'position' => 1]);
    $pro = Plan::factory()->create(['name' => 'Pro', 'position' => 2]);
    $this->tenant->update(['plan_id' => $base->id]);
    TenantLicense::factory()->for($this->tenant)->create(['plan_name' => 'Pro']);

    expect($this->tenant->fresh()->effectivePlan()->is($pro))->toBeTrue();

    $this->actingAs($this->admin)
        ->put(route('admin.portals.update', $this->tenant), portalForm(['slug' => $this->tenant->slug, 'name' => $this->tenant->name, 'plan_id' => (string) $base->id, 'manager_name' => '', 'manager_email' => '']))
        ->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->plan_id)->toBe($base->id);

    $this->get(route('admin.portals'))->assertInertia(fn (Assert $page) => $page
        ->where('portals.data', fn ($portals) => collect($portals)->firstWhere('id', $this->tenant->id)['plan_locked'] === true));
});

test('the plan of an exempt portal is still the one the form sets', function () {
    $base = Plan::factory()->create(['name' => 'Base', 'position' => 1]);
    $pro = Plan::factory()->create(['name' => 'Pro', 'position' => 2]);
    $this->tenant->update(['license_exempt' => true, 'plan_id' => $base->id]);
    TenantLicense::factory()->for($this->tenant)->create(['plan_name' => 'Pro']);

    $this->actingAs($this->admin)
        ->put(route('admin.portals.update', $this->tenant), portalForm(['slug' => $this->tenant->slug, 'name' => $this->tenant->name, 'plan_id' => (string) $pro->id, 'manager_name' => '', 'manager_email' => '']))
        ->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->plan_id)->toBe($pro->id);
});

test('the old subscription check leaves a governed portal to the License Manager and keeps working for the others', function () {
    Mail::fake();
    $this->tenant->forceFill(['paid_until' => '2026-09-01'])->save();
    TenantLicense::factory()->for($this->tenant)->create();
    $exempt = Tenant::factory()->create(['license_exempt' => true, 'paid_until' => '2026-09-01']);
    User::factory()->manager($exempt)->create();

    $this->artisan('portals:check-subscriptions')->assertSuccessful();

    expect($this->tenant->fresh()->is_active)->toBeTrue()
        ->and($exempt->fresh()->is_active)->toBeFalse();
    Mail::assertQueued(SubscriptionNoticeMail::class, 1);
});

test('the paid period of a governed portal cannot be edited, an exempt one still can', function () {
    TenantLicense::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->put(route('admin.portals.paid-until', $this->tenant), ['paid_until' => '2027-01-01'])
        ->assertSessionHasErrors(['paid_until' => 'The paid period of this portal is managed by the License Manager.']);

    expect($this->tenant->fresh()->paid_until)->toBeNull();

    $this->tenant->update(['license_exempt' => true]);

    $this->put(route('admin.portals.paid-until', $this->tenant), ['paid_until' => '2027-01-01'])->assertSessionHasNoErrors();
    expect($this->tenant->fresh()->paid_until->toDateString())->toBe('2027-01-01');
});
