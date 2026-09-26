<?php

namespace Database\Seeders;

use App\Demo\DemoWorkspace;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(DemoWorkspace $demo): void
    {
        // The same data as the live demo; run `php artisan db:seed` again to rebuild it.
        $demo->reset();
    }
}
