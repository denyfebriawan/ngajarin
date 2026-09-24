<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('the create workspace page can be rendered', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('tenants.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('tenants/create'));
});

test('guests cannot create a workspace', function () {
    $this->post(route('tenants.store'), ['name' => 'Budi Math', 'slug' => 'budi-math'])
        ->assertRedirect(route('login'));

    expect(Tenant::count())->toBe(0);
});

test('creating a workspace makes the user its owner and opens it', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('tenants.store'), ['name' => 'Budi Math', 'slug' => 'budi-math']);

    $tenant = Tenant::firstWhere('slug', 'budi-math');

    expect($tenant->name)->toBe('Budi Math')
        ->and($user->roleIn($tenant))->toBe(Role::Owner);
    $response->assertRedirect(route('tenant.dashboard', $tenant));
});

test('the slug must be a valid web address', function (string $slug) {
    $this->actingAs(User::factory()->create())
        ->post(route('tenants.store'), ['name' => 'Budi Math', 'slug' => $slug])
        ->assertSessionHasErrors('slug');
})->with([
    'too short' => 'ab',
    'uppercase' => 'Budi-Math',
    'spaces' => 'budi math',
    'leading hyphen' => '-budi',
    'double hyphen' => 'budi--math',
    'too long' => str_repeat('a', 51),
]);

test('the slug must not be taken', function () {
    Tenant::factory()->create(['slug' => 'budi-math']);

    $this->actingAs(User::factory()->create())
        ->post(route('tenants.store'), ['name' => 'Another Budi', 'slug' => 'budi-math'])
        ->assertSessionHasErrors(['slug' => 'This address is already taken.']);
});

test('a slug taken at the same moment becomes a validation error, not a crash', function () {
    // Simulate a second request winning the race: after validation has passed, just before our
    // insert, another tenant with the same slug appears. Only the unique index can catch this.
    Tenant::creating(function () {
        DB::table('tenants')->insert(['name' => 'Winner', 'slug' => 'budi-math']);
    });

    $this->actingAs(User::factory()->create())
        ->post(route('tenants.store'), ['name' => 'Budi Math', 'slug' => 'budi-math'])
        ->assertSessionHasErrors(['slug' => 'This address is already taken.']);

    // The whole transaction rolled back: no half-created workspace or membership.
    expect(DB::table('tenant_user')->count())->toBe(0);
});

test('the sidebar lists only the user\'s own workspaces, by name', function () {
    $user = User::factory()->create();
    Tenant::factory()->create(['name' => 'Zeta Tutoring'])->addMember($user, Role::Student);
    Tenant::factory()->create(['name' => 'Alpha Math'])->addMember($user, Role::Owner);
    Tenant::factory()->create(['name' => 'Someone Else']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tenants', 2)
            ->where('tenants.0.name', 'Alpha Math')
            ->where('tenants.1.name', 'Zeta Tutoring'),
        );
});
