<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;
use App\Services\LicenseService;
use App\Support\Activity;

class SetLicenseExempt
{
    public function __construct(private readonly LicenseService $license) {}

    /**
     * Mark a portal as outside the licence (demo and test portals not registered
     * in the License Manager), or bring it back under it.
     *
     * The register line belongs to the platform, not to the portal: its manager
     * does not read it.
     */
    public function handle(Tenant $tenant, bool $exempt): Tenant
    {
        if ($tenant->license_exempt === $exempt) {
            return $tenant;
        }

        $tenant->update(['license_exempt' => $exempt]);
        $this->license->forget($tenant);

        Activity::log('portal.license_exempt', null, ['portal_id' => $tenant->getKey(), 'exempt' => $exempt], label: $tenant->name);

        return $tenant;
    }
}
