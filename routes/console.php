<?php

use App\Demo\DemoWorkspace;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:reset', function (DemoWorkspace $demo) {
    $tenant = $demo->reset();

    $this->info("Demo workspace rebuilt at /t/{$tenant->slug}.");
})->purpose('Delete the demo workspace and accounts and build them again, dated from today');

// Keeps the live demo fresh: its lessons move along with the calendar, and whatever visitors
// changed during the day is undone.
Schedule::command('demo:reset')
    ->dailyAt('03:00')
    ->timezone('Asia/Jakarta')
    ->when(fn (): bool => (bool) config('demo.enabled'));
