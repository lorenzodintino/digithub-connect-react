<?php

namespace App\Actions\Plans;

use App\Enums\PlanRequestStatus;
use App\Enums\UserRole;
use App\Mail\Platform\PlanUpgradeRequestMail;
use App\Models\Plan;
use App\Models\PlanRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Support\Facades\Mail;

class RequestPlanUpgrade
{
    /**
     * Why a manager asks, as the form offers it: a key and the sentence the
     * administrator reads in the email.
     *
     * @return array<string, string>
     */
    public static function reasons(): array
    {
        return [
            'services' => __('the services limit was reached'),
            'operators' => __('the operators limit was reached'),
            'bookings' => __('the monthly bookings are close to the limit'),
            'resources' => __('shared resources are not in the plan'),
            'groups' => __('group services are not in the plan'),
            'packages' => __('service packages are not in the plan, or the limit was reached'),
            'waiting_list' => __('the waiting list is not in the plan'),
            'embed' => __('embedding in a website is not in the plan'),
            'calendar' => __('the add to calendar buttons are not in the plan'),
            'smtp' => __('the own email server is not in the plan'),
            'other' => __('another reason'),
        ];
    }

    /**
     * Tell every active administrator and leave a line in the register.
     *
     * The register keeps the plans and the reason, never the free note: it
     * stays a list of facts, not a copy of what people write.
     *
     * The request is kept, with its status, for the administrators to work through
     * and for the manager to follow.
     */
    public function handle(Tenant $tenant, User $manager, Plan $requested, ?string $reason, ?string $note): PlanRequest
    {
        $current = $tenant->effectivePlan();
        $reasons = self::reasons();

        $request = PlanRequest::create([
            'tenant_id' => $tenant->id,
            'user_id' => $manager->id,
            'from_plan' => $current?->name,
            'requested_plan_id' => $requested->id,
            'requested_plan' => $requested->name,
            'reason' => $reason,
            'note' => $note,
            'status' => PlanRequestStatus::New,
            'response' => null,
            'handled_by' => null,
            'status_changed_at' => null,
        ]);

        $administrators = User::query()->where('role', UserRole::Admin)->where('is_active', true)->get();

        foreach ($administrators as $administrator) {
            Mail::to($administrator->email)
                ->locale($administrator->locale)
                ->queue(new PlanUpgradeRequestMail($tenant, $manager, $current, $requested, $reason === null ? null : ($reasons[$reason] ?? null), $note));
        }

        Activity::log('plan.upgrade_requested', $request, [
            'from' => $current?->name,
            'to' => $requested->name,
            'reason' => $reason,
            'notified' => $administrators->count(),
        ], tenant: $tenant);

        return $request;
    }
}
