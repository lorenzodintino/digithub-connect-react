<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tenants\SetLicenseExempt;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\LicenseService;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class LicenseController extends Controller
{
    /**
     * «Check now»: ask the License Manager for this portal, skipping the cache.
     *
     * It works with enforcement off too: it is the only call that mode allows.
     * A short pause per portal on top of the route's rate limit keeps a double
     * click from spending the 120 calls a minute the key has.
     */
    public function refresh(Tenant $tenant, LicenseService $license): RedirectResponse
    {
        Gate::authorize('manageBilling', $tenant);

        if (! Cache::add('license.refresh.'.$tenant->getKey(), true, 10)) {
            Toast::error(__('Wait a few seconds before checking again.'));

            return back();
        }

        $status = $license->refresh($tenant);

        if ($status->error !== null) {
            Toast::error(__('The License Manager did not give a valid answer (:code). The last known answer is kept.', ['code' => $status->error]));
        } else {
            Toast::success(__('License checked.'));
        }

        return back();
    }

    /**
     * Take a portal out of the licence, or put it back.
     */
    public function exempt(Request $request, Tenant $tenant, SetLicenseExempt $setLicenseExempt): RedirectResponse
    {
        Gate::authorize('manageBilling', $tenant);

        $exempt = $request->validate(['exempt' => ['required', 'boolean']])['exempt'];

        $setLicenseExempt->handle($tenant, (bool) $exempt);

        Toast::success($exempt ? __('This portal is now outside the license.') : __('This portal is under the license again.'));

        return back();
    }
}
