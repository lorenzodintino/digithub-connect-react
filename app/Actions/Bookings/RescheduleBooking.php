<?php

namespace App\Actions\Bookings;

use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\User;
use App\Services\AgendaLock;
use App\Services\BookingNotifier;
use App\Services\SlotFinder;
use App\Support\Activity;
use Carbon\CarbonImmutable;

class RescheduleBooking
{
    public function __construct(
        private readonly BookingNotifier $notifier,
        private readonly NotifyWaitingList $waitingList,
    ) {}

    /**
     * Move a booking to a different slot.
     *
     * The booking is updated in place so the link already sent to the customer
     * keeps working; `original_starts_at` remembers where it started.
     *
     * The actor is null when the customer moves it from their public link.
     *
     * @throws SlotUnavailableException
     */
    public function handle(Booking $booking, CarbonImmutable $startsAt, ?User $actor = null): Booking
    {
        abort_unless($booking->isCancellable(), 403);

        // A seat on a class is not moved to another hour: the class is the
        // appointment. Giving it up and taking another is the honest way.
        abort_if($booking->service->isGroup(), 422);

        $startsAt = $startsAt->setTimezone('UTC');

        // Held on to before the move: afterwards the booking only knows its new hour.
        $freedHour = $booking->starts_at;

        $moved = AgendaLock::forOperator($booking->user_id, function () use ($booking, $startsAt): Booking {
            $finder = (new SlotFinder($booking->service, $booking->operator))->ignoring($booking);

            if (! $finder->canBookAt($startsAt)) {
                throw SlotUnavailableException::forSlot();
            }

            [$blockedStart, $blockedEnd] = $finder->blockedWindowFor($startsAt);

            $booking->update([
                'original_starts_at' => $booking->original_starts_at ?? $booking->starts_at,
                'starts_at' => $startsAt,
                'ends_at' => $finder->endsAtFor($startsAt),
                'blocked_starts_at' => $blockedStart,
                'blocked_ends_at' => $blockedEnd,
                'rescheduled_at' => now(),
                // A new time deserves a new reminder, even if the old one went out.
                'reminder_sent_at' => null,
                // Calendars only replace an event when the sequence has grown.
                'ics_sequence' => $booking->ics_sequence + 1,
            ]);

            return $booking->refresh();
        });

        Activity::log('booking.rescheduled', $moved, [
            'from' => $freedHour->toIso8601String(),
            'to' => $moved->starts_at->toIso8601String(),
            'by' => $actor === null ? 'customer' : 'portal',
        ], actor: $actor);

        $this->notifier->rescheduled($moved, $actor);

        // The hour it left is free again for whoever was waiting on it.
        $this->waitingList->handle($moved->service, $freedHour);

        return $moved;
    }
}
