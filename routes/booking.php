<?php

use App\Http\Controllers\BookingCalendarController;
use App\Http\Controllers\Portal\BookingPageController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\ServicePageController;
use App\Http\Controllers\Portal\WaitingListController;
use Illuminate\Support\Facades\Route;

Route::prefix('p')->name('portal.')->group(function () {
    Route::get('{tenant}', [PortalController::class, 'show'])->name('show');

    // Placed before the service routes so the "b" segment is never read as a slug.
    Route::get('{tenant}/b/{booking}', [BookingPageController::class, 'show'])->name('booking');
    Route::post('{tenant}/b/{booking}/cancel', [BookingPageController::class, 'cancel'])->name('booking.cancel');
    Route::post('{tenant}/b/{booking}/reschedule', [BookingPageController::class, 'reschedule'])->name('booking.reschedule');

    Route::get('{tenant}/b/{booking}/calendar.ics', BookingCalendarController::class)
        ->name('booking.calendar');

    // Reached from the email: the page asks, the button takes the person off the list.
    Route::get('{tenant}/w/{entry}', [WaitingListController::class, 'show'])->name('waiting-list.leave');
    Route::delete('{tenant}/w/{entry}', [WaitingListController::class, 'destroy'])->name('waiting-list.destroy');

    Route::get('{tenant}/{service}', [ServicePageController::class, 'show'])
        ->scopeBindings()
        ->name('service');
    Route::post('{tenant}/{service}/book', [ServicePageController::class, 'book'])
        ->scopeBindings()
        ->name('service.book');
    Route::post('{tenant}/{service}/waiting-list', [WaitingListController::class, 'store'])
        ->scopeBindings()
        ->name('service.waiting-list');
});
