<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::redirect('/', '/admin/portals');

        Route::livewire('portals', 'pages::admin.portals')->name('portals');
        Route::livewire('portals/{tenant}', 'pages::admin.portal')->name('portals.show');
        Route::livewire('users', 'pages::admin.users')->name('users');
    });
