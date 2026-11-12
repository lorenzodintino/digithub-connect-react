<?php

namespace App\Support;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;

/**
 * The month calendar of the public pages, drawn from the free slots: the
 * grid of the month with the days that can be picked, and the times of the
 * chosen day. The booking page of a service and the reschedule of a booking
 * draw the same one.
 *
 * Days are the portal's, the business works on its clock; the times are
 * printed on the visitor's.
 */
class SlotCalendar
{
    public function __construct(
        private readonly Tenant $tenant,
        private readonly CarbonImmutable $month,
        private readonly string $visitorTimezone,
    ) {}

    /**
     * The month asked for with `?month=Y-m`, never earlier than this one.
     */
    public static function monthOf(Request $request, Tenant $tenant): CarbonImmutable
    {
        $current = CarbonImmutable::now($tenant->timezone)->startOfMonth();
        $value = $request->query('month');

        if (! is_string($value) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) !== 1) {
            return $current;
        }

        return CarbonImmutable::parse($value.'-01', $tenant->timezone)->startOfMonth()->max($current);
    }

    /**
     * The visitor's timezone from `?tz=`, when it is one we offer.
     */
    public static function timezoneOf(Request $request, string $fallback): string
    {
        $value = $request->query('tz');

        return is_string($value) && Timezones::isSupported($value) ? $value : $fallback;
    }

    /**
     * The first and last day the grid draws, neighbours included: slots are
     * fetched for all of it, or the days of the next month it already shows
     * would look bookable and never be.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(): array
    {
        $weeks = Week::monthMatrix($this->month);

        return [$weeks[0][0], $weeks[count($weeks) - 1][6]];
    }

    /**
     * The grid of the month and why some days cannot be picked.
     *
     * @param  array<string, array<int, CarbonImmutable>>  $slots  free times keyed by date
     * @param  array<string, int>  $nearlyFull  seats left on the days where every time is short of them
     * @param  array<int, string>  $notes  see CalendarNotes
     * @return array<string, mixed>
     */
    public function month(array $slots, array $nearlyFull = [], array $notes = []): array
    {
        $current = CarbonImmutable::now($this->tenant->timezone)->startOfMonth();
        $today = CarbonImmutable::now($this->tenant->timezone)->format('Y-m-d');

        return [
            'value' => $this->month->format('Y-m'),
            'heading' => $this->month->translatedFormat('F Y'),
            'previous' => $this->month->greaterThan($current) ? $this->month->subMonth()->format('Y-m') : null,
            'next' => $this->month->addMonth()->format('Y-m'),
            'weekdays' => array_values(Week::shortLabels()),
            'days' => collect(Week::monthMatrix($this->month))->flatten(1)->map(fn (CarbonImmutable $day) => [
                'date' => $day->format('Y-m-d'),
                'number' => $day->day,
                'label' => $day->translatedFormat('D d M'),
                'in_month' => $day->month === $this->month->month,
                'is_today' => $day->format('Y-m-d') === $today,
                'available' => isset($slots[$day->format('Y-m-d')]),
                'seats_left' => $nearlyFull[$day->format('Y-m-d')] ?? null,
            ])->values()->all(),
            'notes' => $notes,
            'empty' => $slots === [],
            'nearly_full' => $nearlyFull !== [],
        ];
    }

    /**
     * The times of one day, on the visitor's clock, with what the booking form
     * prints once one of them is chosen.
     *
     * @param  array<int, CarbonImmutable>  $slots
     * @param  (Closure(CarbonImmutable): array{label: string, low: bool})|null  $seats
     * @return array<string, mixed>
     */
    public function day(string $date, array $slots, int $durationMinutes, ?Closure $seats = null): array
    {
        $portalTimezone = $this->tenant->timezone;

        return [
            'date' => $date,
            'heading' => CarbonImmutable::parse($date, $portalTimezone)->translatedFormat('D d M'),
            'slots' => array_map(function (CarbonImmutable $slot) use ($date, $durationMinutes, $seats, $portalTimezone): array {
                $start = $slot->setTimezone($this->visitorTimezone);
                $end = $start->addMinutes($durationMinutes);

                return [
                    'value' => $slot->toIso8601String(),
                    'time' => $start->format('H:i'),
                    'other_day' => $start->format('Y-m-d') !== $date ? $start->translatedFormat('D d M') : null,
                    'seats' => $seats?->__invoke($slot),
                    'date_label' => $start->translatedFormat('l d F Y'),
                    'hours' => $start->format('H:i').' – '.$end->format('H:i'),
                    // The same appointment on the portal's clock: printed at the
                    // one moment a wrong hour costs a missed appointment.
                    'portal_hours' => $this->visitorTimezone === $portalTimezone
                        ? null
                        : $start->setTimezone($portalTimezone)->format('H:i').' – '.$end->setTimezone($portalTimezone)->format('H:i'),
                ];
            }, $slots),
        ];
    }
}
