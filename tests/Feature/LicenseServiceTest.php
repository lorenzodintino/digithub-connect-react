<?php

use App\Enums\LicenseOutcome;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantLicense;
use App\Services\LicenseService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The only module that talks to DigitHub License Manager: what it sends, how
 * it reads every answer of the contract (docs/guida-integrazione-licenze.md),
 * what it keeps, and what it does when the License Manager does not answer.
 * With Http::fake only: the real License Manager is never called.
 */
beforeEach(function () {
    config(['services.license.enforcement' => 'on']);
    $this->tenant = Tenant::factory()->create();
    $this->license = app(LicenseService::class);
});

test('it asks for the tenant id with the key in the header and the software code as a parameter', function () {
    Http::fake(['license.test/*' => Http::response(licenseAnswer())]);

    $this->license->refresh($this->tenant);

    Http::assertSent(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $request->method() === 'GET'
            && str_starts_with($request->url(), 'http://license.test/api/v1/license?')
            && $query === ['tenant' => $this->tenant->license_id, 'software' => 'TEST_SOFTWARE']
            && $request->hasHeader('Authorization', 'Bearer lk_test_key')
            && ! str_contains($request->url(), 'lk_test_key');
    });
});

test('the software code is left out when none is configured', function () {
    config(['services.license.software' => '']);
    Http::fake(['license.test/*' => Http::response(licenseAnswer())]);

    $this->license->refresh($this->tenant);

    Http::assertSent(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return array_keys($query) === ['tenant'];
    });
});

test('a valid answer is kept in its reduced form, without the customer and the payments', function () {
    Http::fake(['license.test/*' => Http::response(licenseAnswer(license: ['planType' => ['id' => 'pt', 'name' => ' Pro ', 'prices' => []]]))]);

    $status = $this->license->refresh($this->tenant);
    $copy = $this->tenant->fresh()->license;

    expect($status->outcome)->toBe(LicenseOutcome::Licensed)
        ->and($status->allowed)->toBeTrue()
        ->and($status->planName)->toBe('Pro')
        ->and($status->outstandingCents)->toBe(15000)
        ->and($copy->plan_name)->toBe('Pro')
        ->and($copy->state)->toBe('ACTIVE')
        ->and($copy->last_error)->toBeNull();

    $stored = json_encode($copy->getAttributes());
    expect($stored)->not->toContain('Cliente Riservato')->not->toContain('riservato@cliente.example')->not->toContain('Bonifico segreto')->not->toContain('01234567897');
});

test('the totals and the prices still on sale are kept, shortest first, a price for life last', function () {
    Http::fake(['license.test/*' => Http::response(licenseAnswer(license: ['planType' => ['id' => 'pt', 'name' => 'Pro', 'prices' => [
        ['months' => null, 'priceCents' => 120000, 'currency' => 'EUR', 'active' => true],
        ['months' => 12, 'priceCents' => 35000, 'currency' => 'EUR', 'active' => true],
        ['months' => 6, 'priceCents' => 20000, 'currency' => 'EUR', 'active' => false],
        ['months' => 1, 'priceCents' => 3500, 'currency' => 'EUR', 'active' => true],
    ]]]))]);

    $status = $this->license->refresh($this->tenant);
    $copy = $this->tenant->fresh()->license;

    expect($status->dueCents)->toBe(35000)->and($status->paidCents)->toBe(20000)->and($status->outstandingCents)->toBe(15000)
        ->and($copy->renewal_prices)->toBe([
            ['months' => 1, 'price_cents' => 3500, 'currency' => 'EUR'],
            ['months' => 12, 'price_cents' => 35000, 'currency' => 'EUR'],
            ['months' => null, 'price_cents' => 120000, 'currency' => 'EUR'],
        ])
        ->and($status->renewalPrices)->toBe($copy->renewal_prices);
});

