<?php

use App\Http\Controllers\Tenant\AvailabilityController;
use App\Http\Controllers\Tenant\BookingController;
use App\Http\Controllers\Tenant\LessonController;
use App\Http\Controllers\Tenant\SubjectController;
use App\Http\Controllers\Tenant\TenantDashboardController;
use App\Http\Controllers\Tenant\TenantSettingsController;
use App\Http\Controllers\Tenant\TimeOffController;
use App\Http\Middleware\EnsureTenantMember;
use App\Http\Middleware\PreventDemoChanges;
use Illuminate\Support\Facades\Route;

// The public booking page: no membership check, since visitors aren't members yet. Looking is
// open to everyone; booking needs a verified account (and joins the workspace as a student).
Route::prefix('t/{tenant}')->name('tenant.')->group(function () {
    Route::get('book', [BookingController::class, 'create'])->name('book');
    Route::post('book', [BookingController::class, 'store'])
        ->middleware(['auth', 'verified', 'throttle:10,1'])
        ->name('book.store');
});

Route::middleware(['auth', 'verified', EnsureTenantMember::class])
    ->prefix('t/{tenant}')
    ->name('tenant.')
    ->group(function () {
        Route::get('/', TenantDashboardController::class)->name('dashboard');

        Route::get('settings', [TenantSettingsController::class, 'edit'])
            ->can('update', 'tenant')
            ->name('settings.edit');

        Route::patch('settings', [TenantSettingsController::class, 'update'])
            ->can('update', 'tenant')
            ->middleware(PreventDemoChanges::class)
            ->name('settings.update');

        // Every member can see the subjects; only owners can change them.
        Route::get('subjects', [SubjectController::class, 'index'])->name('subjects.index');

        Route::middleware('can:manageSubjects,tenant')->group(function () {
            Route::resource('subjects', SubjectController::class)->except(['index', 'show']);
        });

        // Every member sees the lessons they may see; the policy decides who may cancel which.
        Route::get('lessons', [LessonController::class, 'index'])->name('lessons.index');
        Route::patch('lessons/{booking}/cancel', [LessonController::class, 'cancel'])
            ->can('cancel', 'booking')
            ->name('lessons.cancel');

        // Each teacher (owner or tutor) edits their own weekly hours.
        Route::middleware('can:teach,tenant')->group(function () {
            Route::get('availability', [AvailabilityController::class, 'edit'])->name('availability.edit');
            Route::put('availability', [AvailabilityController::class, 'update'])->name('availability.update');

            Route::get('time-off', [TimeOffController::class, 'index'])->name('time-off.index');
            Route::post('time-off', [TimeOffController::class, 'store'])->name('time-off.store');
            Route::delete('time-off/{timeOff}', [TimeOffController::class, 'destroy'])
                ->can('delete', 'timeOff')
                ->name('time-off.destroy');
        });
    });
