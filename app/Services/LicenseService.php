<?php

namespace App\Services;

use App\Enums\LicenseEnforcement;
use App\Enums\LicenseOutcome;
use App\Enums\LicenseReason;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantLicense;
use App\Support\LicenseStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The only door to DigitHub License Manager (read-only `GET /api/v1/license`).
 *
 * The rest of the application asks this class for a LicenseStatus and never
 * speaks HTTP to the License Manager. The contract is docs/guida-integrazione-
 * licenze.md: access is decided by `access.allowed`, the plan by the name of
 * `license.planType`, and a License Manager that does not answer never blocks
 * a portal that already had a valid answer.
 *
 * A request is served from the cache and from the copy kept in
 * `tenant_licenses`: the HTTP call happens in the scheduled `licenses:refresh`,
 * on the administrator's «Check now», and in a request only when there is no
 * usable copy at all. With enforcement `off` nothing is ever called on its own.
 *
 * The key travels only in the Authorization header and never reaches a log, a
 * message or the browser: failures are logged by portal id and error code.
 */
class LicenseService
{
    private const ENDPOINT = '/api/v1/license';

    /**
     * Failures that are about how the platform is set up, not about a portal.
     * They are shown to the administrator, never held against the customers.
     *
     * @var array<int, string>
     */
    private const CONFIGURATION_ERRORS = ['configuration', 'insecure_url', 'unauthorized', 'software_mismatch', 'https_required'];

    public function enforcement(): LicenseEnforcement
    {
        return LicenseEnforcement::fromConfig(config('services.license.enforcement'));
    }

    /**
     * Whether the License Manager decides access and plan for this portal.
     */
    public function governs(Tenant $tenant): bool
    {
        return $this->enforcement() === LicenseEnforcement::On && ! $tenant->license_exempt;
    }

    /**
     * Whether the portal is only being watched: checked and logged, never blocked.
     */
    public function observes(Tenant $tenant): bool
    {
        return $this->enforcement() === LicenseEnforcement::Observe && ! $tenant->license_exempt;
    }

    /**
     * Whether URL and key are there and safe to use: nothing is sent otherwise.
     */
    public function configurationProblem(): ?string
    {
        $url = (string) config('services.license.url');

        if ($url === '' || (string) config('services.license.key') === '') {
            return 'configuration';
        }

        if (app()->isProduction() && ! str_starts_with(strtolower($url), 'https://')) {
            return 'insecure_url';
        }

        return null;
    }

    /**
     * The code of the last failure that points at the platform's own setup, if any.
     */
    public function platformProblem(): ?string
    {
        $code = Cache::get('license.platform-problem');

        return is_string($code) ? $code : null;
    }

    /**
     * What is known about the licence of a portal, without waiting for the License Manager.
     *
     * Cache first, then the copy in the database. A call is made only when
     * enforcement is on or observing and there is no usable copy.
     */
    public function status(Tenant $tenant): LicenseStatus
    {
        $cached = Cache::get($this->cacheKey($tenant));

        if ($cached instanceof LicenseStatus) {
            return $cached;
        }

        $copy = $tenant->license;

        if ($this->needsLiveCheck($copy)) {
            return $this->refreshOnce($tenant);
        }

        $status = $this->fromCopy($copy);
        Cache::put($this->cacheKey($tenant), $status, $this->ttl($status));

        return $status;
    }

    /**
     * The copy of the last answer as a status, without cache and without a call: for lists.
     */
    public function fromCopy(?TenantLicense $copy): LicenseStatus
    {
        if ($copy === null || $copy->outcome === null) {
            return LicenseStatus::unavailable($copy?->last_error);
        }

        $stale = $copy->last_error !== null;
        $offline = now()->subHours(max(1, (int) config('services.license.offline_grace_hours')));

        return new LicenseStatus(
            outcome: $copy->outcome,
            allowed: (bool) $copy->allowed,
            reason: $copy->reason,
            state: $copy->state,
            startsAt: $copy->starts_at,
            expiresAt: $copy->expires_at,
            graceEndsAt: $copy->grace_ends_at,
            lifetime: $copy->lifetime,
            planName: $copy->plan_name,
            outstandingCents: $copy->outstanding_cents,
            dueCents: $copy->due_cents,
            paidCents: $copy->paid_cents,
            currency: $copy->currency,
            renewalPrices: $copy->renewal_prices ?? [],
            checkedAt: $copy->checked_at,
            notActivatedReason: $copy->not_activated_reason,
            stale: $stale,
            alarm: $stale && $copy->checked_at !== null && $copy->checked_at->lt($offline),
            error: $copy->last_error,
        );
    }