test('a price that does not have the shape of the contract is dropped, never guessed at', function () {
    Http::fake(['license.test/*' => Http::response(licenseAnswer(license: ['planType' => ['id' => 'pt', 'name' => 'Pro', 'prices' => [
        ['months' => 1, 'priceCents' => '3500', 'currency' => 'EUR', 'active' => true],
        ['months' => '12', 'priceCents' => 35000, 'currency' => 'EUR', 'active' => true],
        ['months' => 12, 'priceCents' => -1, 'currency' => 'EUR', 'active' => true],
        ['months' => 12, 'priceCents' => 35000, 'active' => true],
        ['months' => 12, 'priceCents' => 35000, 'currency' => 'EUR'],
        ['months' => 24, 'priceCents' => 60000, 'currency' => 'EUR', 'active' => true],
    ]]]))]);

    $this->license->refresh($this->tenant);

    expect($this->tenant->fresh()->license->renewal_prices)->toBe([['months' => 24, 'price_cents' => 60000, 'currency' => 'EUR']]);
});

test('an answer with no price list keeps no prices, and a not-activated answer clears the old totals and prices', function () {
    TenantLicense::factory()->for($this->tenant)->create();
    Http::fake(['license.test/*' => Http::response(['error' => ['code' => 'tenant_not_found', 'message' => 'x']], 404)]);

    $this->license->refresh($this->tenant);
    $copy = $this->tenant->fresh()->license;

    expect($copy->renewal_prices)->toBeNull()->and($copy->due_cents)->toBeNull()->and($copy->paid_cents)->toBeNull()->and($copy->outstanding_cents)->toBeNull();
});

test('the verdict is the one the License Manager gave, whatever the dates say', function (string $reason, bool $allowed, bool $blocks) {
    Http::fake(['license.test/*' => Http::response(licenseAnswer(['allowed' => $allowed, 'reason' => $reason]))]);

    $status = $this->license->refresh($this->tenant);

    expect($status->allowed)->toBe($allowed)
        ->and($status->blocksBackOffice())->toBe($blocks)
        ->and($status->blocksPublic())->toBe($blocks)
        ->and($status->blockKey())->toBe($blocks ? $reason : null);
})->with([
    'active' => ['active', true, false],
    'in tolerance' => ['grace', true, false],
    'expired' => ['expired', false, true],
    'not started' => ['not_started', false, true],
    'suspended' => ['suspended', false, true],
    'revoked' => ['revoked', false, true],
    'tenant archived' => ['tenant_archived', false, true],
    'customer archived' => ['customer_archived', false, true],
]);

test('a portal the License Manager does not know is not activated, and an unregistered one has no licence yet', function (string $code) {
    Http::fake(['license.test/*' => Http::response(['error' => ['code' => $code, 'message' => 'x']], 404)]);

    $status = $this->license->refresh($this->tenant);

    expect($status->outcome)->toBe(LicenseOutcome::NotActivated)
        ->and($status->notActivatedReason)->toBe($code)
        ->and($status->blocksBackOffice())->toBeTrue()
        ->and($status->blocksPublic())->toBeTrue()
        ->and($status->blockKey())->toBe($code)
        ->and($status->error)->toBeNull();
})->with(['tenant_not_found', 'no_license']);

test('a disabled software closes everything that is not licensed', function () {
    Http::fake(['license.test/*' => Http::response(['error' => ['code' => 'software_inactive', 'message' => 'x']], 403)]);

    $status = $this->license->refresh($this->tenant);

    expect($status->outcome)->toBe(LicenseOutcome::SoftwareInactive)
        ->and($status->blocksPublic())->toBeTrue()
        ->and($status->blockKey())->toBe('software_inactive');
});

test('a failure with no earlier answer is unverifiable: the back-office waits, the public pages stay open', function (int $http, ?string $code, string $expected) {
    Http::fake(['license.test/*' => Http::response($code === null ? 'oops' : ['error' => ['code' => $code, 'message' => 'x']], $http)]);

    $status = $this->license->refresh($this->tenant);

    expect($status->outcome)->toBe(LicenseOutcome::Unavailable)
        ->and($status->error)->toBe($expected)
        ->and($status->blocksBackOffice())->toBeTrue()
        ->and($status->blocksPublic())->toBeFalse()
        ->and($status->blockKey())->toBe('unverifiable');
})->with([
    'wrong key' => [401, 'unauthorized', 'unauthorized'],
    'wrong software' => [403, 'software_mismatch', 'software_mismatch'],
    'http instead of https' => [426, 'https_required', 'https_required'],
    'bad request' => [400, 'invalid_request', 'invalid_request'],
    'method' => [405, 'method_not_allowed', 'method_not_allowed'],
    'server error' => [500, 'server_error', 'server_error'],
    'no error body' => [503, null, 'http_503'],
]);

