<?php

use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // "Workspace" in URLs and the UI, "tenant" in code.
    Route::inertia('workspaces/create', 'tenants/create')->name('tenants.create');
    Route::post('workspaces', [TenantController::class, 'store'])->name('tenants.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/tenant.php';
