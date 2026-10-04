<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\AgendaLock;
use App\Services\BookingNotifier;
use App\Support\Activity;

class ConfirmBooking
{
    public function __construct(private readonly BookingNotifier $notifier) {}

    /**
     * Approve a booking that was waiting for manual confirmation.
     */
    public function handle(Booking $booking): Booking
    {
        abort_unless($booking->awaitsConfirmation(), 403);

        AgendaLock::forTenant($booking->tenant_id, function () use ($booking): void {
            $booking->refresh();
            abort_unless($booking->awaitsConfirmation(), 403);
            $booking->update([
                'status' => BookingStatus::Confirmed,
                'confirmed_at' => now(),
            ]);
        });

        $booking->refresh();

        Activity::log('booking.confirmed', $booking);

        $this->notifier->confirmed($booking);

        return $booking;
    }
}
