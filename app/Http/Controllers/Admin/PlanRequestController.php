<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Plans\ChangePlanRequestStatus;
use App\Actions\Plans\RequestPlanUpgrade;
use App\Enums\PlanRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlanRequestStatusRequest;
use App\Models\PlanRequest;
use App\Models\User;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanRequestController extends Controller
{
    /**
     * The requests of the managers for a bigger plan: the ones waiting first, then the answered.
     */
    public function index(Request $request): Response
    {
        /** @var User $admin */
        $admin = $request->user();
        $reasons = RequestPlanUpgrade::reasons();

        $requests = PlanRequest::query()
            ->with(['tenant', 'user'])
            ->orderByRaw("case when status in ('new', 'in_progress') then 0 else 1 end")
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (PlanRequest $planRequest) => [
                'id' => $planRequest->id,
                'portal' => $planRequest->tenant->name,
                'portal_url' => route('admin.portals.show', $planRequest->tenant),
                'requester' => $planRequest->user?->name,
                'requester_email' => $planRequest->user?->email,
                'from' => $planRequest->from_plan,
                'to' => $planRequest->requested_plan,
                'reason' => $planRequest->reason === null ? null : ($reasons[$planRequest->reason] ?? null),
                'note' => $planRequest->note,
                'status' => $planRequest->status->value,
                'label' => $planRequest->status->label(),
                'color' => $planRequest->status->color(),
                'open' => $planRequest->status->isOpen(),
                'response' => $planRequest->response,
                'date' => $planRequest->created_at->setTimezone($admin->timezone)->translatedFormat('d M Y, H:i'),
            ])
            ->values()
            ->all();

        return Inertia::render('admin/plan-requests', ['requests' => $requests]);
    }

    public function update(PlanRequestStatusRequest $request, PlanRequest $planRequest, ChangePlanRequestStatus $change): RedirectResponse
    {
        abort_unless($planRequest->status->isOpen(), 404);

        /** @var User $admin */
        $admin = $request->user();

        $change->handle($planRequest, PlanRequestStatus::from($request->validated('status')), $request->validated('response'), $admin);

        Toast::success(__('Request updated.'));

        return back();
    }
}
