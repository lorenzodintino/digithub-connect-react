<?php

namespace App\Http\Controllers;

use App\Actions\Plans\RequestPlanUpgrade;
use App\Enums\PlanRequestStatus;
use App\Http\Requests\PlanUpgradeRequest;
use App\Models\PlanRequest;
use App\Models\User;
use App\Support\Activity;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlanUpgradeRequestController extends Controller
{
    /**
     * The manager asks the platform for a bigger plan.
     */
    public function store(PlanUpgradeRequest $request, RequestPlanUpgrade $upgrade): RedirectResponse
    {
        /** @var User $manager */
        $manager = $request->user();

        $upgrade->handle($manager->tenant, $manager, $request->plan(), $request->validated('reason'), $request->validated('note'));

        Toast::success(__('Request sent. The platform administrator will get back to you.'));

        return back();
    }

    /**
     * The manager withdraws a request that nobody has answered yet.
     */
    public function cancel(Request $request, PlanRequest $planRequest): RedirectResponse
    {
        /** @var User $manager */
        $manager = $request->user();

        abort_unless($manager->isManager() && $planRequest->tenant_id === $manager->tenant_id, 403);
        abort_unless($planRequest->status->isOpen(), 404);

        $planRequest->forceFill(['status' => PlanRequestStatus::Cancelled, 'status_changed_at' => now()])->save();

        Activity::log('plan.request_updated', $planRequest, ['from' => 'open', 'to' => PlanRequestStatus::Cancelled->value, 'automatic' => false], tenant: $planRequest->tenant_id);

        Toast::success(__('Request withdrawn.'));

        return back();
    }
}
