<?php

namespace App\Support;

class Timezones
{
    /**
     * The timezones offered across the application.
     *
     * Kept to a curated list: Flux's free select has no search field, so a full
     * IANA dump would be unusable. Add entries here when a portal needs one.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return [
            'Europe/Rome',
            'Europe/London',
            'Europe/Paris',
            'Europe/Berlin',
            'Europe/Madrid',
            'Europe/Lisbon',
            'Europe/Amsterdam',
            'Europe/Brussels',
            'Europe/Zurich',
            'Europe/Vienna',
            'Europe/Athens',
            'Europe/Bucharest',
            'Europe/Warsaw',
            'Europe/Istanbul',
            'Europe/Moscow',
            'UTC',
            'America/New_York',
            'America/Chicago',
            'America/Denver',
            'America/Los_Angeles',
            'America/Sao_Paulo',
            'Asia/Dubai',
            'Asia/Kolkata',
            'Asia/Shanghai',
            'Asia/Tokyo',
            'Australia/Sydney',
        ];
    }

    /**
     * Determine whether the given identifier is one this application offers.
     */
    public static function isSupported(?string $timezone): bool
    {
        return $timezone !== null && in_array($timezone, self::options(), true);
    }

    /**
     * The default timezone used when nothing else has been chosen.
     */
    public static function default(): string
    {
        return 'Europe/Rome';
    }
}
