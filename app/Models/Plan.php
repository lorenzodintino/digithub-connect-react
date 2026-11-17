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
 * What a plan includes is two things: numeric limits (a null limit means no
 * limit) and a set of features switched on or off. Both are read through
 * App\Services\PlanUsage and Tenant::planAllows(). A feature the plan does not
 * list counts as not included: a new feature can never open by mistake for a
 * plan that was not sold it.
 *
 * The table is a copy: the License Manager is meant to be the only place where
 * plans are defined (see docs/prompt-3-catalogo-piani.md), and `code` is the
 * stable key it will use, while the name is free to change.
 *
 * @property int $id
 * @property string|null $code
 * @property string $name
 * @property string|null $description
 * @property int|null $price_cents
 * @property string $currency
 * @property int|null $max_services
 * @property int|null $max_operators
 * @property int|null $max_bookings_per_month
 * @property int|null $max_waiting_list
 * @property int|null $max_packages
 * @property array<string, bool>|null $features
 * @property bool $is_active
 * @property int $position
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'code', 'name', 'description', 'price_cents', 'currency',
    'max_services', 'max_operators', 'max_bookings_per_month', 'max_waiting_list', 'max_packages',
    'features', 'is_active', 'position', 'synced_at',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * Every feature a plan can include, in the order they are listed.
     *
     * @var list<string>
     */
    public const FEATURES = ['resources', 'waiting_list', 'packages', 'group_services', 'calendar_buttons', 'embed', 'custom_smtp'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * A plan that includes nothing: no limit above zero, no feature. It is what a portal the
     * License Manager governs gets when its licence names no plan that can be applied, so a
     * missing plan never means «no limit». Never saved.
     */
    public static function nothing(): self
    {
        return new self([
            'name' => __('No plan'),
            'currency' => 'EUR',
            'max_services' => 0,
            'max_operators' => 0,
            'max_bookings_per_month' => 0,
            'max_waiting_list' => 0,
            'max_packages' => 0,
            'features' => array_fill_keys(self::FEATURES, false),
            'is_active' => false,
            'position' => 0,
        ]);
    }

    /**
     * The name of each feature as people read it.
     *
     * @return array<string, string>
     */
    public static function featureLabels(): array
    {
        return [
            'resources' => __('Shared resources'),
            'waiting_list' => __('Waiting list'),
            'packages' => __('Service packages'),
            'group_services' => __('Group services'),
            'calendar_buttons' => __('Add to calendar buttons'),
            'embed' => __('Embedding in your website'),
            'custom_smtp' => __('Own mail server'),
        ];
    }

    /**
     * Whether the plan includes a feature; one it does not list is not included.
     */
    public function allows(string $feature): bool
    {
        return (bool) ($this->features[$feature] ?? false);
    }

    /**
     * Every feature with its answer, in the usual order.
     *
     * @return array<string, bool>
     */
    public function featureMap(): array
    {
        return collect(self::FEATURES)->mapWithKeys(fn (string $feature) => [$feature => $this->allows($feature)])->all();
    }

    /**
     * The labels of what the plan includes.
     *
     * @return list<string>
     */
    public function includedLabels(): array
    {
        $labels = self::featureLabels();

        return collect($this->featureMap())->filter()->keys()->map(fn (string $feature) => $labels[$feature])->values()->all();
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
