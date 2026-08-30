<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every quarter hour rather than hourly: the command asks for everything still
// due, so a frequent run only shortens how late a reminder can be.
Schedule::command('bookings:send-reminders')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// Hourly is enough: the command closes everything already past the grace
// period, so a missed run only delays the change, it never skips a booking.
Schedule::command('bookings:complete-past')
    ->hourly()
    ->withoutOverlapping();

// A failed email is a promise the portal does not know it broke: one digest
// an hour, so a portal with wrong credentials does not send a mail per failure.
Schedule::command('jobs:report-failures')
    ->hourly()
    ->withoutOverlapping();

// The register answers questions about the past year, not about all time.
Schedule::command('activity:prune')
    ->weeklyOn(1, '03:45')
    ->withoutOverlapping();

// A waiting list whose dates have gone by is only personal data nobody reads.
Schedule::command('waiting-list:prune')
    ->dailyAt('03:15')
    ->withoutOverlapping();

// Retention is measured in days, so once a night is plenty.
Schedule::command('bookings:anonymise-old')
    ->dailyAt('03:30')
    ->withoutOverlapping();
