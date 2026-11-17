<?php

namespace App\Actions\Plans;

use App\Enums\PlanRequestStatus;
use App\Mail\Platform\PlanRequestUpdatedMail;
use App\Models\PlanRequest;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Support\Facades\Mail;

class ChangePlanRequestStatus
{
    /**
     * An administrator takes a request in charge, handles it or turns it down, and
     * the manager who made it is told, with the reason when there is one.
     *
     * Answered on its own (by the plan reaching the level asked for) there is no
     * administrator: `$actor` is null and the register says so.
     */
    public function handle(PlanRequest $request, PlanRequestStatus $status, ?string $message, ?User $actor): PlanRequest
    {
        $previous = $request->status;

        $request->forceFill([
            'status' => $status,
            'response' => $message,
            'handled_by' => $actor?->id,
            'status_changed_at' => now(),
        ])->save();

        Activity::log('plan.request_updated', $request, [
            'from' => $previous->value,
            'to' => $status->value,
            'automatic' => $actor === null,
        ], tenant: $request->tenant_id);

        $requester = $request->user;

        if ($requester !== null && $requester->is_active) {
            Mail::to($requester->email)->locale($requester->locale)->queue(new PlanRequestUpdatedMail($request));
        }

        return $request;
    }
}
