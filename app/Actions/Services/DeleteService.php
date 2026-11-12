<?php

namespace App\Actions\Services;

use App\Models\Service;
use App\Services\AgendaLock;
use App\Support\Activity;
use Illuminate\Validation\ValidationException;

class DeleteService
{
    /**
     * Remove a service from the portal and its public page.
     *
     * Refused while a package is made with it, or while bookings are still to
     * come: deleting would take them along.
     */
    public function handle(Service $service): void
    {
        AgendaLock::forTenant($service->tenant_id, function () use ($service): void {
            $service->refresh();

            if ($service->usedInPackages()->exists()) {
                throw ValidationException::withMessages([
                    'service' => __('Remove this service from its packages before deleting it.'),
                ]);
            }

            $upcoming = $service->upcomingBookingsCount();

            if ($upcoming > 0) {
                throw ValidationException::withMessages([
                    'service' => trans_choice('This service still has one upcoming booking. Cancel it first.|This service still has :count upcoming bookings. Cancel them first.', $upcoming),
                ]);
            }

            Activity::log('service.deleted', $service);

            $service->delete();
        });
    }
}
