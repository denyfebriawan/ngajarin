<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

test('a user can belong to several tenants with a different role in each', function () {
    $user = User::factory()->create();
    $mathCenter = Tenant::factory()->create();
    $englishTutor = Tenant::factory()->create();

    $mathCenter->addMember($user, Role::Tutor);
    $englishTutor->addMember($user, Role::Student);

    expect($user->tenants)->toHaveCount(2)
        ->and($user->roleIn($mathCenter))->toBe(Role::Tutor)
        ->and($user->roleIn($englishTutor))->toBe(Role::Student);
});

test('a user has no role in a tenant they do not belong to', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    expect($user->roleIn($tenant))->toBeNull();
});

test('a user cannot be added to the same tenant twice', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $tenant->addMember($user, Role::Student);
    $tenant->addMember($user, Role::Owner);
})->throws(UniqueConstraintViolationException::class);

test('the database rejects a role that does not exist', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    DB::table('tenant_user')->insert([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => 'admin',
    ]);
})->throws(QueryException::class, 'tenant_user_role_check');

test('tenant slugs are unique', function () {
    Tenant::factory()->create(['slug' => 'budi-math']);
    Tenant::factory()->create(['slug' => 'budi-math']);
})->throws(UniqueConstraintViolationException::class);

test('deleting a tenant removes its memberships but keeps the users', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->addMember($user, Role::Owner);

    $tenant->delete();

    expect(DB::table('tenant_user')->count())->toBe(0)
        ->and($user->fresh())->not->toBeNull();
});

test('tenants are identified by their slug in URLs', function () {
    $tenant = Tenant::factory()->create(['slug' => 'budi-math']);

    expect($tenant->getRouteKey())->toBe('budi-math');
});
