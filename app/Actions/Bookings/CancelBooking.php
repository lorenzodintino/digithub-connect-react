<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Services\BookingNotifier;
use App\Support\Activity;

class CancelBooking
{
    public function __construct(
        private readonly BookingNotifier $notifier,
        private readonly NotifyWaitingList $waitingList,
    ) {}

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

        Activity::log('booking.cancelled', $booking, [
            'by' => $actor === null ? 'customer' : 'portal',
        ], actor: $actor);

        $this->notifier->cancelled($booking, $actor);

        // The slot is free again, and somebody asked to hear about it.
        $this->waitingList->handle($booking->service, $booking->starts_at);

        return $booking;
    }
}
