<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingSessionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ExportPortalController;
use App\Http\Controllers\MailSettingsController;
use App\Http\Controllers\PlanUpgradeRequestController;
use App\Http\Controllers\PortalFileController;
use App\Http\Controllers\PortalSettingsController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResourceBlockController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffPhotoController;
use App\Http\Controllers\WaitingListController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('app')
    ->name('app.')
    ->group(function () {
        Route::middleware('role:manager')->group(function () {
            // {ownService} only resolves inside the manager's portal: see AppServiceProvider.
            Route::get('services', [ServiceController::class, 'index'])->name('services');
            Route::post('services', [ServiceController::class, 'store'])->name('services.store');
            Route::put('services/{ownService}', [ServiceController::class, 'update'])->name('services.update');
            Route::patch('services/{ownService}/toggle', [ServiceController::class, 'toggle'])->name('services.toggle');
            Route::patch('services/{ownService}/position', [ServiceController::class, 'reorder'])->name('services.reorder');
            Route::delete('services/{ownService}', [ServiceController::class, 'destroy'])->name('services.destroy');
            Route::get('resources', [ResourceController::class, 'index'])->name('resources');
            Route::post('resources', [ResourceController::class, 'store'])->name('resources.store');
            Route::put('resources/{resource}', [ResourceController::class, 'update'])->name('resources.update');
            Route::delete('resources/{resource}', [ResourceController::class, 'destroy'])->name('resources.destroy');
            Route::post('resources/{resource}/blocks', [ResourceBlockController::class, 'store'])->name('resources.blocks.store');
            Route::delete('resource-blocks/{block}', [ResourceBlockController::class, 'destroy'])->name('resources.blocks.destroy');
            Route::get('staff', [StaffController::class, 'index'])->name('staff');
            Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
            Route::patch('staff/bookable', [StaffController::class, 'toggleBookable'])->name('staff.bookable');
            Route::put('staff/{member}', [StaffController::class, 'update'])->name('staff.update');
            Route::delete('staff/{member}', [StaffController::class, 'destroy'])->name('staff.destroy');
            Route::post('staff/{member}/photo', [StaffPhotoController::class, 'store'])->name('staff.photo.store');
            Route::delete('staff/{member}/photo', [StaffPhotoController::class, 'destroy'])->name('staff.photo.destroy');
            Route::get('customers', [CustomerController::class, 'index'])->name('customers');
            Route::get('customers/export', [CustomerController::class, 'export'])->name('customers.export');
            Route::patch('customers/{customer}/notes', [CustomerController::class, 'updateNotes'])->name('customers.notes');
            Route::get('waiting-list', [WaitingListController::class, 'index'])->name('waiting-list');
            Route::delete('waiting-list/{entry}', [WaitingListController::class, 'destroy'])->name('waiting-list.destroy');
            Route::get('activity', ActivityController::class)->name('activity');
            Route::get('portal', [PortalSettingsController::class, 'edit'])->name('portal');
            Route::put('portal', [PortalSettingsController::class, 'update'])->name('portal.update');
            Route::post('portal/files/{kind}', [PortalFileController::class, 'store'])->whereIn('kind', ['logo', 'privacy', 'terms'])->name('portal.files.store');
            Route::delete('portal/files/{kind}', [PortalFileController::class, 'destroy'])->whereIn('kind', ['logo', 'privacy', 'terms'])->name('portal.files.destroy');
            Route::put('portal/mail', [MailSettingsController::class, 'update'])->name('portal.mail.update');
            Route::post('portal/mail/test', [MailSettingsController::class, 'test'])->middleware('throttle:6,1')->name('portal.mail.test');
            Route::post('portal/documents/{document}/accept', [PortalSettingsController::class, 'acceptDocument'])->name('portal.documents.accept');
            Route::get('portal/export', ExportPortalController::class)->middleware('throttle:6,1')->name('portal.export');
            Route::post('plan/upgrade', [PlanUpgradeRequestController::class, 'store'])->middleware('throttle:5,60')->name('plan.upgrade');
            Route::delete('plan/upgrade/{planRequest}', [PlanUpgradeRequestController::class, 'cancel'])->name('plan.upgrade.cancel');
        });

        Route::middleware('role:manager,default')->group(function () {
            Route::get('bookings', [BookingController::class, 'index'])->name('bookings');
            Route::get('bookings/export', [BookingController::class, 'export'])->name('bookings.export');
            Route::post('bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
            Route::post('bookings/{booking}/outcome', [BookingController::class, 'outcome'])->name('bookings.outcome');
            Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
            Route::post('bookings/{booking}/anonymise', [BookingController::class, 'anonymise'])->name('bookings.anonymise');
            Route::post('bookings/{booking}/session-operators', [BookingSessionController::class, 'store'])->name('bookings.session.store');
            Route::delete('bookings/{booking}/session-operators/{user}', [BookingSessionController::class, 'destroy'])->name('bookings.session.destroy');
            Route::get('availability', [AvailabilityController::class, 'index'])->name('availability');
            Route::put('availability/schedule', [AvailabilityController::class, 'saveSchedule'])->name('availability.schedule');
            Route::post('availability/exceptions', [AvailabilityController::class, 'storeException'])->name('availability.exceptions.store');
            Route::delete('availability/exceptions/{exception}', [AvailabilityController::class, 'destroyException'])->name('availability.exceptions.destroy');
            Route::post('availability/blocks', [AvailabilityController::class, 'storeTimeBlock'])->name('availability.blocks.store');
            Route::delete('availability/blocks/{block}', [AvailabilityController::class, 'destroyTimeBlock'])->name('availability.blocks.destroy');
            Route::post('availability/closures', [AvailabilityController::class, 'storeClosure'])->name('availability.closures.store');
            Route::delete('availability/closures/{closure}', [AvailabilityController::class, 'destroyClosure'])->name('availability.closures.destroy');
            Route::get('reports', ReportController::class)->name('reports');
        });
    });
