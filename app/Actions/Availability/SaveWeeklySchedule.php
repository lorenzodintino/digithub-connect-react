<?php

namespace App\Actions\Availability;

use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\User;
use App\Services\AgendaLock;
use App\Services\WorkingHours;
use Illuminate\Support\Collection;

class SaveWeeklySchedule
{
    /**
     * Replace the weekly working hours of a staff member.
     *
     * Unlike blocking a period or closing a day, this is a standing setting
     * rather than a one-off decision: refusing to save because a booking three
     * weeks out falls outside the new hours would make the screen unusable. It
     * saves, then reports what ended up uncovered so it can be dealt with.
     *
     * @param  array<int, array<int, array{starts_at: string, ends_at: string}>>  $schedule
     *                                                                                       Local H:i ranges, keyed by weekday.
     * @return Collection<int, Booking> Upcoming bookings left outside the new hours.
     */
    public function handle(User $operator, array $schedule): Collection
    {
        return AgendaLock::forOperator($operator, function () use ($operator, $schedule): Collection {
            AvailabilityRule::query()->where('user_id', $operator->getKey())->delete();

            $rows = [];

            foreach ($schedule as $weekday => $ranges) {
                foreach ($ranges as $range) {
                    $rows[] = [
                        'user_id' => $operator->getKey(),
                        'weekday' => $weekday,
                        'starts_at' => $range['starts_at'].':00',
                        'ends_at' => $range['ends_at'].':00',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if ($rows !== []) {
                AvailabilityRule::query()->insert($rows);
            }

            return $this->uncoveredBookings($operator);
        });
    }

    /**
     * Upcoming bookings that the current hours no longer cover.
     *
     * @return Collection<int, Booking>
     */
    public function uncoveredBookings(User $operator): Collection
    {
        $bookings = Booking::query()
            ->where('user_id', $operator->getKey())
            ->blocking()
            ->upcoming()
            ->with('service')
            ->orderBy('starts_at')
            ->get();

        if ($bookings->isEmpty()) {
            return $bookings;
        }

        $hours = new WorkingHours($operator, $operator->tenant->timezone);
        $hours->preload($bookings->first()->starts_at, $bookings->last()->starts_at);

        return $bookings->reject(
            fn (Booking $booking) => $hours->cover($booking->starts_at, $booking->ends_at),
        )->values();
    }
}
