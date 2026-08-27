<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('app')
    ->name('app.')
    ->group(function () {
        Route::middleware('role:manager')->group(function () {
            Route::livewire('services', 'pages::app.services')->name('services');
            Route::livewire('staff', 'pages::app.staff')->name('staff');
            Route::livewire('portal', 'pages::app.portal')->name('portal');
        });

        Route::middleware('role:manager,default')->group(function () {
            Route::livewire('bookings', 'pages::app.bookings')->name('bookings');
            Route::livewire('availability', 'pages::app.availability')->name('availability');
        });
    });
