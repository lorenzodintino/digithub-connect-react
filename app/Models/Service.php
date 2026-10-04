<?php

namespace App\Models;

use App\Enums\LocationType;
use App\Services\PackagePlan;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $duration_minutes
 * @property int $capacity
 * @property bool $is_package
 * @property bool $use_component_prices
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
    'tenant_id', 'name', 'slug', 'description', 'duration_minutes', 'capacity', 'buffer_before_minutes',
    'buffer_after_minutes', 'slot_interval_minutes', 'min_notice_minutes', 'max_advance_days',
    'cancellation_notice_hours',
    'price_cents', 'currency', 'color', 'location_type', 'location_value',
    'requires_confirmation', 'is_active', 'position', 'is_package', 'use_component_prices',
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
            'is_package' => 'boolean',
            'use_component_prices' => 'boolean',
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

    /** @return HasMany<ServiceResourceRequirement, $this> */
    public function resourceRequirements(): HasMany
    {
        return $this->hasMany(ServiceResourceRequirement::class)->orderBy('position');
    }

    /** @return HasMany<ServicePackageItem, $this> */
    public function packageItems(): HasMany
    {
        return $this->hasMany(ServicePackageItem::class, 'package_id')->orderBy('position');
    }

    /** @return HasMany<ServicePackageItem, $this> */
    public function usedInPackages(): HasMany
    {
        return $this->hasMany(ServicePackageItem::class, 'service_id');
    }

    /** @return HasMany<BookingPhase, $this> */
    public function phases(): HasMany
    {
        return $this->hasMany(BookingPhase::class);
    }

    public function bookingConfiguration(?Booking $booking = null): self
    {
        return $this->is_package ? (new PackagePlan($this, $booking))->configuration() : $this;
    }

    /** @return Collection<int, ServiceQuestion> */
    public function bookingQuestions(): Collection
    {
        $questions = $this->questions()->get()->toBase();
        if ($this->is_package) {
            $this->loadMissing('packageItems.service.questions');
            foreach ($this->packageItems as $item) {
                foreach ($item->service->questions as $question) {
                    $copy = clone $question;
                    $copy->label = $item->service->name.' · '.$question->label;
                    $questions->push($copy);
                }
            }
        }

        return $questions;
    }

    public function operatorCanDeliver(User $operator): bool
    {
        if (! $this->operators()->whereKey($operator->id)->exists()) {
            return false;
        }
        if ($this->is_package) {
            $this->loadMissing('packageItems.service.operators');
            foreach ($this->packageItems as $item) {
                if (! $item->service->operators->contains('id', $operator->id)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * The runs of this service, for the ones booked by more than one person.
     *
     * @return HasMany<ServiceSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(ServiceSession::class);
    }

    /**
     * Determine whether several people share the same appointment.
     */
    public function isGroup(): bool
    {
        return $this->capacity > 1;
    }

    /**
     * Determine whether that many seats left is few enough to say so.
     *
     * A quarter of the room, at least one: on a class of eight the warning
     * starts at two seats, which is the point where a visitor deciding today
     * rather than tomorrow actually changes the outcome. Told earlier it is
     * noise — a service showing urgency while half the seats are free teaches
     * the visitor to ignore it.
     */
    public function seatsRunningLow(int $left): bool
    {
        return $left > 0 && $left <= max(1, intdiv($this->capacity, 4));
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
        return Booking::query()->blocking()->upcoming()->where(fn (Builder $query) => $query
            ->where('service_id', $this->id)->orWhereHas('phases', fn (Builder $phases) => $phases->where('service_id', $this->id)))->count();
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