    /**
     * Ask the License Manager now and keep what it says.
     *
     * A failed attempt keeps the previous answer, marked stale, and says why.
     * The answer comes back already interpreted.
     */
    public function refresh(Tenant $tenant): LicenseStatus
    {
        $copy = $tenant->license()->first() ?? $tenant->license()->make(['lifetime' => false]);

        $outcome = $this->call($tenant);

        $values = ['last_attempt_at' => now()];

        if ($outcome['error'] !== null) {
            $this->noteFailure($tenant, $outcome['error']);
            $values += ['last_error' => $outcome['error'], 'last_error_at' => now()];
        } else {
            $this->clearPlatformProblem();
            $values += $outcome['values'] + ['checked_at' => now(), 'last_error' => null, 'last_error_at' => null];
        }

        $copy->fill($values)->save();
        $tenant->setRelation('license', $copy);

        $status = $this->fromCopy($copy);
        Cache::put($this->cacheKey($tenant), $status, $this->ttl($status));

        return $status;
    }

    /**
     * Whether the back-office of the portal must be closed: the License
     * Manager says no and enforcement is on. While observing, only logs.
     */
    public function blocksBackOffice(Tenant $tenant): bool
    {
        if ($tenant->license_exempt) {
            return false;
        }

        return match ($this->enforcement()) {
            LicenseEnforcement::Off => false,
            LicenseEnforcement::Observe => $this->observe($tenant, $this->status($tenant)),
            LicenseEnforcement::On => $this->status($tenant)->blocksBackOffice(),
        };
    }

    /**
     * Whether the public pages of the portal must close, like those of a suspended portal.
     */
    public function blocksPublic(Tenant $tenant): bool
    {
        if ($tenant->license_exempt) {
            return false;
        }

        return match ($this->enforcement()) {
            LicenseEnforcement::Off => false,
            LicenseEnforcement::Observe => $this->observe($tenant, $this->status($tenant)),
            LicenseEnforcement::On => $this->status($tenant)->blocksPublic(),
        };
    }

    /**
     * The plan whose limits apply: the one the licence names when the License
     * Manager governs the portal, the portal's own otherwise.
     *
     * The name is compared trimmed and lowercase against the local plans. An
     * unknown name gets the most restrictive plan (the first by position) and
     * a warning in the log.
     */
    public function planFor(Tenant $tenant): ?Plan
    {
        if (! $this->governs($tenant)) {
            return $tenant->plan;
        }

        $status = $this->status($tenant);

        if ($status->outcome !== LicenseOutcome::Licensed || $status->planKey() === null) {
            return $tenant->plan;
        }

        /** @var Collection<int, Plan> $plans */
        $plans = Plan::query()->orderBy('position')->orderBy('id')->get();
        $plan = $plans->first(fn (Plan $candidate): bool => mb_strtolower(trim($candidate->name)) === $status->planKey());

        if ($plan !== null) {
            return $plan;
        }

        if (Cache::add('license.unknown-plan.'.$tenant->getKey().'.'.md5((string) $status->planKey()), true, 3600)) {
            Log::warning('License plan unknown, using the most restrictive one.', ['tenant' => $tenant->getKey(), 'plan' => $status->planKey()]);
        }

        return $plans->first();
    }

    /**
     * The local plan the licence maps to, or null when the name is unknown here. For the administrator page.
     */
    public function mappedPlan(LicenseStatus $status): ?Plan
    {
        if ($status->planKey() === null) {
            return null;
        }

        return Plan::query()->get()->first(fn (Plan $plan): bool => mb_strtolower(trim($plan->name)) === $status->planKey());
    }

    /**
     * Forget the cached verdict of a portal, so the next read looks at the copy.
     */
    public function forget(Tenant $tenant): void
    {
        Cache::forget($this->cacheKey($tenant));
    }

