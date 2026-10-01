<?php

namespace App\Support;

use App\Models\PortalClosure;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The lines that say why a drawn calendar holds days nobody can pick.
 *
 * A day the visitor cannot click looks the same whatever the cause: beyond the
 * booking window, inside a portal closure, or simply nobody working. Read
 * without a word of explanation all three say "closed", so a service bookable
 * only thirty days ahead looks shut for the rest of the month — that is how
 * the closure of 14–20 October was read on 2026-09-09, when what was showing
 * was the window.
 */
class CalendarNotes
{
    /**
     * The notes to print under the calendar drawn between those two days.
     *
     * @return array<int, string>
     */
    public static function forService(Service $service, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $timezone = $service->tenant->timezone;
        $first = $from->setTimezone($timezone)->startOfDay();
        $last = $to->setTimezone($timezone)->startOfDay();

        $notes = [];

        $latest = CarbonImmutable::now($timezone)->addDays($service->max_advance_days)->startOfDay();

        // Said only when the grid really draws days past the window: on a month
        // entirely inside it the line would be noise.
        if ($latest->lessThan($last)) {
            $notes[] = __('You can book up to :date', ['date' => self::day($latest, $timezone)]);
        }

        foreach (self::closuresBetween($service, $first, $last) as $closure) {
            $notes[] = self::closureNote($closure, $timezone);
        }

        return $notes;
    }

    /**
     * The closures of the portal overlapping the drawn days, in date order.
     *
     * @return Collection<int, PortalClosure>
     */
    private static function closuresBetween(Service $service, CarbonImmutable $first, CarbonImmutable $last): Collection
    {
        return PortalClosure::query()
            ->forTenant($service->tenant_id)
            ->overlapping($first->toDateString(), $last->toDateString())
            ->orderBy('starts_on')
            ->get();
    }

    /**
     * One closure, worded as a person would say it.
     *
     * The reason the manager typed stays out: it is written for their own
     * agenda, and "closed for a bereavement" is nobody else's business.
     */
    private static function closureNote(PortalClosure $closure, string $timezone): string
    {
        if ($closure->starts_on->isSameDay($closure->ends_on)) {
            return __('Closed on :date', ['date' => self::day($closure->starts_on, $timezone)]);
        }

        return __('Closed from :from to :to', [
            // "from 14 to 20 October" inside one month, both months named when
            // the closure straddles two.
            'from' => self::day($closure->starts_on, $timezone, withMonth: ! $closure->starts_on->isSameMonth($closure->ends_on)),
            'to' => self::day($closure->ends_on, $timezone),
        ]);
    }

    /**
     * A day written out, with the year only when it is not the current one.
     */
    private static function day(CarbonImmutable $date, string $timezone, bool $withMonth = true): string
    {
        if (! $withMonth) {
            return $date->translatedFormat('j');
        }

        $format = $date->year === CarbonImmutable::now($timezone)->year ? 'j F' : 'j F Y';

        return $date->translatedFormat($format);
    }
}
