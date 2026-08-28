<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;

class RecordOutcome
{
    /**
     * Write down how an appointment went, once it is over.
     *
     * No lock and no email: the slot is in the past, so nobody is competing for
     * it and the customer has nothing left to be told.
     */
    public function handle(Booking $booking, BookingStatus $outcome): Booking
    {
        abort_unless($booking->acceptsOutcome(), 403);
        abort_unless(in_array($outcome, BookingStatus::outcomes(), true), 422);

        $booking->update(['status' => $outcome]);

        return $booking->refresh();
    }
}
