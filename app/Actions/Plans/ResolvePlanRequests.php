<?php

namespace App\Actions\Plans;

use App\Enums\PlanRequestStatus;
use App\Models\PlanRequest;
use App\Models\Tenant;

class ResolvePlanRequests
{
    /**
     * Close the open requests of a portal whose plan has reached what was asked.
     *
     * Called when the plan of a portal changes (by an administrator, or by the
     * License Manager): the manager is told it is done without anyone having to
     * remember to mark the request.
     *
     * @return int how many requests were closed
     */
    public function handle(Tenant $tenant): int
    {
        $plan = $tenant->effectivePlan();

        if ($plan === null) {
            return 0;
        }

        $closed = 0;

        PlanRequest::query()->where('tenant_id', $tenant->getKey())->open()->with('requestedPlan')->each(function (PlanRequest $request) use ($plan, &$closed): void {
            $asked = $request->requestedPlan;

            if ($asked === null || $plan->position < $asked->position) {
                return;
            }

            app(ChangePlanRequestStatus::class)->handle($request, PlanRequestStatus::Handled, null, null);
            $closed++;
        });

        return $closed;
    }
}