test('a failure keeps the last answer, marked stale, and nobody is blocked for it', function (int $http) {
    TenantLicense::factory()->for($this->tenant)->create(['checked_at' => now()->subHour()]);
    Http::fake(['license.test/*' => Http::response(['error' => ['code' => 'server_error', 'message' => 'x']], $http)]);

    $status = $this->license->refresh($this->tenant);

    expect($status->outcome)->toBe(LicenseOutcome::Licensed)
        ->and($status->allowed)->toBeTrue()
        ->and($status->stale)->toBeTrue()
        ->and($status->error)->toBe('server_error')
        ->and($status->alarm)->toBeFalse()
        ->and($status->blocksBackOffice())->toBeFalse()
        ->and($this->tenant->fresh()->license->checked_at->diffInMinutes(now()))->toBeGreaterThan(50);
})->with([500, 502]);

test('a network failure and a timeout read as unreachable, and keep the last answer', function () {
    TenantLicense::factory()->for($this->tenant)->create();
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    $status = $this->license->refresh($this->tenant);

    expect($status->error)->toBe('unreachable')
        ->and($status->stale)->toBeTrue()
        ->and($status->allowed)->toBeTrue();
});

test('a malformed answer is a failure, never a verdict', function (mixed $body) {
    Http::fake(['license.test/*' => Http::response($body)]);

    $status = $this->license->refresh($this->tenant);

    expect($status->error)->toBe('malformed')->and($status->outcome)->toBe(LicenseOutcome::Unavailable);
})->with([
    'not json' => ['<html>maintenance</html>'],
    'no data' => [['ok' => true]],
    'allowed is not a boolean' => [['data' => ['access' => ['allowed' => 'yes', 'reason' => 'active'], 'license' => ['planType' => ['name' => 'Pro']]]]],
    'no plan' => [['data' => ['access' => ['allowed' => true, 'reason' => 'active'], 'license' => ['planType' => []]]]],
]);

test('a copy older than the offline window is still used when it allowed access, and raises the alarm', function () {
    TenantLicense::factory()->for($this->tenant)->stale(hours: 100)->create();

    $status = $this->license->fromCopy($this->tenant->fresh()->license);

    expect($status->allowed)->toBeTrue()
        ->and($status->stale)->toBeTrue()
        ->and($status->alarm)->toBeTrue()
        ->and($status->blocksBackOffice())->toBeFalse();
});

test('a copy younger than the offline window raises no alarm', function () {
    TenantLicense::factory()->for($this->tenant)->stale(hours: 10)->create();

    expect($this->license->fromCopy($this->tenant->fresh()->license)->alarm)->toBeFalse();
});

test('a rate limit stops the calls for the time the License Manager asked', function () {
    Http::fake(['license.test/*' => Http::response(['error' => ['code' => 'rate_limited', 'message' => 'x']], 429, ['Retry-After' => '30'])]);

    $first = $this->license->refresh($this->tenant);
    $second = $this->license->refresh($this->tenant);

    expect($first->error)->toBe('rate_limited')->and($second->error)->toBe('rate_limited');
    Http::assertSentCount(1);
});

test('a failure that points at the platform setup is remembered for the administrator and cleared by a good answer', function () {
    Http::fake(['license.test/*' => Http::sequence()
        ->push(['error' => ['code' => 'unauthorized', 'message' => 'x']], 401)
        ->push(licenseAnswer())]);

    $this->license->refresh($this->tenant);
    expect($this->license->platformProblem())->toBe('unauthorized');

    $this->license->refresh($this->tenant);
    expect($this->license->platformProblem())->toBeNull();
});

test('nothing is sent without a URL and a key, and the problem is named', function (string $setting) {
    config(['services.license.'.$setting => '']);
    Http::fake();

    $status = $this->license->refresh($this->tenant);

    expect($status->error)->toBe('configuration')->and($this->license->platformProblem())->toBe('configuration');
    Http::assertNothingSent();
})->with(['url', 'key']);

