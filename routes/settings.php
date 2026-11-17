<?php

use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ProfilePhotoController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('settings/profile/photo', [ProfilePhotoController::class, 'store'])->name('profile.photo.store');
    Route::delete('settings/profile/photo', [ProfilePhotoController::class, 'destroy'])->name('profile.photo.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/appearance', [AppearanceController::class, 'edit'])->name('appearance.edit');
    Route::put('settings/appearance', [AppearanceController::class, 'update'])->name('appearance.update');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware([
            'password.confirm',
        ])
        ->name('security.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');
});

Route::get('.well-known/passkey-endpoints', function () {
    // Nothing to announce while passkeys are off.
    abort_unless(Features::enabled(Features::passkeys()), 404);

    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
