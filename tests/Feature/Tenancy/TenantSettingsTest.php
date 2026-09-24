<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Old Name', 'slug' => 'budi-math']);
});

test('only owners may update the tenant', function (Role $role, bool $allowed) {
    $user = memberOf($this->tenant, $role);

    expect($user->can('update', $this->tenant))->toBe($allowed);
})->with([
    'owner' => [Role::Owner, true],
    'tutor' => [Role::Tutor, false],
    'student' => [Role::Student, false],
]);

test('owners can open the settings page', function () {
    $owner = memberOf($this->tenant, Role::Owner);

    $this->actingAs($owner)
        ->get(route('tenant.settings.edit', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('tenant/settings'));
});

test('tutors and students cannot open the settings page', function (Role $role) {
    $user = memberOf($this->tenant, $role);

    $this->actingAs($user)
        ->get(route('tenant.settings.edit', $this->tenant))
        ->assertForbidden();
})->with([Role::Tutor, Role::Student]);

test('owners can rename the tenant without changing its slug', function () {
    $owner = memberOf($this->tenant, Role::Owner);

    $this->actingAs($owner)
        ->from(route('tenant.dashboard', $this->tenant))
        ->patch(route('tenant.settings.update', $this->tenant), [
            'name' => 'New Name',
            'timezone' => 'Asia/Jakarta',
            'slug' => 'hijacked',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.dashboard', $this->tenant));

    expect($this->tenant->fresh())
        ->name->toBe('New Name')
        ->slug->toBe('budi-math');
});

test('tutors and students cannot rename the tenant', function (Role $role) {
    $user = memberOf($this->tenant, $role);

    $this->actingAs($user)
        ->patch(route('tenant.settings.update', $this->tenant), ['name' => 'New Name'])
        ->assertForbidden();

    expect($this->tenant->fresh()->name)->toBe('Old Name');
})->with([Role::Tutor, Role::Student]);

test('a name is required', function () {
    $owner = memberOf($this->tenant, Role::Owner);

    $this->actingAs($owner)
        ->patch(route('tenant.settings.update', $this->tenant), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('the frontend is told which actions the user may take', function (Role $role, bool $canUpdate) {
    $user = memberOf($this->tenant, $role);

    $this->actingAs($user)
        ->get(route('tenant.dashboard', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('currentTenant.can.update', $canUpdate));
})->with([
    'owner' => [Role::Owner, true],
    'tutor' => [Role::Tutor, false],
]);

test('new workspaces use Western Indonesian Time', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('tenants.store'), ['name' => 'Ani English', 'slug' => 'ani-english']);

    expect(Tenant::firstWhere('slug', 'ani-english')->timezone)->toBe('Asia/Jakarta');
});

test('owners can change the timezone to another Indonesian zone', function () {
    $owner = memberOf($this->tenant, Role::Owner);

    $this->actingAs($owner)
        ->patch(route('tenant.settings.update', $this->tenant), ['name' => 'Old Name', 'timezone' => 'Asia/Makassar'])
        ->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->timezone)->toBe('Asia/Makassar');
});

test('only the listed timezones are accepted', function (string $timezone) {
    $owner = memberOf($this->tenant, Role::Owner);

    $this->actingAs($owner)
        ->patch(route('tenant.settings.update', $this->tenant), ['name' => 'Old Name', 'timezone' => $timezone])
        ->assertSessionHasErrors('timezone');

    expect($this->tenant->fresh()->timezone)->toBe('Asia/Jakarta');
})->with(['Europe/London', 'Mars/Olympus', '']);

test('the settings page offers the timezones', function () {
    $owner = memberOf($this->tenant, Role::Owner);

    $this->actingAs($owner)
        ->get(route('tenant.settings.edit', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('timezones', Tenant::TIMEZONES)
            ->where('currentTenant.timezone', 'Asia/Jakarta'),
        );
});
