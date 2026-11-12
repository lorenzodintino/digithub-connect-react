<?php

namespace App\Actions\Resources;

use App\Models\Resource;
use App\Models\Tenant;
use App\Services\AgendaLock;
use App\Support\Activity;
use Illuminate\Validation\ValidationException;

class SaveResource
{
    /**
     * Create a resource of the portal, or update one of its own.
     *
     * Deactivating is checked under the portal lock: a resource with upcoming
     * bookings stays active until they are cancelled or moved, or the agenda
     * would promise rooms and machines nobody can use.
     *
     * @param  array<string, mixed>  $attributes  validated by ResourceRequest
     */
    public function handle(Tenant $tenant, ?Resource $resource, array $attributes): Resource
    {
        if ($resource === null) {
            $resource = $tenant->resources()->create($attributes);

            Activity::log('resource.created', $resource);

            return $resource;
        }

        return AgendaLock::forTenant($resource->tenant_id, function () use ($resource, $attributes): Resource {
            $resource->refresh();

            if (! (bool) $attributes['is_active'] && $resource->hasUpcomingAllocations()) {
                throw ValidationException::withMessages([
                    'is_active' => __('This resource has upcoming bookings. Cancel or move them before deactivating it.'),
                ]);
            }

            $resource->update($attributes);

            Activity::log('resource.updated', $resource);

            return $resource;
        });
    }
}
