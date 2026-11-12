<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StopImpersonating;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Not behind `role:admin`: while impersonating, the session is the manager.
    Route::get('stop-impersonating', StopImpersonating::class)->name('impersonation.stop');
});

require __DIR__.'/admin.php';
require __DIR__.'/app.php';
require __DIR__.'/booking.php';
require __DIR__.'/settings.php';
