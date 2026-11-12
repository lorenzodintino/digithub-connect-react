<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LicenseService;
use App\Support\LicenseMessages;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What a member of a portal sees when its licence is refused, and the manager's
 * way to ask the License Manager again. Never reached by the platform administrator.
 */
class LicenseController extends Controller
{
    /**
     * The blocked screen: the reason, the end date and what can still be done.
     *
     * The manager also gets the portal id to pass on (that is what the
     * License Manager knows the portal by), the platform's contact and the
     * export of the data. An operator only learns that the portal's licence is
     * not valid and who to ask: no contacts of the platform.
     */
    public function blocked(Request $request, LicenseService $license): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $user->tenant;

        if ($tenant === null || ! $license->blocksBackOffice($tenant)) {
            return to_route('dashboard');
        }

        $status = $license->status($tenant);
        $isManager = $user->isManager();

        return Inertia::render('license/blocked', [
            'portal' => $tenant->name,
            'title' => LicenseMessages::title($status),
            'message' => $isManager
                ? LicenseMessages::explain($status, $tenant->timezone)
                : __('The license of :portal is not valid at the moment. Contact the person who manages the portal.', ['portal' => $tenant->name]),
            'isManager' => $isManager,
            'tenantId' => $isManager ? (string) $tenant->license_id : null,
            'supportEmail' => $isManager ? (config('services.license.support_email') ?: config('mail.from.address')) : null,
        ]);
    }

    /**
     * Ask the License Manager again, skipping the cache: the manager has just been told the portal was activated.
     */
    public function recheck(Request $request, LicenseService $license): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $user->tenant;

        abort_unless($user->isManager() && $tenant !== null && $license->governs($tenant), 404);

        if (! Cache::add('license.recheck.'.$tenant->getKey(), true, 10)) {
            Toast::error(__('Wait a few seconds before checking again.'));

            return back();
        }

        $license->refresh($tenant);
        Toast::success(__('License checked.'));

        return to_route('dashboard');
    }
}
