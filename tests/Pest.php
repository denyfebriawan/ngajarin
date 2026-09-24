<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
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

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
|
| Functions shared by several test files. A function declared in one test file is global, so
| declaring it again in another would be a fatal "cannot redeclare" error; shared ones live here.
|
*/

/**
 * A new user who belongs to the tenant with the given role.
 */
function memberOf(Tenant $tenant, Role $role): User
{
    $user = User::factory()->create();
    $tenant->addMember($user, $role);

    return $user;
}
