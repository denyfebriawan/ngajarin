<?php

use App\Http\Controllers\Tenant\SubjectController;
use App\Http\Controllers\Tenant\TenantSettingsController;
use App\Http\Middleware\EnsureTenantMember;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureTenantMember::class])
    ->prefix('t/{tenant}')
    ->name('tenant.')
    ->group(function () {
        Route::inertia('/', 'tenant/dashboard')->name('dashboard');

        Route::get('settings', [TenantSettingsController::class, 'edit'])
            ->can('update', 'tenant')
            ->name('settings.edit');

        Route::patch('settings', [TenantSettingsController::class, 'update'])
            ->can('update', 'tenant')
            ->name('settings.update');

        // Every member can see the subjects; only owners can change them.
        Route::get('subjects', [SubjectController::class, 'index'])->name('subjects.index');

        Route::middleware('can:manageSubjects,tenant')->group(function () {
            Route::resource('subjects', SubjectController::class)->except(['index', 'show']);
        });
    });
