<?php

namespace App\Actions\Availability;

use App\Exceptions\PeriodHasBookingsException;
use App\Models\AvailabilityException;
use App\Models\Booking;
use App\Models\User;
use App\Services\AgendaLock;
use Carbon\CarbonImmutable;

class SetDateOverride
{
    /**
     * Close a single day, or replace its hours with a custom window.
     *
     * Like blocking time, this takes availability away, so it runs under the
     * agenda lock and refuses to strand bookings that are already there.
     *
     * @param  string  $date  A date in the portal timezone, as Y-m-d.
     * @param  string|null  $startsAt  Local H:i, required when the day stays open.
     * @param  string|null  $endsAt  Local H:i, required when the day stays open.
     *
     * @throws PeriodHasBookingsException
     */
    public function handle(
        User $operator,
        string $date,
        bool $isUnavailable,
        ?string $startsAt = null,
        ?string $endsAt = null,
        ?string $reason = null,
    ): AvailabilityException {
        $timezone = $operator->tenant->timezone;

        $dayStart = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $dayEnd = $dayStart->endOfDay();

        return AgendaLock::forOperator($operator, function () use (
            $operator, $date, $isUnavailable, $startsAt, $endsAt, $reason, $timezone, $dayStart, $dayEnd
        ): AvailabilityException {
            $bookings = Booking::query()
                ->where('user_id', $operator->getKey())
                ->blocking()
                ->where('starts_at', '<', $dayEnd->setTimezone('UTC'))
                ->where('ends_at', '>', $dayStart->setTimezone('UTC'))
                ->get(['starts_at', 'ends_at']);

            if ($isUnavailable) {
                // The whole day goes away, so anything booked on it conflicts.
                if ($bookings->isNotEmpty()) {
                    throw PeriodHasBookingsException::withCount($bookings->count());
                }
            } else {
                $windowOpen = CarbonImmutable::parse($date.' '.$startsAt, $timezone)->setTimezone('UTC');
                $windowClose = CarbonImmutable::parse($date.' '.$endsAt, $timezone)->setTimezone('UTC');

                // Only what falls outside the new window is a problem. Buffers are
                // allowed to spill over, exactly as when the slot was generated.
                $stranded = $bookings->filter(
                    fn (Booking $booking) => $booking->starts_at->lessThan($windowOpen)
                        || $booking->ends_at->greaterThan($windowClose),
                );

                if ($stranded->isNotEmpty()) {
                    throw PeriodHasBookingsException::outsideNewHours($stranded->count());
                }
            }

            return AvailabilityException::updateOrCreate(
                ['user_id' => $operator->getKey(), 'date' => $date],
                [
                    'is_unavailable' => $isUnavailable,
                    'starts_at' => $isUnavailable ? null : $startsAt.':00',
                    'ends_at' => $isUnavailable ? null : $endsAt.':00',
                    'reason' => $reason ?: null,
                ],
            );
        });
    }
}