test('in production the key is never sent over plain http', function () {
    $this->app['env'] = 'production';
    config(['services.license.url' => 'http://license.example']);
    Http::fake();

    expect($this->license->refresh($this->tenant)->error)->toBe('insecure_url');
    Http::assertNothingSent();
});

test('in production an https address is used', function () {
    $this->app['env'] = 'production';
    config(['services.license.url' => 'https://license.example']);
    Http::fake(['license.example/*' => Http::response(licenseAnswer())]);

    expect($this->license->refresh($this->tenant)->error)->toBeNull();
    Http::assertSentCount(1);
});

test('a failure is logged by portal and code, never with the key', function () {
    Log::spy();
    Http::fake(['license.test/*' => Http::response(['error' => ['code' => 'unauthorized', 'message' => 'x']], 401)]);

    $this->license->refresh($this->tenant);
    $this->license->refresh($this->tenant);

    // Once an hour per portal and code, not once per attempt.
    Log::shouldHaveReceived('log')->withArgs(function (string $level, string $message, array $context) {
        return $level === 'error'
            && $message === 'License check failed.'
            && $context === ['tenant' => $this->tenant->id, 'code' => 'unauthorized']
            && ! str_contains(json_encode($context), 'lk_test_key');
    })->once();
});

test('a request with no copy asks once and then reads the cache', function () {
    Http::fake(['license.test/*' => Http::response(licenseAnswer())]);

    $first = $this->license->status($this->tenant);
    $second = $this->license->status($this->tenant);

    expect($first->allowed)->toBeTrue()->and($second->allowed)->toBeTrue();
    Http::assertSentCount(1);
});

test('a request with a fresh copy makes no call at all', function () {
    TenantLicense::factory()->for($this->tenant)->create();
    Http::fake();

    expect($this->license->status($this->tenant->fresh())->allowed)->toBeTrue();
    Http::assertNothingSent();
});

test('a copy older than twice the cache lifetime is refreshed by the request', function () {
    TenantLicense::factory()->for($this->tenant)->create(['checked_at' => now()->subMinutes(20), 'last_attempt_at' => now()->subMinutes(20)]);
    Http::fake(['license.test/*' => Http::response(licenseAnswer(license: ['planType' => ['id' => 'pt', 'name' => 'Studio', 'prices' => []]]))]);

    $status = $this->license->status($this->tenant->fresh());

    expect($status->planName)->toBe('Studio');
    Http::assertSentCount(1);
});

test('a failed attempt is not repeated within a minute', function () {
    TenantLicense::factory()->for($this->tenant)->stale(hours: 1)->create();
    Http::fake();

    $this->license->status($this->tenant->fresh());

    Http::assertNothingSent();
});

test('with enforcement off nothing is ever called on its own', function (string $state) {
    config(['services.license.enforcement' => 'off']);
    Http::fake();

    if ($state === 'old copy') {
        TenantLicense::factory()->for($this->tenant)->create(['checked_at' => now()->subDay(), 'last_attempt_at' => now()->subDay()]);
    }

    $status = $this->license->status($this->tenant->fresh());

    expect($status->blocksBackOffice() || $status->blocksPublic())->toBe($state === 'no copy');
    Http::assertNothingSent();
    Cache::flush();
    expect($this->license->blocksBackOffice($this->tenant->fresh()))->toBeFalse();
    expect($this->license->blocksPublic($this->tenant->fresh()))->toBeFalse();
    Http::assertNothingSent();
})->with(['no copy', 'old copy']);

test('the plan of a governed portal is the one the licence names by its code, whatever the name says', function () {
    Plan::factory()->create(['name' => 'Base', 'position' => 1, 'max_services' => 3]);
    $pro = Plan::factory()->create(['name' => 'Pro', 'position' => 2, 'max_services' => 15]);
    $studio = Plan::factory()->create(['name' => 'Studio', 'position' => 3, 'max_services' => null]);
    $this->tenant->update(['plan_id' => $studio->id]);

    // Renamed in the License Manager: the name changed, the code did not.
    TenantLicense::factory()->for($this->tenant)->create(['plan_name' => 'Professional', 'plan_code' => 'pro']);

    expect($this->license->planFor($this->tenant->fresh())->is($pro))->toBeTrue()
        ->and($this->tenant->fresh()->planUsage()->serviceLimit())->toBe(15);
});

