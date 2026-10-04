<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $package_id
 * @property int $service_id
 * @property int $position
 * @property int $pause_after_minutes
 */
#[Fillable(['tenant_id', 'package_id', 'service_id', 'position', 'pause_after_minutes'])]
class ServicePackageItem extends Model
{
    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'package_id');
    }
}
