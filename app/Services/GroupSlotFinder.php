<?php

namespace App\Services;

use App\Models\PortalClosure;
use App\Models\Service;
use App\Models\ServiceSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Bookable times for a service several people share.
 *
 * Two different things make a time bookable here:
 *
 * - a session already running at that time with a seat left — the class exists,
 *   its staff are busy with it, and the customer joins them;
 * - a time where at least one of the service operators is free, so a new
 *   session could start.
 *
 * A session that filled up takes its time off the list entirely: a second class
 * of the same kind at the same minute would be the same class.
 */
class GroupSlotFinder
{
    public function __construct(private readonly Service $service) {}

    /**
     * The bookable times of a single day, given in the portal timezone.
     *
     * @return array<int, CarbonImmutable>
     */
    public function slotsForDay(CarbonImmutable $day): array
    {
        $timezone = $this->service->tenant->timezone;

        return $this->slotsBetween($day, $day)[$day->setTimezone($timezone)->format('Y-m-d')] ?? [];
    }

    /**
     * The bookable times of every day in the range, keyed by local date.
     *
     * @return array<string, array<int, CarbonImmutable>>
     */
    public function slotsBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $timezone = $this->service->tenant->timezone;
        $operators = $this->operators();

        if ($operators->isEmpty()) {
            return [];
        }

        $sessions = $this->sessionsBetween($from, $to);

        $taken = [];
        $offered = [];

        foreach ($sessions as $session) {
            // The hour belongs to this class either way: a full one takes its
            // time off the list instead of letting a second class open on it.
            $taken[$session->starts_at->getTimestamp()] = true;

            if (! $session->isFull() && $this->isStillBookable($session->starts_at)) {
                $offered[$session->starts_at->getTimestamp()] = $session->starts_at;
            }
        }

        // Where nobody is teaching yet, anybody free can start the class.
        foreach ($operators as $operator) {
            foreach ((new SlotFinder($this->service, $operator))->slotsBetween($from, $to) as $instants) {
                foreach ($instants as $instant) {
                    if (! isset($taken[$instant->getTimestamp()])) {
                        $offered[$instant->getTimestamp()] = $instant;
                    }
                }
            }
        }

        ksort($offered);

        $days = [];

        foreach ($offered as $instant) {
            $days[$instant->setTimezone($timezone)->format('Y-m-d')][] = $instant;
        }

        return $days;
    }

    /**
     * Determine whether a seat can be taken at the given instant.
     */
    public function canBookAt(CarbonImmutable $startsAt): bool
    {
        $startsAt = $startsAt->setTimezone('UTC');

        foreach ($this->slotsForDay($startsAt->setTimezone($this->service->tenant->timezone)) as $slot) {
            if ($slot->equalTo($startsAt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The session already running at that instant, if there is one.
     */
    public function sessionAt(CarbonImmutable $startsAt): ?ServiceSession
    {
        return $this->service->sessions()
            ->where('starts_at', $startsAt->setTimezone('UTC'))
            ->first();
    }

    /**
     * How many seats are left at that instant, capacity when nothing runs yet.
     */
    public function seatsLeftAt(CarbonImmutable $startsAt): int
    {
        $session = $this->sessionAt($startsAt);

        if ($session === null) {
            return $this->service->capacity;
        }

        return $session->isFull() && ! $this->hasLiveBookings($session) ? $this->service->capacity : $session->seatsLeft();
    }

    /**
     * The staff who could give this service.
     *
     * @return Collection<int, User>
     */
    public function operators(): Collection
    {
        return $this->service->operators()->bookable()->orderBy('name')->get();
    }

    /**
     * The operators free to give a session starting at that instant.
     *
     * @return Collection<int, User>
     */
    public function operatorsFreeAt(CarbonImmutable $startsAt): Collection
    {
        return $this->operators()->filter(
            fn (User $operator) => (new SlotFinder($this->service, $operator))->canBookAt($startsAt)
        )->values();
    }

    /**
     * Determine whether a class already running is still open to newcomers.
     *
     * An existing session must not become a way around the rules the service
     * sets for everybody else: the minimum notice, the booking window and the
     * days the portal declared shut all still apply to it.
     */
    private function isStillBookable(CarbonImmutable $startsAt): bool
    {
        $service = $this->service;
        $timezone = $service->tenant->timezone;

        $earliest = CarbonImmutable::now('UTC')->addMinutes($service->min_notice_minutes);
        $latest = CarbonImmutable::now($timezone)->addDays($service->max_advance_days)->endOfDay()->setTimezone('UTC');

        if ($startsAt->lessThan($earliest) || $startsAt->greaterThan($latest)) {
            return false;
        }

        $date = $startsAt->setTimezone($timezone)->format('Y-m-d');

        return PortalClosure::closedDates($service->tenant_id, $date, $date) === [];
    }

    /**
     * The sessions of this service that still hold somebody, in the range.
     *
     * @return Collection<int, ServiceSession>
     */
    private function sessionsBetween(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $timezone = $this->service->tenant->timezone;

        return $this->service->sessions()
            ->live()
            ->whereBetween('starts_at', [
                $from->setTimezone($timezone)->startOfDay()->utc(),
                $to->setTimezone($timezone)->endOfDay()->utc(),
            ])
            ->get();
    }

    /**
     * Determine whether anybody is still holding a seat on the session.
     */
    private function hasLiveBookings(ServiceSession $session): bool
    {
        return $session->seatsTaken() > 0;
    }
}
