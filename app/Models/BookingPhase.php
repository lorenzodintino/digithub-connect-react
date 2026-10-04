<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $booking_id
 * @property int|null $service_id
 * @property string $service_name
 * @property int $position
 * @property int $pause_after_minutes
 * @property int $buffer_before_minutes
 * @property int $buffer_after_minutes
 * @property int|null $price_cents
 * @property string $currency
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 */
#[Fillable([
    'tenant_id', 'booking_id', 'service_id', 'service_name', 'position',
    'pause_after_minutes', 'buffer_before_minutes', 'buffer_after_minutes',
    'price_cents', 'currency', 'starts_at', 'ends_at',
])]

class BookingPhase extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return HasMany<ResourceAllocation, $this> */
    public function resourceAllocations(): HasMany
    {
        return $this->hasMany(ResourceAllocation::class);
    }
}
