<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Services\BookingNotifier;

class CancelBooking
{
    public function __construct(private readonly BookingNotifier $notifier) {}

    /**
     * Cancel a booking and free its slot.
     *
     * The actor is null when the customer cancels through their public link.
     */
    public function handle(Booking $booking, ?User $actor = null, ?string $reason = null): Booking
    {
        abort_unless($booking->isCancellable(), 403);

        $booking->update([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by_id' => $actor?->getKey(),
            'cancellation_reason' => $reason ?: null,
            // Same reason as a reschedule: the calendar must see a newer version.
            'ics_sequence' => $booking->ics_sequence + 1,
        ]);

        $booking->refresh();

        $this->notifier->cancelled($booking, $actor);

        return $booking;
    }
}
