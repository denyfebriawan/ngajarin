<?php

use App\Enums\Role;
use App\Models\AvailabilityRule;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => 'budi-math']);
    $this->teacher = memberOf($this->tenant, Role::Tutor);
});

function block(int $weekday, string $start, string $end): array
{
    return ['weekday' => $weekday, 'starts_at' => $start, 'ends_at' => $end];
}

function rulesOf(int $userId): array
{
    return AvailabilityRule::withoutGlobalScopes()
        ->where('user_id', $userId)
        ->orderBy('weekday')->orderBy('starts_at')
        ->get()
        ->map(fn (AvailabilityRule $rule) => block($rule->weekday, substr($rule->starts_at, 0, 5), substr($rule->ends_at, 0, 5)))
        ->all();
}

function saveWeek(array $blocks)
{
    return test()->put(route('tenant.availability.update', test()->tenant), ['blocks' => $blocks]);
}

test('owners and tutors can open their availability; students cannot', function (Role $role, bool $allowed) {
    $user = $role === Role::Tutor ? $this->teacher : memberOf($this->tenant, $role);

    $response = $this->actingAs($user)->get(route('tenant.availability.edit', $this->tenant));

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    'owner' => [Role::Owner, true],
    'tutor' => [Role::Tutor, true],
    'student' => [Role::Student, false],
]);

test('students cannot save availability either', function () {
    $this->actingAs(memberOf($this->tenant, Role::Student));

    saveWeek([block(1, '09:00', '12:00')])->assertForbidden();

    expect(AvailabilityRule::withoutGlobalScopes()->count())->toBe(0);
});

test('the page shows only the signed-in teacher\'s own hours, as HH:MM', function () {
    AvailabilityRule::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->teacher->id, 'weekday' => 3, 'starts_at' => '13:00', 'ends_at' => '15:30']);
    AvailabilityRule::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->teacher->id, 'weekday' => 1]);
    // Someone else's hours in the same workspace.
    $colleague = memberOf($this->tenant, Role::Owner);
    AvailabilityRule::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $colleague->id, 'weekday' => 2]);

    $this->actingAs($this->teacher)
        ->get(route('tenant.availability.edit', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/availability')
            ->where('blocks', [block(1, '09:00', '12:00'), block(3, '13:00', '15:30')]),
        );
});

test('saving replaces the whole week, and an empty week clears it', function () {
    $this->actingAs($this->teacher);

    saveWeek([block(1, '09:00', '12:00'), block(1, '13:00', '17:00')])->assertSessionHasNoErrors();
    expect(rulesOf($this->teacher->id))->toBe([block(1, '09:00', '12:00'), block(1, '13:00', '17:00')]);

    saveWeek([block(5, '08:00', '10:00')])->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.availability.edit', $this->tenant));
    expect(rulesOf($this->teacher->id))->toBe([block(5, '08:00', '10:00')]);

    saveWeek([])->assertSessionHasNoErrors();
    expect(rulesOf($this->teacher->id))->toBe([]);
});

test('saving never touches other teachers\' hours', function () {
    $colleague = memberOf($this->tenant, Role::Owner);
    AvailabilityRule::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $colleague->id]);

    $this->actingAs($this->teacher);
    saveWeek([])->assertSessionHasNoErrors();

    expect(rulesOf($colleague->id))->toHaveCount(1);
});

test('a teacher cannot save hours for someone else', function () {
    $colleague = memberOf($this->tenant, Role::Owner);

    $this->actingAs($this->teacher);
    saveWeek([['user_id' => $colleague->id, ...block(1, '09:00', '12:00')]])->assertSessionHasNoErrors();

    expect(rulesOf($colleague->id))->toBe([])
        ->and(rulesOf($this->teacher->id))->toHaveCount(1);
});

test('invalid blocks are rejected with an error on the right row', function (array $block, string $field) {
    $this->actingAs($this->teacher);

    saveWeek([block(1, '09:00', '10:00'), $block])->assertSessionHasErrors("blocks.1.$field");

    expect(rulesOf($this->teacher->id))->toBe([]);
})->with([
    'end before start' => [block(2, '12:00', '09:00'), 'ends_at'],
    'zero length' => [block(2, '09:00', '09:00'), 'ends_at'],
    'not a quarter hour' => [block(2, '09:10', '10:00'), 'starts_at'],
    'not a time' => [block(2, 'nine', '10:00'), 'starts_at'],
    'no such weekday' => [block(8, '09:00', '10:00'), 'weekday'],
]);

test('overlapping blocks in one save are rejected before touching the database', function () {
    $this->actingAs($this->teacher);

    saveWeek([block(1, '09:00', '12:00'), block(1, '11:00', '13:00')])
        ->assertSessionHasErrors(['blocks.1.starts_at' => 'This overlaps other hours on Monday.']);

    expect(rulesOf($this->teacher->id))->toBe([]);
});

test('touching blocks and the same hours on different days are fine', function () {
    $this->actingAs($this->teacher);

    saveWeek([block(1, '09:00', '12:00'), block(1, '12:00', '15:00'), block(2, '09:00', '12:00')])
        ->assertSessionHasNoErrors();

    expect(rulesOf($this->teacher->id))->toHaveCount(3);
});

test('a save that races another save becomes a friendly error, not a crash', function () {
    // Simulate a second tab saving at the same moment: just before our insert, an overlapping
    // block appears. Validation has already passed; only the database constraint can catch this.
    // (Registered before BelongsToTenant fills in tenant_id, so it uses the test's own values.)
    AvailabilityRule::creating(function () {
        DB::table('availability_rules')->insert([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->teacher->id,
            'weekday' => 1, 'starts_at' => '10:00', 'ends_at' => '11:00',
        ]);
    });

    $this->actingAs($this->teacher);
    saveWeek([block(1, '09:00', '12:00')])->assertSessionHasErrors('blocks');

    // The transaction rolled back: nothing was half-saved.
    expect(rulesOf($this->teacher->id))->toBe([]);
});

test('the frontend is told who can teach', function (Role $role, bool $canTeach) {
    $user = $role === Role::Tutor ? $this->teacher : memberOf($this->tenant, $role);

    $this->actingAs($user)
        ->get(route('tenant.dashboard', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('currentTenant.can.teach', $canTeach));
})->with([
    'owner' => [Role::Owner, true],
    'tutor' => [Role::Tutor, true],
    'student' => [Role::Student, false],
]);
