<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Every test in tests/Feature runs inside Tests\TestCase (a booted Laravel app) and gets a
| freshly migrated database that is rolled back after each test. Unit tests stay plain PHP.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
