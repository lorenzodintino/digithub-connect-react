<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * Every portal member has an agenda; what it contains is scoped elsewhere.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->belongsToTenant();
    }

    /**
     * A manager sees the whole portal agenda, an operator only their own.
     */
    public function view(User $user, Booking $booking): bool
    {
        if (! $user->canAccessTenant($booking->tenant_id)) {
            return false;
        }

        return $user->isAdmin() || $user->isManager() || $booking->user_id === $user->getKey();
    }

    /**
     * A manager cancels anything in the portal, an operator only their own.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        return $this->view($user, $booking) && $booking->isCancellable();
    }

    /**
     * Erasing the customer of a booking is a decision for the manager alone.
     *
     * An operator sees their own agenda, but answering a customer asking to be
     * forgotten is the portal's responsibility, not theirs.
     */
    public function anonymise(User $user, Booking $booking): bool
    {
        return $user->canAccessTenant($booking->tenant_id)
            && $user->isManager()
            && ! $booking->isAnonymised();
    }

    /**
     * Manual approval is given by the manager or by the assigned operator.
     */
    public function confirm(User $user, Booking $booking): bool
    {
        return $this->view($user, $booking) && $booking->awaitsConfirmation();
    }

    /**
     * Recording the outcome is open to whoever sees the booking, once it is over.
     */
    public function recordOutcome(User $user, Booking $booking): bool
    {
        return $this->view($user, $booking) && $booking->acceptsOutcome();
    }

    /**
     * Moving a booking follows the same rule as cancelling it.
     */
    public function reschedule(User $user, Booking $booking): bool
    {
        return $this->cancel($user, $booking);
    }
}
