<?php

use App\Enums\Role;
use App\Models\Booking;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

// A confirmed lesson in $tenant at a local time there (one hour).
function upcomingLesson(Tenant $tenant, User $teacher, User $student, string $localStart, ?string $subject = null): Booking
{
    $start = CarbonImmutable::parse($localStart, $tenant->timezone);

    // Subject names are unique per workspace, so only set one when the test cares about it.
    $subjectAttributes = $subject === null ? [] : ['name' => $subject];

    return Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'subject_id' => Subject::factory()->for($tenant)->create($subjectAttributes)->id,
        'teacher_id' => $teacher->id,
        'student_id' => $student->id,
        'starts_at' => $start,
        'ends_at' => $start->addHour(),
    ]);
}

test('the dashboard lists the user\'s upcoming lessons across all their workspaces', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 10:00', 'Asia/Jakarta'));
    $student = User::factory()->create();

    $math = Tenant::factory()->create(['name' => 'Budi Math', 'slug' => 'budi-math', 'timezone' => 'Asia/Jakarta']);
    $math->addMember($student, Role::Student);
    $piano = Tenant::factory()->create(['name' => 'Ani Piano', 'slug' => 'ani-piano', 'timezone' => 'Asia/Makassar']);
    $piano->addMember($student, Role::Student);

    upcomingLesson($piano, memberOf($piano, Role::Owner), $student, '2026-10-03 09:00', 'Piano');
    upcomingLesson($math, memberOf($math, Role::Owner), $student, '2026-10-02 15:00', 'Math Grade 10');

    $this->actingAs($student)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('lessons', 2)
            // Soonest first, each in its own workspace's local time.
            ->where('lessons.0.tenant.slug', 'budi-math')
            ->where('lessons.0.subject', 'Math Grade 10')
            ->where('lessons.0.starts', '15:00')
            ->where('lessons.0.teaching', false)
            ->where('lessons.1.tenant.slug', 'ani-piano')
            ->where('lessons.1.starts', '09:00'),
        );
});

test('lessons the user teaches are listed too, marked as teaching', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 10:00', 'Asia/Jakarta'));
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Jakarta']);
    $teacher = memberOf($tenant, Role::Owner);
    $student = memberOf($tenant, Role::Student);
    upcomingLesson($tenant, $teacher, $student, '2026-10-02 09:00');

    $this->actingAs($teacher)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('lessons.0.teaching', true)
            ->where('lessons.0.with', $student->name),
        );
});

test('only the user\'s own, upcoming, confirmed lessons are listed', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 10:00', 'Asia/Jakarta'));
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Jakarta']);
    $teacher = memberOf($tenant, Role::Owner);
    $student = memberOf($tenant, Role::Student);

    upcomingLesson($tenant, $teacher, $student, '2026-09-30 09:00');                          // past
    upcomingLesson($tenant, $teacher, $student, '2026-10-02 09:00')->cancel();                // cancelled
    upcomingLesson($tenant, $teacher, memberOf($tenant, Role::Student), '2026-10-02 11:00');  // someone else's

    $this->actingAs($student)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('lessons', 0));
});

test('every page knows the user\'s role in each of their workspaces', function () {
    $user = User::factory()->create();
    Tenant::factory()->create(['name' => 'Zeta Tutoring'])->addMember($user, Role::Student);
    Tenant::factory()->create(['name' => 'Alpha Math'])->addMember($user, Role::Owner);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenants.0.name', 'Alpha Math')
            ->where('tenants.0.role', 'owner')
            ->where('tenants.1.name', 'Zeta Tutoring')
            ->where('tenants.1.role', 'student'),
        );
});
