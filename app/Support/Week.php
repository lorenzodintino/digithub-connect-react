<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class Week
{
    /**
     * Weekday numbers in Monday-first order, matching Carbon's dayOfWeek.
     *
     * @return array<int, int>
     */
    public static function ordered(): array
    {
        return [1, 2, 3, 4, 5, 6, 0];
    }

    /**
     * Translated weekday labels keyed by Carbon's dayOfWeek, Monday first.
     *
     * @return array<int, string>
     */
    public static function labels(): array
    {
        return [
            1 => __('Monday'),
            2 => __('Tuesday'),
            3 => __('Wednesday'),
            4 => __('Thursday'),
            5 => __('Friday'),
            6 => __('Saturday'),
            0 => __('Sunday'),
        ];
    }

    /**
     * Translated short weekday labels keyed by Carbon's dayOfWeek, Monday first.
     *
     * @return array<int, string>
     */
    public static function shortLabels(): array
    {
        return [
            1 => __('Mon'),
            2 => __('Tue'),
            3 => __('Wed'),
            4 => __('Thu'),
            5 => __('Fri'),
            6 => __('Sat'),
            0 => __('Sun'),
        ];
    }

    /**
     * The translated label of a single weekday.
     */
    public static function label(int $weekday): string
    {
        return self::labels()[$weekday] ?? '';
    }

    /**
     * Format a H:i:s database time value as H:i.
     */
    public static function time(?string $time): string
    {
        return $time === null ? '' : substr($time, 0, 5);
    }

    /**
     * A Monday-first calendar grid for the month the given day belongs to.
     *
     * Leading and trailing days from the neighbouring months are included so
     * every row holds seven days.
     *
     * @return array<int, array<int, CarbonImmutable>>
     */
    public static function monthMatrix(CarbonImmutable $month): array
    {
        $first = $month->startOfMonth()->startOfDay();
        $cursor = $first->subDays(($first->dayOfWeek + 6) % 7);

        $last = $month->endOfMonth()->startOfDay();
        $end = $last->addDays((7 - $last->dayOfWeek) % 7);

        $weeks = [];

        while ($cursor->lessThanOrEqualTo($end)) {
            $week = [];

            for ($i = 0; $i < 7; $i++) {
                $week[] = $cursor;
                $cursor = $cursor->addDay();
            }

            $weeks[] = $week;
        }

        return $weeks;
    }
}