    /**
     * One call to the License Manager, interpreted.
     *
     * @return array{error: string|null, values: array<string, mixed>}
     */
    private function call(Tenant $tenant): array
    {
        if (($problem = $this->configurationProblem()) !== null) {
            return $this->failure($problem);
        }

        if (Cache::has('license.backoff')) {
            return $this->failure('rate_limited');
        }

        // The portal's UUID, not its numeric id: the numeric one can repeat if the database is recreated.
        if (blank($tenant->license_id)) {
            return $this->failure('no_license_id');
        }

        $query = ['tenant' => (string) $tenant->license_id];

        if (filled(config('services.license.software'))) {
            $query['software'] = (string) config('services.license.software');
        }

        try {
            $response = Http::withToken((string) config('services.license.key'))
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(max(1, (int) config('services.license.timeout')))
                ->get(rtrim((string) config('services.license.url'), '/').self::ENDPOINT, $query);
        } catch (Throwable) {
            // Refused connection, DNS, timeout: the message could carry the address, so it is dropped.
            return $this->failure('unreachable');
        }

        return $this->interpret($response);
    }

    /**
     * The answer, by HTTP status and error code (guide, §4.4).
     *
     * @return array{error: string|null, values: array<string, mixed>}
     */
    private function interpret(Response $response): array
    {
        $code = $response->json('error.code');
        $code = is_string($code) ? $code : null;

        if ($response->status() === 200) {
            return $this->licensed($response);
        }

        if ($response->status() === 404 && in_array($code, ['tenant_not_found', 'no_license'], true)) {
            return $this->answer(LicenseOutcome::NotActivated, ['not_activated_reason' => $code]);
        }

        if ($response->status() === 403 && $code === 'software_inactive') {
            return $this->answer(LicenseOutcome::SoftwareInactive);
        }

        if ($response->status() === 429) {
            Cache::put('license.backoff', true, max(1, min(300, (int) $response->header('Retry-After') ?: 60)));

            return $this->failure('rate_limited');
        }

        // unauthorized, software_mismatch, https_required, invalid_request, server_error, anything else.
        return $this->failure($code ?? 'http_'.$response->status());
    }

    /**
     * A 200: the verdict and the few fields kept, or `malformed` when the shape is not the contract's.
     *
     * @return array{error: string|null, values: array<string, mixed>}
     */
    private function licensed(Response $response): array
    {
        $data = $response->json('data');
        $allowed = data_get($data, 'access.allowed');
        $reason = data_get($data, 'access.reason');
        $plan = data_get($data, 'license.planType.name');

        if (! is_array($data) || ! is_bool($allowed) || ! is_string($reason) || ! is_string($plan) || trim($plan) === '') {
            return $this->failure('malformed');
        }

        $state = data_get($data, 'access.state');
        $graceDays = data_get($data, 'access.graceDays');
        $outstanding = data_get($data, 'balance.outstandingCents');
        $due = data_get($data, 'balance.dueCents');
        $paid = data_get($data, 'balance.paidCents');
        $currency = data_get($data, 'balance.currency');

        return $this->answer(LicenseOutcome::Licensed, [
            'allowed' => $allowed,
            'reason' => LicenseReason::tryFrom($reason)?->value,
            'state' => is_string($state) ? mb_substr($state, 0, 20) : null,
            'starts_at' => $this->date(data_get($data, 'license.startsAt')),
            'expires_at' => $this->date(data_get($data, 'access.expiresAt')),
            'grace_ends_at' => $this->date(data_get($data, 'access.graceEndsAt')),
            'lifetime' => data_get($data, 'license.lifetime') === true,
            'grace_days' => is_int($graceDays) ? $graceDays : null,
            'plan_name' => mb_substr(trim($plan), 0, 80),
            'outstanding_cents' => is_int($outstanding) ? $outstanding : null,
            'due_cents' => is_int($due) ? $due : null,
            'paid_cents' => is_int($paid) ? $paid : null,
            'currency' => is_string($currency) ? mb_substr($currency, 0, 3) : null,
            'renewal_prices' => $this->renewalPrices(data_get($data, 'license.planType.prices')),
        ]);
    }

    /**
     * The prices a customer can renew at: only the ones still on sale, reduced to months, price and currency.
     *
     * A price that does not have the shape of the contract is dropped, never guessed at.
     *
     * @return array<int, array{months: int|null, price_cents: int, currency: string}>
     */
    private function renewalPrices(mixed $prices): array
    {
        if (! is_array($prices)) {
            return [];
        }

        $kept = [];

        foreach ($prices as $price) {
            $months = data_get($price, 'months');
            $cents = data_get($price, 'priceCents');
            $currency = data_get($price, 'currency');

            if (data_get($price, 'active') !== true || ! is_int($cents) || $cents < 0 || ! is_string($currency) || ($months !== null && ! is_int($months))) {
                continue;
            }

            $kept[] = ['months' => $months, 'price_cents' => $cents, 'currency' => mb_substr($currency, 0, 3)];
        }

        // Shortest first; a lifetime price (no months) last.
        usort($kept, fn (array $a, array $b): int => ($a['months'] ?? PHP_INT_MAX) <=> ($b['months'] ?? PHP_INT_MAX));

        return $kept;
    }

