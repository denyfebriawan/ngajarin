<?php

use App\Enums\Role;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => 'budi-math']);
    $this->owner = memberOf($this->tenant, Role::Owner);
    $this->tutor = memberOf($this->tenant, Role::Tutor);
});

function subjectWithTeachers(array $teacherIds): array
{
    return [
        'name' => 'Math Grade 10',
        'duration_minutes' => 60,
        'price' => 150_000,
        'teacher_ids' => $teacherIds,
    ];
}

function teacherIdsOf(Subject $subject): array
{
    return $subject->teachers()->pluck('users.id')->sort()->values()->all();
}

test('owners can pick the teachers when creating a subject, including themselves', function () {
    $this->actingAs($this->owner)
        ->post(route('tenant.subjects.store', $this->tenant), subjectWithTeachers([$this->owner->id, $this->tutor->id]))
        ->assertSessionHasNoErrors();

    $subject = Subject::withoutGlobalScopes()->sole();

    expect(teacherIdsOf($subject))->toBe(collect([$this->owner->id, $this->tutor->id])->sort()->values()->all())
        ->and(DB::table('subject_teacher')->pluck('tenant_id')->unique()->all())->toBe([$this->tenant->id]);
});

test('updating replaces the teachers, and unticking all removes them', function () {
    $subject = Subject::factory()->for($this->tenant)->create();
    $subject->syncTeachers([$this->owner->id]);
    $route = ['tenant' => $this->tenant, 'subject' => $subject];

    $this->actingAs($this->owner)
        ->patch(route('tenant.subjects.update', $route), subjectWithTeachers([$this->tutor->id]))
        ->assertSessionHasNoErrors();
    expect(teacherIdsOf($subject))->toBe([$this->tutor->id]);

    // An unticked checkbox list sends no teacher_ids at all.
    $data = subjectWithTeachers([]);
    unset($data['teacher_ids']);
    $this->patch(route('tenant.subjects.update', $route), $data)->assertSessionHasNoErrors();
    expect(teacherIdsOf($subject))->toBe([]);
});

test('only this workspace\'s owners and tutors can be picked', function (Closure $makeUser) {
    $user = $makeUser($this->tenant);

    $this->actingAs($this->owner)
        ->post(route('tenant.subjects.store', $this->tenant), subjectWithTeachers([$user->id]))
        ->assertSessionHasErrors('teacher_ids.0');

    expect(Subject::withoutGlobalScopes()->count())->toBe(0);
})->with([
    // Closures, because users can only be created once the test (and its database) is running.
    'a student' => [fn (Tenant $tenant) => memberOf($tenant, Role::Student)],
    'a tutor of another workspace' => [fn () => memberOf(Tenant::factory()->create(), Role::Tutor)],
    'a user in no workspace' => [fn () => User::factory()->create()],
]);

test('the database refuses to link a subject to a member of another workspace', function () {
    $subject = Subject::factory()->for($this->tenant)->create();
    $otherTenant = Tenant::factory()->create();
    $outsider = memberOf($otherTenant, Role::Tutor);

    // Even with tenant_id set to the other workspace, the subject does not exist there.
    DB::table('subject_teacher')->insert([
        'tenant_id' => $otherTenant->id,
        'subject_id' => $subject->id,
        'user_id' => $outsider->id,
    ]);
})->throws(QueryException::class, 'subject_teacher_tenant_id_subject_id_foreign');

test('leaving a workspace removes the member from its subjects', function () {
    $subject = Subject::factory()->for($this->tenant)->create();
    $subject->syncTeachers([$this->owner->id, $this->tutor->id]);

    $this->tenant->users()->detach($this->tutor);

    expect(teacherIdsOf($subject))->toBe([$this->owner->id]);
});

test('the subject list shows each subject\'s teachers without leaking pivot data', function () {
    $subject = Subject::factory()->for($this->tenant)->create();
    $subject->syncTeachers([$this->tutor->id]);

    $this->actingAs($this->owner)
        ->get(route('tenant.subjects.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('subjects.0.teachers', [['id' => $this->tutor->id, 'name' => $this->tutor->name]]),
        );
});

test('the subject list does not run a query per subject', function () {
    $queriesFor = function (int $subjects): int {
        Subject::withoutGlobalScopes()->delete();
        Subject::factory()->for($this->tenant)->count($subjects)->create()
            ->each(fn (Subject $subject) => $subject->syncTeachers([$this->owner->id]));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->owner)->get(route('tenant.subjects.index', $this->tenant))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    expect($queriesFor(10))->toBe($queriesFor(1));
});

test('the form offers only this workspace\'s owners and tutors as teachers', function () {
    memberOf($this->tenant, Role::Student);
    memberOf(Tenant::factory()->create(), Role::Tutor);

    $this->actingAs($this->owner)
        ->get(route('tenant.subjects.create', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->has('teachers', 2)
            ->where('teachers', fn ($teachers) => collect($teachers)->pluck('id')->sort()->values()->all()
                === collect([$this->owner->id, $this->tutor->id])->sort()->values()->all()),
        );
});