test('a name that looks like a plan is not a plan: only the code counts', function () {
    $base = Plan::factory()->create(['name' => 'Base', 'position' => 1]);
    Plan::factory()->create(['name' => 'Pro', 'position' => 2]);
    $this->tenant->update(['plan_id' => $base->id]);

    // A licence that says «Pro» but carries no code recognises nothing: the portal keeps its own plan.
    TenantLicense::factory()->for($this->tenant)->create(['plan_name' => 'Pro', 'plan_code' => null, 'plan_attributes' => null, 'checked_at' => now(), 'last_attempt_at' => now()]);

    expect($this->license->planFor($this->tenant->fresh())->is($base))->toBeTrue();
});

test('the limits and the features of a governed portal are the ones the licence carries', function () {
    $local = Plan::factory()->create(['name' => 'Pro', 'position' => 2, 'max_services' => 15, 'features' => ['resources' => false]]);
    TenantLicense::factory()->for($this->tenant)->create(['plan_code' => 'pro', 'plan_attributes' => [
        'max_servizi' => 40, 'max_operatori' => null, 'max_prenotazioni_mese' => 2000, 'max_lista_attesa' => 9, 'max_pacchetti' => 6,
        'is_risorse' => true, 'is_lista_attesa' => true, 'is_servizi_gruppo' => true,
    ]]);

    $plan = $this->license->planFor($this->tenant->fresh());

    expect($plan->id)->toBe($local->id)
        ->and($plan->name)->toBe('Pro')
        ->and($plan->max_services)->toBe(40)
        ->and($plan->max_operators)->toBeNull()
        ->and($plan->max_bookings_per_month)->toBe(2000)
        ->and($plan->max_waiting_list)->toBe(9)
        ->and($plan->max_packages)->toBe(6)
        ->and($plan->allows('resources'))->toBeTrue()
        ->and($plan->allows('waiting_list'))->toBeTrue()
        ->and($plan->allows('group_services'))->toBeTrue()
        ->and($plan->allows('packages'))->toBeFalse()
        ->and($plan->allows('embed'))->toBeFalse();

    // The row of the catalogue copy is not touched by the portal that reads it.
    expect($local->fresh()->max_services)->toBe(15)->and($local->fresh()->allows('resources'))->toBeFalse();
});

test('what the licence does not say is not included, and a number not whole is zero', function () {
    TenantLicense::factory()->for($this->tenant)->create(['plan_code' => 'pro', 'plan_attributes' => ['max_servizi' => 5, 'max_operatori' => 'molti', 'is_risorse' => true, 'is_embed_link' => 'yes']]);

    $plan = $this->license->planFor($this->tenant->fresh());

    expect($plan->max_services)->toBe(5)
        ->and($plan->max_operators)->toBe(0)
        ->and($plan->max_bookings_per_month)->toBe(0)
        ->and($plan->max_waiting_list)->toBe(0)
        ->and($plan->max_packages)->toBe(0)
        ->and($plan->allows('resources'))->toBeTrue()
        ->and($plan->allows('embed'))->toBeFalse()
        ->and($plan->allows('custom_smtp'))->toBeFalse();
});

test('a plan the catalogue copy does not know yet is still applied from its licence', function () {
    TenantLicense::factory()->for($this->tenant)->create(['plan_name' => 'Platinum', 'plan_code' => 'platinum', 'plan_attributes' => ['max_servizi' => null, 'is_risorse' => true]]);

    $plan = $this->license->planFor($this->tenant->fresh());

    expect($plan->code)->toBe('platinum')
        ->and($plan->name)->toBe('Platinum')
        ->and($plan->max_services)->toBeNull()
        ->and($plan->allows('resources'))->toBeTrue()
        ->and($this->license->mappedPlan($this->license->status($this->tenant->fresh())))->toBeNull();
});

