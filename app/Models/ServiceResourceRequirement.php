<?php

namespace App\Models;

use Database\Factories\ServiceResourceRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * All requirements are needed; one resource is chosen inside each requirement.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $service_id
 * @property string $name
 * @property int $position
 */
#[Fillable(['tenant_id', 'service_id', 'name', 'position'])]
class ServiceResourceRequirement extends Model
{
    /** @use HasFactory<ServiceResourceRequirementFactory> */
    use HasFactory;

    public const LIMIT = 10;

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsToMany<\App\Models\Resource, $this> */
    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'resource_service_requirement');
    }
}
