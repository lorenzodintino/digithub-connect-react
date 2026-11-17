<?php

namespace Database\Factories;

use App\Enums\LicenseOutcome;
use App\Enums\LicenseReason;
use App\Models\Tenant;
use App\Models\TenantLicense;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TenantLicense>
 */
class TenantLicenseFactory extends Factory
{
    /**
     * An active Pro licence checked a minute ago, ending in a year.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'outcome' => LicenseOutcome::Licensed,
            'allowed' => true,
            'reason' => LicenseReason::Active,
            'state' => 'ACTIVE',
            'not_activated_reason' => null,
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addYear(),
            'grace_ends_at' => now()->addYear()->addDays(7),
            'lifetime' => false,
            'grace_days' => 7,
            'plan_name' => 'Pro',
            'plan_code' => 'pro',
            'plan_attributes' => null,
            'outstanding_cents' => 0,
            'due_cents' => 35000,
            'paid_cents' => 35000,
            'currency' => 'EUR',
            'renewal_prices' => [
                ['months' => 1, 'price_cents' => 3500, 'currency' => 'EUR'],
                ['months' => 12, 'price_cents' => 35000, 'currency' => 'EUR'],
            ],
            'checked_at' => now()->subMinute(),
            'last_attempt_at' => now()->subMinute(),
            'last_error' => null,
            'last_error_at' => null,
        ];
    }

    /**
     * Ending within 14 days (the License Manager calls it EXPIRING_14).
     */
    public function expiring(int $days = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => $days <= 7 ? 'EXPIRING_7' : 'EXPIRING_14',
            'expires_at' => now()->addDays($days),
            'grace_ends_at' => now()->addDays($days + 7),
        ]);
    }

    /**
     * Past the end but inside the days of tolerance: still allowed.
     */
    public function inGrace(): static
    {
        return $this->state(fn (array $attributes) => [
            'reason' => LicenseReason::Grace,
            'state' => 'IN_GRACE',
            'expires_at' => now()->subDays(2),
            'grace_ends_at' => now()->addDays(5),
        ]);
    }

    /**
     * Past the tolerance: refused.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'allowed' => false,
            'reason' => LicenseReason::Expired,
            'state' => 'PAST_GRACE',
            'expires_at' => now()->subDays(30),
            'grace_ends_at' => now()->subDays(23),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'allowed' => false,
            'reason' => LicenseReason::Suspended,
            'state' => 'SUSPENDED',
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'allowed' => false,
            'reason' => LicenseReason::Revoked,
            'state' => 'REVOKED',
        ]);
    }

    /**
     * The License Manager does not know the portal (404 tenant_not_found).
     */
    public function notActivated(string $reason = 'tenant_not_found'): static
    {
        return $this->state(fn (array $attributes) => [
            'outcome' => LicenseOutcome::NotActivated,
            'allowed' => null,
            'reason' => null,
            'state' => null,
            'not_activated_reason' => $reason,
            'starts_at' => null,
            'expires_at' => null,
            'grace_ends_at' => null,
            'grace_days' => null,
            'plan_name' => null,
            'plan_code' => null,
            'plan_attributes' => null,
            'outstanding_cents' => null,
            'due_cents' => null,
            'paid_cents' => null,
            'currency' => null,
            'renewal_prices' => null,
        ]);
    }

    /**
     * The last attempt failed: the copy is served as stale, for this many hours.
     */
    public function stale(int $hours = 1, string $error = 'unreachable'): static
    {
        return $this->state(fn (array $attributes) => [
            'checked_at' => now()->subHours($hours),
            'last_attempt_at' => now()->subSeconds(10),
            'last_error' => $error,
            'last_error_at' => now()->subSeconds(10),
        ]);
    }

    /**
     * Never answered: only a failed attempt is on record.
     */
    public function neverAnswered(string $error = 'unreachable'): static
    {
        return $this->state(fn (array $attributes) => [
            'outcome' => null,
            'allowed' => null,
            'reason' => null,
            'state' => null,
            'starts_at' => null,
            'expires_at' => null,
            'grace_ends_at' => null,
            'grace_days' => null,
            'plan_name' => null,
            'plan_code' => null,
            'plan_attributes' => null,
            'outstanding_cents' => null,
            'due_cents' => null,
            'paid_cents' => null,
            'currency' => null,
            'renewal_prices' => null,
            'checked_at' => null,
            'last_attempt_at' => now()->subSeconds(10),
            'last_error' => $error,
            'last_error_at' => now()->subSeconds(10),
        ]);
    }

    public function onPlan(string $name): static
    {
        return $this->state(fn (array $attributes) => ['plan_name' => $name, 'plan_code' => Str::slug($name)]);
    }

    /**
     * What the License Manager sent for the plan, by the keys it uses (see App\Support\PlanAttributes).
     *
     * @param  array<string, bool|int|null>  $attributes
     */
    public function withAttributes(array $attributes): static
    {
        return $this->state(fn (array $state) => ['plan_attributes' => $attributes]);
    }
}