test('a licence copied before the plan code existed is asked again', function () {
    config(['services.license.enforcement' => 'on']);
    Http::fake(['license.test/*' => Http::response(licenseAnswer(license: ['planType' => ['id' => 'pt', 'code' => 'pro', 'name' => 'Pro', 'prices' => [], 'attributes' => ['max_servizi' => 7]]]))]);
    TenantLicense::factory()->for($this->tenant)->create(['plan_name' => 'Pro', 'plan_code' => null, 'checked_at' => now()->subMinute(), 'last_attempt_at' => now()->subMinutes(5)]);

    $plan = $this->license->planFor($this->tenant->fresh());

    expect($plan->max_services)->toBe(7)->and($this->tenant->fresh()->license->plan_code)->toBe('pro');
});

test('a portal the License Manager does not govern keeps its own plan', function (string $case) {
    $pro = Plan::factory()->create(['name' => 'Pro', 'position' => 2]);
    $base = Plan::factory()->create(['name' => 'Base', 'position' => 1]);
    $this->tenant->update(['plan_id' => $base->id, 'license_exempt' => $case === 'exempt']);
    config(['services.license.enforcement' => $case === 'off' ? 'off' : 'on']);
    TenantLicense::factory()->for($this->tenant)->create(['plan_name' => 'Pro']);

    expect($this->license->planFor($this->tenant->fresh())->is($base))->toBeTrue();
})->with(['off', 'exempt']);

test('the licence keeps the code and the attributes Atrio knows, and drops the rest', function () {
    Http::fake(['license.test/*' => Http::response(licenseAnswer(license: ['planType' => ['id' => 'pt', 'code' => 'pro', 'name' => 'Pro', 'prices' => [], 'attributes' => [
        'max_servizi' => 15, 'max_pacchetti' => null, 'is_risorse' => true,
        'is_telepatia' => true, 'max_operatori' => 'molti', 'max_services' => 99, 'resources' => true,
    ]]]))]);

    $status = $this->license->refresh($this->tenant);
    $copy = $this->tenant->fresh()->license;

    expect($status->planCode)->toBe('pro')
        ->and($copy->plan_code)->toBe('pro')
        ->and($copy->plan_attributes)->toBe(['max_servizi' => 15, 'max_pacchetti' => null, 'is_risorse' => true]);
});

test('a plan code that is not a code is not kept', function (mixed $code) {
    Http::fake(['license.test/*' => Http::response(licenseAnswer(license: ['planType' => ['id' => 'pt', 'code' => $code, 'name' => 'Pro', 'prices' => []]]))]);

    expect($this->license->refresh($this->tenant)->planCode)->toBeNull();
})->with(['uppercase' => 'PRO', 'a space' => 'pro plus', 'a number' => 7, 'empty' => '']);

test('the limits and the features of a portal come from the answer of the License Manager', function () {
    Http::fake(['license.test/*' => Http::response(licenseAnswer())]);

    $this->license->refresh($this->tenant);

    $tenant = $this->tenant->fresh();

    expect($tenant->planUsage()->serviceLimit())->toBe(15)
        ->and($tenant->planUsage()->operatorLimit())->toBe(5)
        ->and($tenant->planUsage()->bookingLimit())->toBe(1000)
        ->and($tenant->planUsage()->waitingListLimit())->toBe(5)
        ->and($tenant->planUsage()->packageLimit())->toBe(3)
        ->and($tenant->planAllows('resources'))->toBeTrue()
        ->and($tenant->planAllows('group_services'))->toBeFalse()
        ->and($tenant->planAllows('custom_smtp'))->toBeTrue();
});

test('when the License Manager stops answering the limits stay those of the last answer', function () {
    Http::fake(['license.test/*' => Http::response(licenseAnswer())]);
    $this->license->refresh($this->tenant);

    Http::fake(fn () => throw new ConnectionException('cURL error 28'));
    Cache::flush();
    $this->license->refresh($this->tenant);

    $tenant = $this->tenant->fresh();

    expect($tenant->license->last_error)->toBe('unreachable')
        ->and($tenant->planUsage()->serviceLimit())->toBe(15)
        ->and($tenant->planAllows('resources'))->toBeTrue();
});
