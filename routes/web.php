<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoLoginController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// One-click logins to the shared demo accounts (the controller returns 404 unless the demo is on).
Route::post('demo/{account}', DemoLoginController::class)
    ->whereIn('account', ['tutor', 'student'])
    ->middleware(['guest', 'throttle:10,1'])
    ->name('demo.login');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // "Workspace" in URLs and the UI, "tenant" in code.
    Route::inertia('workspaces/create', 'tenants/create')->name('tenants.create');
    Route::post('workspaces', [TenantController::class, 'store'])->name('tenants.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/tenant.php';
