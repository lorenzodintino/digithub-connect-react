<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\StopImpersonating;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Where a portal whose licence is refused is sent (EnsureLicenseAllowsAccess), and the manager's re-check.
    Route::get('license', [LicenseController::class, 'blocked'])->name('license.blocked');
    Route::post('license/recheck', [LicenseController::class, 'recheck'])->middleware('throttle:6,1')->name('license.recheck');

    // Not behind `role:admin`: while impersonating, the session is the manager.
    Route::get('stop-impersonating', StopImpersonating::class)->name('impersonation.stop');
});

require __DIR__.'/admin.php';
require __DIR__.'/app.php';
require __DIR__.'/booking.php';
require __DIR__.'/settings.php';
