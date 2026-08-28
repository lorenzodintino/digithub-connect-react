<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PortalClosureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A stretch of days the whole portal is closed.
 *
 * @property int $id
 * @property int $tenant_id
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tenant_id', 'starts_on', 'ends_on', 'reason'])]
class PortalClosure extends Model
{
    /** @use HasFactory<PortalClosureFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
        ];
    }

    /**
     * The portal that is closed.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope the query to the closures of the given portal.
     *
     * @param  Builder<PortalClosure>  $query
     */
    public function scopeForTenant(Builder $query, Tenant|int $tenant): void
    {
        $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->getKey() : $tenant);
    }

    /**
     * Scope the query to closures that have not ended yet.
     *
     * @param  Builder<PortalClosure>  $query
     */
    public function scopeUpcoming(Builder $query, ?string $timezone = null): void
    {
        $query->whereDate('ends_on', '>=', CarbonImmutable::now($timezone ?? config('app.timezone'))->toDateString());
    }

    /**
     * Scope the query to closures overlapping the given range of dates.
     *
     * @param  Builder<PortalClosure>  $query
     */
    public function scopeOverlapping(Builder $query, string $from, string $to): void
    {
        $query->whereDate('starts_on', '<=', $to)->whereDate('ends_on', '>=', $from);
    }

    /**
     * How many days the closure covers, both ends included.
     */
    public function days(): int
    {
        return $this->starts_on->daysUntil($this->ends_on)->count();
    }
}
