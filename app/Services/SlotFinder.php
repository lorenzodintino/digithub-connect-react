<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PortalClosure;
use App\Models\Service;
use App\Models\ServiceSession;
use App\Models\TimeBlock;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Turns working hours into concrete bookable slots.
 *
 * Opening hours come from `WorkingHours`, which resolves weekly rules and date
 * overrides. Every value this class returns is UTC; rendering in the visitor
 * timezone happens in the views.
 */
class SlotFinder
{
    private ?Booking $ignoredBooking = null;

    private readonly WorkingHours $hours;

    public function __construct(
        private readonly Service $service,
        private readonly User $operator,
    ) {
        $this->hours = new WorkingHours($operator, $service->tenant->timezone);
    }

    /**
     * Ignore a booking when checking for conflicts, used while rescheduling it.
     */
    public function ignoring(?Booking $booking): self
    {
        $this->ignoredBooking = $booking;

        return $this;
    }

    /**
     * The timezone the working hours are expressed in.
     */
    public function timezone(): string
    {
        return $this->hours->timezone();
    }

    /**
     * The first instant that can be booked, honouring the minimum notice.
     */
    public function earliestBookableAt(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->addMinutes($this->service->min_notice_minutes);
    }

    /**
     * The last instant that can be booked, honouring the booking window.
     */
    public function latestBookableAt(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone())
            ->addDays($this->service->max_advance_days)
            ->endOfDay()
            ->setTimezone('UTC');
    }

    /**
     * The bookable slots of a single day, given as a date in the portal timezone.
     *
     * @return array<int, CarbonImmutable>
     */
    public function slotsForDay(CarbonImmutable $day): array
    {
        return $this->slotsBetween($day, $day)[$day->setTimezone($this->timezone())->format('Y-m-d')] ?? [];
    }

    /**
     * The bookable slots of every day in the range, keyed by date in the portal timezone.
     *
     * Days without any slot are omitted.
     *
     * @return array<string, array<int, CarbonImmutable>>
     */
    public function slotsBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! $this->operatorCanTakeBookings()) {
            return [];
        }

        $timezone = $this->timezone();
        $from = $from->setTimezone($timezone)->startOfDay();
        $to = $to->setTimezone($timezone)->endOfDay();

        if ($from->greaterThan($to)) {
            return [];
        }

        $this->hours->preload($from, $to);

        // Widen the busy lookup by a day so buffers spilling over midnight are seen.
        $busy = $this->busyPeriods(
            $from->setTimezone('UTC')->subDay(),
            $to->setTimezone('UTC')->addDay(),
        );

        $earliest = $this->earliestBookableAt();
        $latest = $this->latestBookableAt();

        $closed = $this->closedDates($from, $to);

        $slots = [];

        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            if (isset($closed[$day->format('Y-m-d')])) {
                continue;
            }

            $found = [];

            foreach ($this->hours->windowsFor($day) as [$windowOpen, $windowClose]) {
                for (
                    $start = $windowOpen;
                    $start->addMinutes($this->service->duration_minutes)->lessThanOrEqualTo($windowClose);
                    $start = $start->addMinutes($this->service->slot_interval_minutes)
                ) {
                    if ($start->lessThan($earliest) || $start->greaterThan($latest)) {
                        continue;
                    }

                    if ($this->overlapsBusyPeriod($start, $busy)) {
                        continue;
                    }

                    $found[] = $start;
                }
            }

            if ($found !== []) {
                $slots[$day->format('Y-m-d')] = $found;
            }
        }

        return $slots;
    }

    /**
     * Determine whether a booking can start at the given UTC instant.
     */
    public function canBookAt(CarbonImmutable $startsAt): bool
    {
        $startsAt = $startsAt->setTimezone('UTC');

        foreach ($this->slotsForDay($startsAt->setTimezone($this->timezone())) as $slot) {
            if ($slot->equalTo($startsAt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The window a booking occupies once the service buffers are applied.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function blockedWindowFor(CarbonImmutable $startsAt): array
    {
        $startsAt = $startsAt->setTimezone('UTC');

        return [
            $startsAt->subMinutes($this->service->buffer_before_minutes),
            $startsAt->addMinutes($this->service->duration_minutes + $this->service->buffer_after_minutes),
        ];
    }

    /**
     * The instant a booking starting at the given time would end.
     */
    public function endsAtFor(CarbonImmutable $startsAt): CarbonImmutable
    {
        return $startsAt->setTimezone('UTC')->addMinutes($this->service->duration_minutes);
    }

    /**
     * The days the whole portal is shut, keyed by date in the portal timezone.
     *
     * A closure belongs to the business, not to a person: it wipes the day for
     * every operator at once, on top of whatever their own hours say.
     *
     * @return array<string, true>
     */
    private function closedDates(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return PortalClosure::closedDates(
            $this->service->tenant_id,
            $from->toDateString(),
            $to->toDateString(),
        );
    }

    /**
     * Determine whether the operator is in a state where bookings make sense.
     */
    private function operatorCanTakeBookings(): bool
    {
        return $this->operator->is_bookable
            && $this->operator->is_active
            && $this->service->is_active
            && $this->service->tenant->is_active;
    }

    /**
     * Every period the operator is already taken, as UTC ranges.
     *
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function busyPeriods(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $bookings = Booking::query()
            ->where('user_id', $this->operator->getKey())
            ->blocking()
            ->when($this->ignoredBooking, fn ($query) => $query->whereKeyNot($this->ignoredBooking->getKey()))
            ->where('blocked_starts_at', '<', $to)
            ->where('blocked_ends_at', '>', $from)
            ->get(['blocked_starts_at', 'blocked_ends_at'])
            ->map(fn (Booking $booking) => [$booking->blocked_starts_at, $booking->blocked_ends_at]);

        // A group session takes the time of everybody giving it, whatever
        // service they are booked for elsewhere.
        $sessions = ServiceSession::query()
            ->live()
            ->whereHas('operators', fn ($query) => $query->whereKey($this->operator->getKey()))
            ->where('blocked_starts_at', '<', $to)
            ->where('blocked_ends_at', '>', $from)
            ->get(['blocked_starts_at', 'blocked_ends_at'])
            ->map(fn (ServiceSession $session) => [$session->blocked_starts_at, $session->blocked_ends_at]);

        $blocks = TimeBlock::query()
            ->where('user_id', $this->operator->getKey())
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get(['starts_at', 'ends_at'])
            ->map(fn (TimeBlock $block) => [$block->starts_at, $block->ends_at]);

        return $bookings->concat($blocks)->concat($sessions)->all();
    }

    /**
     * Determine whether a candidate slot collides with an existing commitment.
     *
     * @param  array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>  $busy
     */
    private function overlapsBusyPeriod(CarbonImmutable $start, array $busy): bool
    {
        [$blockedStart, $blockedEnd] = $this->blockedWindowFor($start);

        foreach ($busy as [$busyStart, $busyEnd]) {
            if ($blockedStart->lessThan($busyEnd) && $busyStart->lessThan($blockedEnd)) {
                return true;
            }
        }

        return false;
    }
}
