<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Activity;
use App\Support\BillingData;
use App\Support\LicenseManagerExport;
use App\Support\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class LicenseExportController extends Controller
{
    /**
     * Download the registration file of a portal for the License Manager.
     *
     * It is personal data leaving the platform, so it is refused when the
     * billing details are not complete (the file would not pass the License
     * Manager's own checks) or when the software code of this environment is
     * not set, and every download is written in the register, without values.
     */
    public function __invoke(Tenant $tenant): JsonResponse|RedirectResponse
    {
        Gate::authorize('manageBilling', $tenant);

        $profile = $tenant->billingProfile;
        $gaps = BillingData::gaps($profile);

        if ($profile === null || $gaps !== []) {
            Toast::error(__('Complete the billing details of this portal first: missing :items.', ['items' => implode(', ', $gaps)]));

            return back(fallback: route('admin.portals'));
        }

        if (blank(config('services.license.software'))) {
            Toast::error(__('The software code of the License Manager is not set (LICENSE_SOFTWARE_CODE in the .env file).'));

            return back(fallback: route('admin.portals'));
        }

        Activity::log('portal.license_json_exported', null, ['portal_id' => $tenant->getKey()], label: $tenant->name);

        return response()->json(
            LicenseManagerExport::payload($tenant, $profile),
            200,
            [
                'Content-Disposition' => 'attachment; filename="'.$tenant->slug.'-license-manager.json"',
                // Personal data: nothing in between keeps a copy.
                'Cache-Control' => 'no-store',
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
