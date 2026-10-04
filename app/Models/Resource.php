<?php

namespace App\Models;

use App\Enums\ResourceType;
use Carbon\CarbonImmutable;
use Database\Factories\ResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property ResourceType $type
 * @property string|null $description
 * @property string|null $notes
 * @property int $preparation_minutes
 * @property int $cleanup_minutes
 * @property bool $is_active
 */
#[Fillable(['tenant_id', 'name', 'type', 'description', 'notes', 'preparation_minutes', 'cleanup_minutes', 'is_active'])]
class Resource extends Model
{
    /** @use HasFactory<ResourceFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ResourceType::class,
            'is_active' => 'boolean',
            'preparation_minutes' => 'integer',
            'cleanup_minutes' => 'integer',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<ResourceBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(ResourceBlock::class);
    }

    /** @return HasMany<ResourceAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ResourceAllocation::class);
    }

    public function hasUpcomingAllocations(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): bool
    {
        return $this->allocations()->blocking()->where('ends_at', '>', now())
            ->when($from, fn ($query) => $query->where('ends_at', '>', $from))
            ->when($to, fn ($query) => $query->where('starts_at', '<', $to))->exists();
    }

    /** @return BelongsToMany<ServiceResourceRequirement, $this> */
    public function requirements(): BelongsToMany
    {
        return $this->belongsToMany(ServiceResourceRequirement::class, 'resource_service_requirement');
    }
}
