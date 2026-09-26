<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo workspace
    |--------------------------------------------------------------------------
    |
    | When enabled, the demo workspace (App\Demo\DemoWorkspace) is rebuilt every night, so its
    | lessons stay current and whatever visitors changed is undone. Switch it on for the live
    | site only; locally, run `php artisan demo:reset` (or `db:seed`) whenever you want it.
    |
    */

    'enabled' => (bool) env('DEMO_ENABLED', false),

];
