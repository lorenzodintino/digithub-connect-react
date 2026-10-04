<?php

namespace App\Support;

/**
 * Lay out a day's appointments on a time grid, side by side where they overlap.
 *
 * Two appointments that share some minutes cannot sit on top of each other:
 * each gets a lane, and every appointment of the same overlapping cluster is
 * drawn as wide as the cluster's lane count allows. Appointments that only
 * touch (one ends when the next starts) do not overlap.
 */
class TimeGrid
{
    /**
     * Place the intervals in lanes.
     *
     * @template TKey of array-key
     *
     * @param  array<TKey, array{start: int, end: int}>  $intervals  minutes from the start of the day
     * @return array<TKey, array{start: int, end: int, lane: int, lanes: int}>
     */
    public static function place(array $intervals): array
    {
        uasort($intervals, fn (array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);

        $lanesOf = [];
        $laneOf = [];
        $cluster = [];
        $laneEnds = [];
        $clusterEnd = null;

        foreach ($intervals as $key => $interval) {
            // A gap closes the cluster: what came before can widen to its own lanes.
            if ($clusterEnd !== null && $interval['start'] >= $clusterEnd) {
                $lanesOf += array_fill_keys($cluster, count($laneEnds));
                $cluster = [];
                $laneEnds = [];
                $clusterEnd = null;
            }

            $lane = self::firstFreeLane($laneEnds, $interval['start']);
            $laneEnds[$lane] = $interval['end'];
            $clusterEnd = max($clusterEnd ?? $interval['end'], $interval['end']);

            $laneOf[$key] = $lane;
            $cluster[] = $key;
        }

        $lanesOf += array_fill_keys($cluster, count($laneEnds));

        $placed = [];

        foreach ($intervals as $key => $interval) {
            $placed[$key] = [...$interval, 'lane' => $laneOf[$key], 'lanes' => $lanesOf[$key]];
        }

        return $placed;
    }

    /**
     * The lowest lane whose last appointment has already ended.
     *
     * @param  array<int, int>  $laneEnds
     */
    private static function firstFreeLane(array $laneEnds, int $start): int
    {
        foreach ($laneEnds as $lane => $end) {
            if ($end <= $start) {
                return $lane;
            }
        }

        return count($laneEnds);
    }
}
