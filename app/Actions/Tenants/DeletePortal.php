<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;
use App\Support\Activity;

class DeletePortal
{
    /**
     * Remove a portal and everything it owns: manager, operators, services,
     * availability and bookings, upcoming ones included. The administrator is
     * warned about those before confirming; nobody is notified.
     */
    public function handle(Tenant $portal): void
    {
        Activity::log('portal.deleted', $portal, [], $portal);

        $portal->delete();
    }
}
