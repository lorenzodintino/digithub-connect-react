<?php

use App\Http\Controllers\BookingCalendarController;
use Illuminate\Support\Facades\Route;

Route::prefix('p')->name('portal.')->group(function () {
    Route::livewire('{tenant}', 'pages::portal.show')->name('show');

    // Placed before the service route so the "b" segment is never read as a slug.
    Route::livewire('{tenant}/b/{booking}', 'pages::portal.booking')->name('booking');

    Route::get('{tenant}/b/{booking}/calendar.ics', BookingCalendarController::class)
        ->name('booking.calendar');

    Route::livewire('{tenant}/{service}', 'pages::portal.service')
        ->scopeBindings()
        ->name('service');
});
