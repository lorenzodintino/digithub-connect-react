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
