<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tenants\SetPaidUntil;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LicenseService;
use App\Support\Toast;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PaidUntilController extends Controller
{
    /**
     * Record the last day the portal has paid for; empty means it never expires.
     */
    public function __invoke(Request $request, Tenant $tenant, SetPaidUntil $setPaidUntil, LicenseService $license): RedirectResponse
    {
        Gate::authorize('update', $tenant);

        // The dates of a portal the License Manager governs are its own: a second one would only disagree.
        if ($license->governs($tenant)) {
            throw ValidationException::withMessages(['paid_until' => __('The paid period of this portal is managed by the License Manager.')]);
        }

        $paidUntil = $request->validate(
            ['paid_until' => ['nullable', 'date_format:Y-m-d']],
            attributes: ['paid_until' => __('paid until')],
        )['paid_until'] ?? null;

        /** @var User $admin */
        $admin = $request->user();

        $setPaidUntil->handle($tenant, $paidUntil === null ? null : CarbonImmutable::parse($paidUntil), $admin);

        Toast::success(__('Paid period saved.'));

        return back();
    }
}
