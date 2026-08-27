<?php

namespace App\Services;

use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Answers one question: when is this person open on a given day?
 *
 * Weekly rules give the baseline; a date override replaces them for that single
 * day. Windows are expressed in the portal timezone and returned as UTC.
 *
 * `SlotFinder` uses it to generate slots; the availability screen uses it to
 * tell whether existing bookings still fall inside the hours.
 */
class WorkingHours
{
    /**
     * Weekly rules grouped by weekday, loaded once.
     *
     * @var array<int, array<int, AvailabilityRule>>|null
     */
    private ?array $rules = null;

    /**
     * Date overrides already resolved, keyed by date. A null value means the
     * day was looked up and has no override.
     *
     * @var array<string, AvailabilityException|null>
     */
    private array $overrides = [];

    public function __construct(
        private readonly User $operator,
        private readonly string $timezone,
    ) {}

    /**
     * The timezone the hours are expressed in.
     */
    public function timezone(): string
    {
        return $this->timezone;
    }

    /**
     * Resolve every date override of a range in one query.
     *
     * Without this a month-long loop would query once per day.
     */
    public function preload(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $from = $from->setTimezone($this->timezone)->startOfDay();
        $to = $to->setTimezone($this->timezone)->endOfDay();

        // The column holds a full datetime, so compare on the date part rather
        // than on raw strings, which would never match "Y-m-d 00:00:00".
        $found = $this->operator->availabilityExceptions()
            ->whereDate('date', '>=', $from->format('Y-m-d'))
            ->whereDate('date', '<=', $to->format('Y-m-d'))
            ->get()
            ->keyBy(fn (AvailabilityException $exception) => $exception->date->format('Y-m-d'));

        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $date = $day->format('Y-m-d');

            $this->overrides[$date] = $found->get($date);
        }
    }

    /**
     * The opening windows of a local day, as UTC instants.
     *
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function windowsFor(CarbonImmutable $day): array
    {
        $day = $day->setTimezone($this->timezone)->startOfDay();
        $override = $this->overrideFor($day);

        if ($override !== null) {
            if ($override->is_unavailable || $override->starts_at === null || $override->ends_at === null) {
                return [];
            }

            return [[
                $this->instant($day, AvailabilityRule::toMinutes($override->starts_at)),
                $this->instant($day, AvailabilityRule::toMinutes($override->ends_at)),
            ]];
        }

        return array_map(
            fn (AvailabilityRule $rule) => [
                $this->instant($day, $rule->startsAtMinutes()),
                $this->instant($day, $rule->endsAtMinutes()),
            ],
            $this->weeklyRules()[$day->dayOfWeek] ?? [],
        );
    }

    /**
     * Determine whether a single window fully contains the given period.
     */
    public function cover(CarbonImmutable $from, CarbonImmutable $to): bool
    {
        $from = $from->setTimezone('UTC');
        $to = $to->setTimezone('UTC');

        foreach ($this->windowsFor($from) as [$open, $close]) {
            if ($from->greaterThanOrEqualTo($open) && $to->lessThanOrEqualTo($close)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The date override of a day, resolving it if it was not preloaded.
     */
    private function overrideFor(CarbonImmutable $day): ?AvailabilityException
    {
        $date = $day->format('Y-m-d');

        if (! array_key_exists($date, $this->overrides)) {
            $this->overrides[$date] = $this->operator->availabilityExceptions()
                ->whereDate('date', $date)
                ->first();
        }

        return $this->overrides[$date];
    }

    /**
     * The weekly rules of the operator, grouped by weekday.
     *
     * @return array<int, array<int, AvailabilityRule>>
     */
    private function weeklyRules(): array
    {
        if ($this->rules !== null) {
            return $this->rules;
        }

        $grouped = [];

        foreach ($this->operator->availabilityRules()->orderBy('starts_at')->get() as $rule) {
            $grouped[$rule->weekday][] = $rule;
        }

        return $this->rules = $grouped;
    }

    /**
     * Build the UTC instant for a local time on the given day.
     */
    private function instant(CarbonImmutable $day, int $minutes): CarbonImmutable
    {
        return $day->setTime(intdiv($minutes, 60), $minutes % 60)->setTimezone('UTC');
    }
}
