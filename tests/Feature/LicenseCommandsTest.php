<?php

use App\Models\Tenant;
use App\Models\TenantLicense;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;

/**
 * The two commands of the licence: `licenses:refresh`, which keeps the copies
 * fresh and does nothing at all while enforcement is off, and
 * `licenses:suspended-portals`, the read-only list to check by hand before
 * turning enforcement on.
 */
test('refreshing does nothing while enforcement is off', function () {
    config(['services.license.enforcement' => 'off']);
    Tenant::factory()->create();
    Http::fake();

    $this->artisan('licenses:refresh')->expectsOutput('Enforcement is off: nothing to refresh.')->assertSuccessful();

    Http::assertNothingSent();
});

test('refreshing is scheduled every five minutes without overlapping', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'licenses:refresh'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/5 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});

test('it refreshes the portals that need it, leaves the exempt ones alone and skips one just checked', function (string $mode) {
    config(['services.license.enforcement' => $mode]);
    $never = Tenant::factory()->create();
    $old = Tenant::factory()->create();
    TenantLicense::factory()->for($old)->create(['checked_at' => now()->subHour(), 'last_attempt_at' => now()->subHour()]);
    $recent = Tenant::factory()->create();
    TenantLicense::factory()->for($recent)->create(['last_attempt_at' => now()->subSeconds(20)]);
    $exempt = Tenant::factory()->create(['license_exempt' => true]);
    Http::fake(['license.test/*' => Http::response(licenseAnswer())]);

    $this->artisan('licenses:refresh')->expectsOutput('Checked 2 portals, 0 without a valid answer.')->assertSuccessful();

    Http::assertSentCount(2);
    expect($never->fresh()->license)->not->toBeNull()
        ->and($old->fresh()->license->checked_at->isAfter(now()->subMinute()))->toBeTrue()
        ->and($exempt->fresh()->license)->toBeNull();
})->with(['observe', 'on']);

test('it takes the oldest answers first and stops at the limit', function () {
    config(['services.license.enforcement' => 'on']);
    $oldest = Tenant::factory()->create();
    TenantLicense::factory()->for($oldest)->create(['last_attempt_at' => now()->subDay()]);
    $middle = Tenant::factory()->create();
    TenantLicense::factory()->for($middle)->create(['last_attempt_at' => now()->subHours(5)]);
    $newest = Tenant::factory()->create();
    TenantLicense::factory()->for($newest)->create(['last_attempt_at' => now()->subHour()]);
    Http::fake(['license.test/*' => Http::response(licenseAnswer())]);

    $this->artisan('licenses:refresh', ['--limit' => 2])->assertSuccessful();

    Http::assertSentCount(2);
    expect($oldest->fresh()->license->last_attempt_at->isAfter(now()->subMinute()))->toBeTrue()
        ->and($middle->fresh()->license->last_attempt_at->isAfter(now()->subMinute()))->toBeTrue()
        ->and($newest->fresh()->license->last_attempt_at->isBefore(now()->subMinutes(30)))->toBeTrue();
});

test('it can check a single portal', function () {
    config(['services.license.enforcement' => 'on']);
    $one = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    Http::fake(['license.test/*' => Http::response(licenseAnswer())]);

    $this->artisan('licenses:refresh', ['--tenant' => $one->id])->assertSuccessful();

    Http::assertSentCount(1);
    expect($other->fresh()->license)->toBeNull();
});

test('it counts the portals that got no valid answer', function () {
    config(['services.license.enforcement' => 'on']);
    Tenant::factory()->create();
    Http::fake(['license.test/*' => Http::response(['error' => ['code' => 'server_error', 'message' => 'x']], 500)]);

    $this->artisan('licenses:refresh')->expectsOutput('Checked 1 portals, 1 without a valid answer.')->assertSuccessful();
});

test('it stops when the License Manager asks to slow down, and the rest waits for the next run', function () {
    config(['services.license.enforcement' => 'on']);
    Tenant::factory()->count(3)->create();
    Http::fake(['license.test/*' => Http::response(['error' => ['code' => 'rate_limited', 'message' => 'x']], 429, ['Retry-After' => '60'])]);

    $this->artisan('licenses:refresh')->expectsOutputToContain('asked to slow down')->assertSuccessful();

    Http::assertSentCount(1);
});

test('it calls nothing without a URL and a key', function () {
    config(['services.license.enforcement' => 'on', 'services.license.key' => '']);
    Tenant::factory()->create();
    Http::fake();

    $this->artisan('licenses:refresh')->assertFailed();

    Http::assertNothingSent();
});

test('the list of portals switched off tells the old subscription check from a hand switch, and changes nothing', function () {
    $forPayment = Tenant::factory()->inactive()->create(['slug' => 'scaduto', 'paid_until' => '2026-08-01', 'subscription_suspended_at' => '2026-08-10 06:00:00']);
    $byHand = Tenant::factory()->inactive()->create(['slug' => 'a-mano']);
    Tenant::factory()->create(['slug' => 'attivo']);
    TenantLicense::factory()->for($byHand)->create();

    $this->artisan('licenses:suspended-portals')
        ->expectsTable(
            ['Id', 'Slug', 'Name', 'Paid until', 'Suspended for payment on', 'Exempt', 'License (last answer)'],
            [
                [$forPayment->id, 'scaduto', $forPayment->name, '2026-08-01', '2026-08-10', 'no', 'never answered'],
                [$byHand->id, 'a-mano', $byHand->name, '-', '- (by hand)', 'no', 'licensed / active ('.now()->subMinute()->toDateString().')'],
            ],
        )
        ->expectsOutput('2 portals switched off: 1 by the old subscription check, 1 by hand. Nothing was changed.')
        ->assertSuccessful();

    expect($forPayment->fresh()->is_active)->toBeFalse()
        ->and($forPayment->fresh()->subscription_suspended_at)->not->toBeNull()
        ->and($byHand->fresh()->is_active)->toBeFalse();
    Http::assertNothingSent();
});

test('the list says so when no portal is switched off', function () {
    Tenant::factory()->create();

    $this->artisan('licenses:suspended-portals')->expectsOutput('No portal is switched off.')->assertSuccessful();
});
