<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
});

require __DIR__.'/admin.php';
require __DIR__.'/app.php';
require __DIR__.'/booking.php';
require __DIR__.'/settings.php';
