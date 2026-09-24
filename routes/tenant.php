<?php

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
    });