    /**
     * A valid answer: every kept column is set, so nothing of an older one is left over.
     *
     * @param  array<string, mixed>  $values
     * @return array{error: null, values: array<string, mixed>}
     */
    private function answer(LicenseOutcome $outcome, array $values = []): array
    {
        return [
            'error' => null,
            'values' => [
                'outcome' => $outcome->value,
                'allowed' => null,
                'reason' => null,
                'state' => null,
                'not_activated_reason' => null,
                'starts_at' => null,
                'expires_at' => null,
                'grace_ends_at' => null,
                'lifetime' => false,
                'grace_days' => null,
                'plan_name' => null,
                'outstanding_cents' => null,
                'due_cents' => null,
                'paid_cents' => null,
                'currency' => null,
                'renewal_prices' => null,
                ...$values,
            ],
        ];
    }

    /**
     * @return array{error: string, values: array<string, mixed>}
     */
    private function failure(string $code): array
    {
        return ['error' => $code, 'values' => []];
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A failure is logged once an hour per portal and code, and the ones that
     * point at the platform's own setup are remembered for the administrator.
     */
    private function noteFailure(Tenant $tenant, string $code): void
    {
        if (in_array($code, self::CONFIGURATION_ERRORS, true)) {
            Cache::put('license.platform-problem', $code, 86400);
        }

        if (! Cache::add('license.logged.'.$tenant->getKey().'.'.$code, true, 3600)) {
            return;
        }

        Log::log(in_array($code, self::CONFIGURATION_ERRORS, true) ? 'error' : 'warning', 'License check failed.', [
            'tenant' => $tenant->getKey(),
            'code' => $code,
        ]);
    }

    private function clearPlatformProblem(): void
    {
        Cache::forget('license.platform-problem');
    }

    /**
     * While observing, say once a day what enforcement would have done; never block.
     */
    private function observe(Tenant $tenant, LicenseStatus $status): false
    {
        $key = $status->blockKey();

        if ($key !== null && Cache::add('license.observe.'.$tenant->getKey().'.'.$key, true, 86400)) {
            Log::info('License would block the portal.', ['tenant' => $tenant->getKey(), 'reason' => $key]);
        }

        return false;
    }

    /**
     * Whether a request has to call the License Manager itself: never with
     * enforcement off, otherwise only without a usable copy.
     */
    private function needsLiveCheck(?TenantLicense $copy): bool
    {
        if ($this->enforcement() === LicenseEnforcement::Off) {
            return false;
        }

        if ($copy === null) {
            return true;
        }

        if ($copy->last_attempt_at?->gt(now()->subMinute())) {
            return false;
        }

        return $copy->checked_at === null
            || $copy->checked_at->lt(now()->subSeconds(2 * max(1, (int) config('services.license.cache_ttl'))));
    }

    /**
     * A live check under a lock, so a burst of requests makes one call; whoever
     * does not get the lock reads the copy.
     */
    private function refreshOnce(Tenant $tenant): LicenseStatus
    {
        $lock = Cache::lock('license.fetch.'.$tenant->getKey(), 10);

        if (! $lock->get()) {
            return $this->fromCopy($tenant->license);
        }

        try {
            return $this->refresh($tenant);
        } finally {
            $lock->release();
        }
    }

    private function cacheKey(Tenant $tenant): string
    {
        return 'license.status.'.$tenant->getKey();
    }

    /**
     * How long a verdict may be reused: five minutes for a licence, a minute
     * for «not activated» so an activation shows quickly, half a minute when unknown.
     */
    private function ttl(LicenseStatus $status): int
    {
        return match ($status->outcome) {
            LicenseOutcome::Licensed => max(1, (int) config('services.license.cache_ttl')),
            LicenseOutcome::NotActivated, LicenseOutcome::SoftwareInactive => max(1, (int) config('services.license.not_found_ttl')),
            LicenseOutcome::Unavailable => 30,
        };
    }
}
