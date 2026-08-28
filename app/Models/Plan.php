<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A commercial plan a portal can be put on.
 *
 * The limits are recorded but **not enforced anywhere yet**: this is the shape
 * we want to agree on before writing the checks that will read it.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $price_cents
 * @property string $currency
 * @property int|null $max_services
 * @property int|null $max_operators
 * @property int|null $max_bookings_per_month
 * @property bool $allows_custom_smtp
 * @property bool $is_active
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'description', 'price_cents', 'currency',
    'max_services', 'max_operators', 'max_bookings_per_month', 'allows_custom_smtp',
    'is_active', 'position',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allows_custom_smtp' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The portals sitting on this plan.
     *
     * @return HasMany<Tenant, $this>
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * Scope the query to the plans that can still be sold.
     *
     * @param  Builder<Plan>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The formatted monthly price, or null when the plan carries none.
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
     * A limit as a readable value, with "no limit" spelled out.
     */
    public function limitLabel(?int $limit): string
    {
        return $limit === null ? __('Unlimited') : (string) $limit;
    }
}
