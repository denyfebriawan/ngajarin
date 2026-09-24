<?php

use App\Enums\Role;
use App\Models\Subject;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => 'budi-math']);
    $this->owner = memberOf($this->tenant, Role::Owner);
});

function validSubject(array $overrides = []): array
{
    return [
        'name' => 'Math Grade 10',
        'description' => 'Algebra and geometry.',
        'duration_minutes' => 60,
        'price' => 150_000,
        ...$overrides,
    ];
}

test('every member can list the workspace\'s subjects, and only its own', function (Role $role) {
    $member = memberOf($this->tenant, $role);
    Subject::factory()->for($this->tenant)->create(['name' => 'Math Grade 10']);
    Subject::factory()->create(['name' => 'Someone else\'s subject']);

    $this->actingAs($member)
        ->get(route('tenant.subjects.index', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/subjects/index')
            ->has('subjects', 1)
            ->where('subjects.0.name', 'Math Grade 10')
            ->missing('subjects.0.tenant_id'),
        );
})->with([Role::Owner, Role::Tutor, Role::Student]);

test('owners can create a subject', function () {
    $this->actingAs($this->owner)
        ->post(route('tenant.subjects.store', $this->tenant), validSubject())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.subjects.index', $this->tenant));

    $subject = Subject::withoutGlobalScopes()->sole();

    expect($subject)
        ->tenant_id->toBe($this->tenant->id)
        ->name->toBe('Math Grade 10')
        ->duration_minutes->toBe(60)
        ->price->toBe(150_000);
});

test('tutors and students cannot create, edit or delete subjects', function (Role $role) {
    $member = memberOf($this->tenant, $role);
    $subject = Subject::factory()->for($this->tenant)->create();
    $route = ['tenant' => $this->tenant, 'subject' => $subject];

    $this->actingAs($member);
    $this->get(route('tenant.subjects.create', $this->tenant))->assertForbidden();
    $this->post(route('tenant.subjects.store', $this->tenant), validSubject())->assertForbidden();
    $this->get(route('tenant.subjects.edit', $route))->assertForbidden();
    $this->patch(route('tenant.subjects.update', $route), validSubject())->assertForbidden();
    $this->delete(route('tenant.subjects.destroy', $route))->assertForbidden();

    expect(Subject::withoutGlobalScopes()->count())->toBe(1);
})->with([Role::Tutor, Role::Student]);

test('invalid subjects are rejected', function (array $overrides, string $field) {
    $this->actingAs($this->owner)
        ->post(route('tenant.subjects.store', $this->tenant), validSubject($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'lesson too short' => [['duration_minutes' => 10], 'duration_minutes'],
    'lesson too long' => [['duration_minutes' => 485], 'duration_minutes'],
    'odd minutes' => [['duration_minutes' => 47], 'duration_minutes'],
    'negative price' => [['price' => -1], 'price'],
    'decimal price' => [['price' => 1500.5], 'price'],
]);

test('names are unique within a workspace but may repeat across workspaces', function () {
    Subject::factory()->for($this->tenant)->create(['name' => 'Math Grade 10']);
    Subject::factory()->create(['name' => 'Physics']);

    $this->actingAs($this->owner)
        ->post(route('tenant.subjects.store', $this->tenant), validSubject(['name' => 'Math Grade 10']))
        ->assertSessionHasErrors('name');

    $this->actingAs($this->owner)
        ->post(route('tenant.subjects.store', $this->tenant), validSubject(['name' => 'Physics']))
        ->assertSessionHasNoErrors();
});

test('owners can update a subject, keeping its own name', function () {
    $subject = Subject::factory()->for($this->tenant)->create(['name' => 'Math Grade 10']);

    $this->actingAs($this->owner)
        ->patch(
            route('tenant.subjects.update', ['tenant' => $this->tenant, 'subject' => $subject]),
            validSubject(['price' => 175_000]),
        )
        ->assertSessionHasNoErrors();

    expect($subject->fresh()->price)->toBe(175_000);
});

test('owners can delete a subject', function () {
    $subject = Subject::factory()->for($this->tenant)->create();

    $this->actingAs($this->owner)
        ->delete(route('tenant.subjects.destroy', ['tenant' => $this->tenant, 'subject' => $subject]))
        ->assertRedirect(route('tenant.subjects.index', $this->tenant));

    expect(Subject::withoutGlobalScopes()->count())->toBe(0);
});

test('another workspace\'s subject cannot be edited through this workspace\'s URL', function () {
    $foreign = Subject::factory()->create();
    $route = ['tenant' => $this->tenant, 'subject' => $foreign];

    $this->actingAs($this->owner);
    $this->get(route('tenant.subjects.edit', $route))->assertNotFound();
    $this->patch(route('tenant.subjects.update', $route), validSubject())->assertNotFound();
    $this->delete(route('tenant.subjects.destroy', $route))->assertNotFound();

    expect($foreign->fresh())->not->toBeNull();
});

test('the database rejects out-of-range durations and prices', function (array $overrides) {
    DB::table('subjects')->insert([
        'tenant_id' => $this->tenant->id,
        'name' => 'Raw insert',
        'duration_minutes' => 60,
        'price' => 100_000,
        ...$overrides,
    ]);
})->with([
    'duration' => [['duration_minutes' => 5]],
    'price' => [['price' => -1]],
])->throws(QueryException::class);

test('the frontend is told who may manage subjects', function (Role $role, bool $canManage) {
    $member = $role === Role::Owner ? $this->owner : memberOf($this->tenant, $role);

    $this->actingAs($member)
        ->get(route('tenant.subjects.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('currentTenant.can.manageSubjects', $canManage));
})->with([
    'owner' => [Role::Owner, true],
    'tutor' => [Role::Tutor, false],
]);
