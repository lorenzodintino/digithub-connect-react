<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One run of a group service: the class itself, with its seats and its staff.
 *
 * It comes into being when the first customer books that time, and it holds
 * the two things a group appointment needs that a one-to-one booking does not:
 * how many seats there are, and who is giving it.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $service_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable $blocked_starts_at
 * @property CarbonImmutable $blocked_ends_at
 * @property int $capacity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id', 'service_id', 'starts_at', 'ends_at',
    'blocked_starts_at', 'blocked_ends_at', 'capacity',
])]
class ServiceSession extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'blocked_starts_at' => 'immutable_datetime',
            'blocked_ends_at' => 'immutable_datetime',
        ];
    }

    /**
     * The portal the session belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The service being given.
     *
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * The staff giving this session.
     *
     * @return BelongsToMany<User, $this>
     */
    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * The seats taken, cancelled ones included.
     *
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Scope the query to the sessions still holding somebody.
     *
     * A session everybody cancelled out of stops occupying the agenda: the
     * class is not happening any more.
     *
     * @param  Builder<ServiceSession>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereHas('bookings', fn (Builder $bookings) => $bookings->whereIn('status', BookingStatus::blocking()));
    }

    /**
     * Scope the query to the sessions of the given portal.
     *
     * @param  Builder<ServiceSession>  $query
     */
    public function scopeForTenant(Builder $query, Tenant|int $tenant): void
    {
        $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->getKey() : $tenant);
    }

    /**
     * How many seats are taken by bookings that still count.
     */
    public function seatsTaken(): int
    {
        return $this->bookings()->whereIn('status', BookingStatus::blocking())->count();
    }

    /**
     * How many seats are still free.
     */
    public function seatsLeft(): int
    {
        return max(0, $this->capacity - $this->seatsTaken());
    }

    /**
     * Determine whether the session has run out of seats.
     */
    public function isFull(): bool
    {
        return $this->seatsLeft() === 0;
    }
}
