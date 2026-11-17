<?php

namespace App\Actions\Plans;

use App\Enums\UserRole;
use App\Mail\Platform\BookingsLimitReachedMail;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

class NotifyBookingsLimit
{
    /**
     * Tell the administrators, once a month, that a portal reached the bookings
     * of its plan. Customers keep booking: this is the moment to talk about a
     * bigger plan, not a block.
     *
     * The month is written on the portal before anything is sent and the write
     * is conditional, so two bookings arriving together send one message.
     *
     * @return bool whether a message went out now
     */
    public function handle(Tenant $tenant): bool
    {
        $usage = $tenant->planUsage();

        if (! $usage->bookingsAtLimit()) {
            return false;
        }

        $month = CarbonImmutable::now($tenant->timezone)->format('Y-m');

        $claimed = Tenant::query()
            ->whereKey($tenant->getKey())
            ->where(fn ($query) => $query->whereNull('bookings_limit_notified_month')->orWhere('bookings_limit_notified_month', '!=', $month))
            ->update(['bookings_limit_notified_month' => $month]);

        if ($claimed === 0) {
            return false;
        }

        $administrators = User::query()->where('role', UserRole::Admin)->where('is_active', true)->get();
        $plan = $tenant->effectivePlan();

        foreach ($administrators as $administrator) {
            Mail::to($administrator->email)
                ->locale($administrator->locale)
                ->queue(new BookingsLimitReachedMail($tenant, $plan, $usage->bookingsThisMonth(), (int) $usage->bookingLimit()));
        }

        Activity::log('portal.bookings_limit_reached', $tenant, [
            'plan' => $plan?->name,
            'bookings' => $usage->bookingsThisMonth(),
            'limit' => $usage->bookingLimit(),
            'notified' => $administrators->count(),
        ], tenant: $tenant);

        return true;
    }
}
