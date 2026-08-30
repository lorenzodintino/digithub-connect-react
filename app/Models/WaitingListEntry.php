<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\WaitingListEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Somebody who wants a place on a service and did not find one.
 *
 * @property int $id
 * @property string $uid
 * @property int $tenant_id
 * @property int $service_id
 * @property string $customer_name
 * @property string $customer_email
 * @property string|null $customer_phone
 * @property string $customer_timezone
 * @property string $customer_locale
 * @property CarbonImmutable $wanted_from
 * @property CarbonImmutable $wanted_to
 * @property CarbonImmutable|null $privacy_accepted_at
 * @property string|null $privacy_accepted_url
 * @property CarbonImmutable|null $notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'uid', 'tenant_id', 'service_id',
    'customer_name', 'customer_email', 'customer_phone', 'customer_timezone', 'customer_locale',
    'wanted_from', 'wanted_to', 'privacy_accepted_at', 'privacy_accepted_url', 'notified_at',
])]
class WaitingListEntry extends Model
{
    /** @use HasFactory<WaitingListEntryFactory> */
    use HasFactory;

    /**
     * How long to stay quiet before telling the same person again.
     *
     * A day of cancellations should not turn into a day of emails.
     */
    public const QUIET_HOURS = 12;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wanted_from' => 'immutable_date',
            'wanted_to' => 'immutable_date',
            'privacy_accepted_at' => 'immutable_datetime',
            'notified_at' => 'immutable_datetime',
        ];
    }

    /**
     * Resolve the entry by its unguessable token.
     */
    public function getRouteKeyName(): string
    {
        return 'uid';
    }

    /**
     * The portal the person is waiting on.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The service they are waiting for.
     *
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Scope the query to the entries of the given portal.
     *
     * @param  Builder<WaitingListEntry>  $query
     */
    public function scopeForTenant(Builder $query, Tenant|int $tenant): void
    {
        $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->getKey() : $tenant);
    }

    /**
     * Scope the query to the entries whose wanted period covers a date.
     *
     * @param  Builder<WaitingListEntry>  $query
     */
    public function scopeCovering(Builder $query, string $date): void
    {
        $query->whereDate('wanted_from', '<=', $date)->whereDate('wanted_to', '>=', $date);
    }

    /**
     * Scope the query to the entries that have not been told recently.
     *
     * @param  Builder<WaitingListEntry>  $query
     */
    public function scopeQuiet(Builder $query): void
    {
        $query->where(fn (Builder $inner) => $inner
            ->whereNull('notified_at')
            ->orWhere('notified_at', '<', now()->subHours(self::QUIET_HOURS)));
    }

    /**
     * A token nobody can guess, for the link that removes the entry.
     */
    public static function newUid(): string
    {
        return Str::lower(Str::random(32));
    }
}
