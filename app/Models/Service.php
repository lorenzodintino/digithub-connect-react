<?php

namespace App\Models;

use App\Enums\LocationType;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $duration_minutes
 * @property int $buffer_before_minutes
 * @property int $buffer_after_minutes
 * @property int $slot_interval_minutes
 * @property int $min_notice_minutes
 * @property int $max_advance_days
 * @property int $cancellation_notice_hours
 * @property int|null $price_cents
 * @property string $currency
 * @property string|null $color
 * @property LocationType $location_type
 * @property string|null $location_value
 * @property bool $requires_confirmation
 * @property bool $is_active
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id', 'name', 'slug', 'description', 'duration_minutes', 'buffer_before_minutes',
    'buffer_after_minutes', 'slot_interval_minutes', 'min_notice_minutes', 'max_advance_days',
    'cancellation_notice_hours',
    'price_cents', 'currency', 'color', 'location_type', 'location_value',
    'requires_confirmation', 'is_active', 'position',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'location_type' => LocationType::class,
            'requires_confirmation' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Resolve the service by its portal-scoped slug.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The portal this service belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The staff members that can be booked for this service.
     *
     * @return BelongsToMany<User, $this>
     */
    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * The extra questions this service asks at booking time.
     *
     * @return HasMany<ServiceQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(ServiceQuestion::class)->orderBy('position');
    }

    /**
     * The bookings placed for this service.
     *
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Scope the query to active services only.
     *
     * @param  Builder<Service>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope the query to the services of the given portal.
     *
     * @param  Builder<Service>  $query
     */
    public function scopeForTenant(Builder $query, Tenant|int $tenant): void
    {
        $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->getKey() : $tenant);
    }

    /**
     * The color used for this service, falling back to the portal brand color.
     */
    public function displayColor(): string
    {
        return $this->color ?? $this->tenant->brand_color;
    }

    /**
     * How many upcoming bookings would be destroyed along with this record.
     */
    public function upcomingBookingsCount(): int
    {
        return $this->bookings()->blocking()->upcoming()->count();
    }

    /**
     * The total minutes a booking of this service occupies, buffers included.
     */
    public function totalBlockMinutes(): int
    {
        return $this->buffer_before_minutes + $this->duration_minutes + $this->buffer_after_minutes;
    }

    /**
     * The formatted price, or null when the service is free.
     */
    public function formattedPrice(): ?string
    {
        if ($this->price_cents === null) {
            return null;
        }

        return Str::of(number_format($this->price_cents / 100, 2, ',', '.'))
            ->prepend($this->currency === 'EUR' ? '€ ' : $this->currency.' ')
            ->value();
    }

    /**
     * The human readable duration of this service.
     */
    public function formattedDuration(): string
    {
        $hours = intdiv($this->duration_minutes, 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours === 0) {
            return __(':count min', ['count' => $minutes]);
        }

        return $minutes === 0
            ? __(':count h', ['count' => $hours])
            : __(':hours h :minutes min', ['hours' => $hours, 'minutes' => $minutes]);
    }

    /**
     * Build a slug unique within the given portal.
     */
    public static function uniqueSlug(string $name, int $tenantId, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: Str::random(8);
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
