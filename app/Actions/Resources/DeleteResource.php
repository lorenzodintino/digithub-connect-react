<?php

namespace App\Actions\Resources;

use App\Models\Resource;
use App\Services\AgendaLock;
use App\Support\Activity;
use Illuminate\Validation\ValidationException;

class DeleteResource
{
    /**
     * Delete a resource nothing depends on any more, with its unavailability.
     *
     * Refused while a service still requires it or an upcoming booking holds
     * it. Past allocations keep their snapshot of its name.
     */
    public function handle(Resource $resource): void
    {
        AgendaLock::forTenant($resource->tenant_id, function () use ($resource): void {
            if ($resource->requirements()->exists() || $resource->hasUpcomingAllocations()) {
                throw ValidationException::withMessages([
                    'resource' => __('Remove this resource from its services and cancel or move its upcoming bookings before deleting it.'),
                ]);
            }

            Activity::log('resource.deleted', $resource);

            $resource->delete();
        });
    }
}
