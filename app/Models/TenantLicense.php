<?php

namespace App\Models;

use App\Enums\LicenseOutcome;
use App\Enums\LicenseReason;
use Carbon\CarbonImmutable;
use Database\Factories\TenantLicenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The last answer of DigitHub License Manager about a portal, reduced to what
 * the platform needs. Written only by App\Services\LicenseService; read through
 * the LicenseStatus it builds. See the migration for what is kept and why.
 *
 * @property int $id
 * @property int $tenant_id
 * @property LicenseOutcome|null $outcome
 * @property bool|null $allowed
 * @property LicenseReason|null $reason
 * @property string|null $state
 * @property string|null $not_activated_reason
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $grace_ends_at
 * @property bool $lifetime
 * @property int|null $grace_days
 * @property string|null $plan_name
 * @property string|null $plan_code
 * @property array<string, bool|int|null>|null $plan_attributes
 * @property int|null $outstanding_cents
 * @property int|null $due_cents
 * @property int|null $paid_cents
 * @property string|null $currency
 * @property array<int, array{months: int|null, price_cents: int, currency: string}>|null $renewal_prices
 * @property CarbonImmutable|null $checked_at
 * @property CarbonImmutable|null $last_attempt_at
 * @property string|null $last_error
 * @property CarbonImmutable|null $last_error_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'outcome', 'allowed', 'reason', 'state', 'not_activated_reason',
    'starts_at', 'expires_at', 'grace_ends_at', 'lifetime', 'grace_days',
    'plan_name', 'plan_code', 'plan_attributes', 'outstanding_cents', 'due_cents', 'paid_cents', 'currency', 'renewal_prices',
    'checked_at', 'last_attempt_at', 'last_error', 'last_error_at',
])]
class TenantLicense extends Model
{
    /** @use HasFactory<TenantLicenseFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => LicenseOutcome::class,
            'allowed' => 'boolean',
            'reason' => LicenseReason::class,
            'starts_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'grace_ends_at' => 'immutable_datetime',
            'lifetime' => 'boolean',
            'renewal_prices' => 'array',
            'plan_attributes' => 'array',
            'checked_at' => 'immutable_datetime',
            'last_attempt_at' => 'immutable_datetime',
            'last_error_at' => 'immutable_datetime',
        ];
    }

    /**
     * The portal this answer is about.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
