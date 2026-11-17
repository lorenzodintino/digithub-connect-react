<?php

use App\Mail\Platform\LicenseManagerBackMail;
use App\Mail\Platform\LicenseManagerDownMail;
use App\Models\Tenant;
use App\Models\TenantLicense;
use App\Models\User;
use App\Services\LicenseService;
use App\Support\LayoutNotices;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * When the License Manager cannot be reached the portals keep working on the last answer,
 * and the administrators — who can fix it — are told by the layout and by email.
 */
beforeEach(function () {
    Mail::fake();

    config(['services.license.enforcement' => 'on']);

    $this->tenant = Tenant::factory()->create();
    TenantLicense::factory()->for($this->tenant)->create();
    $this->admin = User::factory()->admin()->create();
    User::factory()->admin()->create(['is_active' => false]);
    $this->license = app(LicenseService::class);

    // What the License Manager does is whatever the test says: a later Http::fake would not replace the first.
    $this->reply = fn () => throw new ConnectionException('cURL error 28');
    Http::fake(fn () => ($this->reply)());
});

test('the administrators are told by email, once, when the License Manager stops answering', function () {
    $this->license->refresh($this->tenant);

    Mail::assertQueued(LicenseManagerDownMail::class, 1);
    Mail::assertQueued(LicenseManagerDownMail::class, fn (LicenseManagerDownMail $mail) => $mail->hasTo($this->admin->email) && $mail->code === 'unreachable' && ! $mail->configuration);

    // Still down a minute later, for this portal and for another: no second message inside six hours.
    $this->travel(1)->minutes();
    $this->license->refresh($this->tenant);
    $this->license->refresh(Tenant::factory()->create());

    Mail::assertQueued(LicenseManagerDownMail::class, 1);
});

test('it writes again every six hours while the problem lasts', function () {
    $this->license->refresh($this->tenant);
    $this->travel(7)->hours();
    $this->license->refresh($this->tenant);

    Mail::assertQueued(LicenseManagerDownMail::class, 2);
});

test('the portal keeps working on the last answer while it is down', function () {
    $status = $this->license->refresh($this->tenant);

    expect($status->stale)->toBeTrue()->and($status->allowed)->toBeTrue()->and($status->blocksBackOffice())->toBeFalse();
});

test('a configuration error is told as such', function () {
    $this->reply = fn () => Http::response(['error' => ['code' => 'unauthorized', 'message' => 'x']], 401);

    $this->license->refresh($this->tenant);

    Mail::assertQueued(LicenseManagerDownMail::class, fn (LicenseManagerDownMail $mail) => $mail->configuration && $mail->code === 'unauthorized');
});

test('our own rate limit is not an outage', function () {
    $this->reply = fn () => Http::response(['error' => ['code' => 'rate_limited', 'message' => 'x']], 429, ['Retry-After' => '30']);

    $this->license->refresh($this->tenant);

    Mail::assertNotQueued(LicenseManagerDownMail::class);
    expect($this->license->outage())->toBeNull();
});

test('the layout of an administrator shows the outage, and nobody else does', function () {
    $this->license->refresh($this->tenant);

    $notice = collect(LayoutNotices::for($this->admin))->firstWhere('key', 'license-outage');

    expect($notice['tone'])->toBe('red')->and($notice['message'])->toContain('unreachable');

    $manager = User::factory()->manager($this->tenant)->create();

    expect(collect(LayoutNotices::for($manager))->firstWhere('key', 'license-outage'))->toBeNull();
});

test('when it answers again the administrators are told and the notice goes away', function () {
    $this->license->refresh($this->tenant);

    $this->travel(1)->hours();
    $this->reply = fn () => Http::response(licenseAnswer());
    $this->license->refresh($this->tenant);

    Mail::assertQueued(LicenseManagerBackMail::class, 1);
    Mail::assertQueued(LicenseManagerBackMail::class, fn (LicenseManagerBackMail $mail) => $mail->hasTo($this->admin->email));

    expect($this->license->outage())->toBeNull()
        ->and(collect(LayoutNotices::for($this->admin))->firstWhere('key', 'license-outage'))->toBeNull();

    // A good answer with nothing wrong before it says nothing.
    $this->license->refresh($this->tenant);
    Mail::assertQueued(LicenseManagerBackMail::class, 1);
});

test('a new outage after the recovery writes at once', function () {
    $this->license->refresh($this->tenant);

    $this->reply = fn () => Http::response(licenseAnswer());
    $this->travel(1)->minutes();
    $this->license->refresh($this->tenant);

    $this->reply = fn () => throw new ConnectionException('cURL error 28');
    $this->travel(1)->minutes();
    $this->license->refresh($this->tenant);

    Mail::assertQueued(LicenseManagerDownMail::class, 2);
});

test('the messages render', function () {
    $down = (new LicenseManagerDownMail('unreachable', false, CarbonImmutable::parse('2026-11-17 10:00:00')))->render();
    $config = (new LicenseManagerDownMail('unauthorized', true, null))->render();
    $back = (new LicenseManagerBackMail(CarbonImmutable::parse('2026-11-17 10:00:00')))->render();

    expect($down)->toContain('unreachable')
        ->and($config)->toContain('unauthorized')
        ->and($back)->toContain('17');
});
