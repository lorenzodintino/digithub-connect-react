<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;
use App\Models\TenantBillingProfile;
use App\Support\Activity;
use App\Support\BillingData;

class SaveBillingProfile
{
    /**
     * Store who pays for a portal, creating the profile the first time.
     *
     * The register gets the names of the fields that changed, never the values:
     * the profile is personal data and the register must not become a copy of it.
     * The line belongs to the platform, not to the portal: the register of the
     * portal is read by its manager, who must not see the billing profile.
     *
     * @param  array<string, mixed>  $billing  normalised and validated by PortalRequest
     */
    public function handle(Tenant $portal, array $billing): TenantBillingProfile
    {
        $profile = $portal->billingProfile;
        $columns = BillingData::columns($billing);
        $changed = BillingData::changedFields($profile, $columns);

        $profile = $portal->billingProfile()->updateOrCreate([], $columns);

        if ($changed !== []) {
            Activity::log('portal.billing_updated', null, ['portal_id' => $portal->getKey(), 'fields' => $changed], label: $portal->name);
        }

        return $profile;
    }
}
