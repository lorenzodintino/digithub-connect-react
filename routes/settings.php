<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ProfilePhotoController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('settings/profile/photo', [ProfilePhotoController::class, 'store'])->name('profile.photo.store');
    Route::delete('settings/profile/photo', [ProfilePhotoController::class, 'destroy'])->name('profile.photo.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

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
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
