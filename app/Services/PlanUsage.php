<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
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
        return $this->tenant->plan?->max_services;
    }

    public function operatorLimit(): ?int
    {
        return $this->tenant->plan?->max_operators;
    }

    public function bookingLimit(): ?int
    {
        return $this->tenant->plan?->max_bookings_per_month;
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
