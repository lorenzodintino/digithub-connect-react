<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Plan;
use App\Models\Resource;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaitingListEntry;
use Carbon\CarbonImmutable;

/**
 * How much of its plan a portal is using, and what that allows.
 *
 * Services and operators are hard limits: past them nothing new is created.
 * Bookings are a threshold: the customers of a portal keep booking, and the
 * manager and the administrator are told so they can talk about a bigger plan.
 * A portal without a plan has no limit at all.
 */
class PlanUsage
{
    /**
     * Share of the monthly bookings at which the manager is first warned.
     */
    public const BOOKINGS_WARNING_SHARE = 0.8;

    public function __construct(private readonly Tenant $tenant) {}

    public function services(): int
    {
        return Service::query()->where('tenant_id', $this->tenant->getKey())->count();
    }

    /**
     * Operator accounts; the manager is not counted, even when bookable.
     */
    public function operators(): int
    {
        return User::query()->where('tenant_id', $this->tenant->getKey())->where('role', UserRole::Default)->count();
    }

    /**
     * Bookings placed since the first day of this month, in the portal's timezone.
     *
     * Counted when placed, cancelled ones included: a booking that was made
     * and later cancelled still went through the portal.
     */
    public function bookingsThisMonth(): int
    {
        $monthStart = CarbonImmutable::now($this->tenant->timezone)->startOfMonth()->utc();

        return Booking::query()->where('tenant_id', $this->tenant->getKey())->where('created_at', '>=', $monthStart)->count();
    }

    public function serviceLimit(): ?int
    {
        return $this->tenant->effectivePlan()?->max_services;
    }

    public function operatorLimit(): ?int
    {
        return $this->tenant->effectivePlan()?->max_operators;
    }

    public function bookingLimit(): ?int
    {
        return $this->tenant->effectivePlan()?->max_bookings_per_month;
    }

    /**
     * Service packages of the portal (a package also counts as a service above).
     */
    public function packages(): int
    {
        return Service::query()->where('tenant_id', $this->tenant->getKey())->where('is_package', true)->count();
    }

    public function packageLimit(): ?int
    {
        return $this->tenant->effectivePlan()?->max_packages;
    }

    public function canAddPackage(): bool
    {
        return $this->packageLimit() === null || $this->packages() < $this->packageLimit();
    }

    public function waitingListLimit(): ?int
    {
        return $this->tenant->effectivePlan()?->max_waiting_list;
    }

    /**
     * Whether the waiting list of a service has no place left for one more person.
     */
    public function waitingListFull(Service $service): bool
    {
        return $this->waitingListLimit() !== null
            && WaitingListEntry::query()->where('tenant_id', $this->tenant->getKey())->where('service_id', $service->getKey())->count() >= $this->waitingListLimit();
    }

    /**
     * What the portal has beyond what its plan now includes, after a move to a smaller plan.
     *
     * Nothing is deleted or hidden: what exists keeps working and only new creations are refused
     * (see App\Models\Plan). This only says it, so the manager is not left to find out from an error.
     * A limit that is exceeded reads «:used of :limit»; a feature the plan no longer has but whose
     * data is still there reads as no longer included.
     *
     * @return list<string>
     */
    public function excessLabels(): array
    {
        $plan = $this->tenant->effectivePlan();

        if ($plan === null) {
            return [];
        }

        $over = fn (string $label, int $used, ?int $limit): ?string => $limit !== null && $used > $limit
            ? __(':label: :used of :limit', ['label' => $label, 'used' => $used, 'limit' => $limit])
            : null;
        $lost = fn (string $feature, int $existing): ?string => ! $plan->allows($feature) && $existing > 0
            ? __(':label: no longer included, what exists keeps working', ['label' => Plan::featureLabels()[$feature]])
            : null;

        $labels = Plan::featureLabels();
        $packages = $this->packages();

        // The waiting list is counted per service: how many services hold more people than a plan lets.
        $crowded = $plan->allows('waiting_list') && $plan->max_waiting_list !== null
            ? WaitingListEntry::query()->where('tenant_id', $this->tenant->getKey())->select('service_id')->groupBy('service_id')->havingRaw('count(*) > ?', [$plan->max_waiting_list])->get()->count()
            : 0;

        return array_values(array_filter([
            $over(__('Services'), $this->services(), $plan->max_services),
            $over(__('Operators'), $this->operators(), $plan->max_operators),
            $plan->allows('packages') ? $over($labels['packages'], $packages, $plan->max_packages) : $lost('packages', $packages),
            $crowded > 0 ? trans_choice('Waiting list: one service has more people than the plan includes (:limit)|Waiting list: :count services have more people than the plan includes (:limit)', $crowded, ['limit' => (int) $plan->max_waiting_list]) : null,
            $lost('waiting_list', WaitingListEntry::query()->where('tenant_id', $this->tenant->getKey())->count()),
            $lost('resources', Resource::query()->where('tenant_id', $this->tenant->getKey())->count()),
            $lost('group_services', Service::query()->where('tenant_id', $this->tenant->getKey())->where('is_package', false)->where('capacity', '>', 1)->count()),
        ]));
    }

    public function canAddService(): bool
    {
        return $this->serviceLimit() === null || $this->services() < $this->serviceLimit();
    }

    public function canAddOperator(): bool
    {
        return $this->operatorLimit() === null || $this->operators() < $this->operatorLimit();
    }

    /**
     * Whether this month's bookings are close to the plan's threshold or past it.
     */
    public function bookingsNearLimit(): bool
    {
        return $this->bookingLimit() !== null && $this->bookingsThisMonth() >= $this->bookingLimit() * self::BOOKINGS_WARNING_SHARE;
    }

    public function bookingsAtLimit(): bool
    {
        return $this->bookingLimit() !== null && $this->bookingsThisMonth() >= $this->bookingLimit();
    }
}
