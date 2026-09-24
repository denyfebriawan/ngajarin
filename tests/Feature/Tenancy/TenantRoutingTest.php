<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('tenant URLs use the slug', function () {
    $tenant = Tenant::factory()->create(['slug' => 'budi-math']);

    expect(route('tenant.dashboard', $tenant, absolute: false))->toBe('/t/budi-math');
});

test('guests are redirected to the login page', function () {
    $tenant = Tenant::factory()->create();

    $this->get(route('tenant.dashboard', $tenant))->assertRedirect(route('login'));
});

test('members can open their tenant and see it with their role', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['name' => 'Budi Math', 'slug' => 'budi-math']);
    $tenant->addMember($user, Role::Tutor);

    $this->actingAs($user)
        ->get(route('tenant.dashboard', $tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/dashboard')
            ->where('currentTenant', [
                'name' => 'Budi Math',
                'slug' => 'budi-math',
                'timezone' => 'Asia/Jakarta',
                'role' => 'tutor',
                'can' => ['update' => false, 'manageSubjects' => false],
            ]),
        );
});

test('users cannot open a tenant they do not belong to', function () {
    $user = User::factory()->create();
    $ownTenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $ownTenant->addMember($user, Role::Owner);

    $this->actingAs($user)
        ->get(route('tenant.dashboard', $otherTenant))
        ->assertForbidden();
});

test('an unknown tenant slug returns not found', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/t/does-not-exist')->assertNotFound();
});

test('pages outside tenant routes have no current tenant', function () {
    $user = User::factory()->create();
    Tenant::factory()->create()->addMember($user, Role::Owner);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('currentTenant', null));
});
