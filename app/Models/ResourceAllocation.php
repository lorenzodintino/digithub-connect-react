<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The physical resource and occupation window agreed when booking.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $resource_id
 * @property int|null $booking_id
 * @property int|null $booking_phase_id
 * @property int|null $service_session_id
 * @property string $resource_name
 * @property string $requirement_name
 * @property int $position
 * @property int $preparation_minutes
 * @property int $cleanup_minutes
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable|null $released_at
 */
#[Fillable([
    'tenant_id', 'resource_id', 'booking_id', 'booking_phase_id', 'service_session_id',
    'resource_name', 'requirement_name', 'position', 'preparation_minutes',
    'cleanup_minutes', 'starts_at', 'ends_at', 'released_at',
])]
class ResourceAllocation extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'released_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<\App\Models\Resource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<ServiceSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ServiceSession::class, 'service_session_id');
    }

    /** @param Builder<ResourceAllocation> $query */
    public function scopeBlocking(Builder $query): void
    {
        $query->whereNull('released_at')->where(function (Builder $owners): void {
            $owners->whereHas('booking', fn (Builder $booking) => $booking->whereIn('status', BookingStatus::blocking()))
                ->orWhereHas('session', fn (Builder $session) => $session->whereHas('bookings', fn (Builder $booking) => $booking->whereIn('status', BookingStatus::blocking())));
        });
    }
}
