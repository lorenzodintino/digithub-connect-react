<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ImpersonateManagerController;
use App\Http\Controllers\Admin\LegalDocumentController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\LicenseExportController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PlanRequestController;
use App\Http\Controllers\Admin\PortalController;
use App\Http\Controllers\Admin\QueueController;
use App\Http\Controllers\Admin\ShowcaseMailController;
use App\Http\Controllers\MailSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::redirect('/', '/admin/portals');

        Route::get('portals', [PortalController::class, 'index'])->name('portals');
        Route::post('portals', [PortalController::class, 'store'])->name('portals.store');
        Route::get('portals/{tenant}', [PortalController::class, 'show'])->name('portals.show');
        Route::put('portals/{tenant}', [PortalController::class, 'update'])->name('portals.update');
        Route::patch('portals/{tenant}/active', [PortalController::class, 'toggle'])->name('portals.toggle');
        Route::delete('portals/{tenant}', [PortalController::class, 'destroy'])->name('portals.destroy');
        Route::post('portals/{tenant}/impersonate', ImpersonateManagerController::class)->name('portals.impersonate');
        Route::post('portals/{tenant}/license/refresh', [LicenseController::class, 'refresh'])->middleware('throttle:6,1')->name('portals.license.refresh');
        Route::get('portals/{tenant}/license-manager.json', LicenseExportController::class)->name('portals.license-json');
        Route::put('portals/{tenant}/license/exempt', [LicenseController::class, 'exempt'])->name('portals.license.exempt');
        Route::put('portals/{tenant}/mail', [MailSettingsController::class, 'update'])->name('portals.mail.update');
        Route::post('portals/{tenant}/mail/test', [MailSettingsController::class, 'test'])->middleware('throttle:6,1')->name('portals.mail.test');

        Route::get('users', [AccountController::class, 'index'])->name('users');
        Route::patch('users/{account}/active', [AccountController::class, 'toggle'])->name('users.toggle');
        Route::post('users/{account}/password', [AccountController::class, 'resetPassword'])->name('users.password');
        Route::delete('users/{account}', [AccountController::class, 'destroy'])->name('users.destroy');

        Route::get('activity', ActivityController::class)->name('activity');

        Route::get('queue', [QueueController::class, 'index'])->name('queue');
        Route::post('queue/retry', [QueueController::class, 'retryAll'])->name('queue.retry-all');
        Route::post('queue/{uuid}/retry', [QueueController::class, 'retry'])->name('queue.retry');
        Route::delete('queue/{uuid}', [QueueController::class, 'destroy'])->name('queue.destroy');

        Route::get('showcase-mail', [ShowcaseMailController::class, 'index'])->name('showcase-mail');
        Route::put('showcase-mail', [ShowcaseMailController::class, 'update'])->name('showcase-mail.update');
        Route::post('showcase-mail/test', [ShowcaseMailController::class, 'test'])->middleware('throttle:6,1')->name('showcase-mail.test');

        Route::get('plans', [PlanController::class, 'index'])->name('plans');

        Route::get('plan-requests', [PlanRequestController::class, 'index'])->name('plan-requests');
        Route::patch('plan-requests/{planRequest}', [PlanRequestController::class, 'update'])->name('plan-requests.update');

        Route::get('legal', [LegalDocumentController::class, 'index'])->name('legal');
        Route::post('legal/{type}', [LegalDocumentController::class, 'store'])->name('legal.store');
    });
