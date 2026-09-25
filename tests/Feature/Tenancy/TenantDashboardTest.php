<?php

use App\Enums\Role;
use App\Models\Booking;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Thursday 1 Oct 2026, 10:00 in Jakarta. This week runs Mon 28 Sep to Sun 4 Oct.
    $this->travelTo(CarbonImmutable::parse('2026-10-01 10:00', 'Asia/Jakarta'));

    $this->tenant = Tenant::factory()->create(['timezone' => 'Asia/Jakarta']);
    $this->owner = memberOf($this->tenant, Role::Owner);
    $this->teacher = memberOf($this->tenant, Role::Tutor);
    $this->student = memberOf($this->tenant, Role::Student);
    $this->subject = Subject::factory()->for($this->tenant)->create(['name' => 'Math Grade 10']);
});

// A confirmed lesson at a local Jakarta time (end = start + 1 hour).
function lessonAtLocal(string $start, int $price = 100_000, ?User $teacher = null, ?User $student = null): Booking
{
    $startsAt = CarbonImmutable::parse($start, 'Asia/Jakarta');

    return Booking::factory()->create([
        'tenant_id' => test()->tenant->id,
        'subject_id' => test()->subject->id,
        'teacher_id' => ($teacher ?? test()->teacher)->id,
        'student_id' => ($student ?? test()->student)->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addHour(),
        'price' => $price,
    ]);
}

function overviewFor(User $user)
{
    return test()->actingAs($user)->get(route('tenant.dashboard', test()->tenant));
}

test('the numbers use the workspace\'s today, week and month', function () {
    $otherStudent = memberOf($this->tenant, Role::Student);

    lessonAtLocal('2026-10-01 13:00', 100_000);            // today
    lessonAtLocal('2026-10-01 23:00', 200_000, student: $otherStudent); // today, though 16:00 UTC
    lessonAtLocal('2026-10-02 09:00', 150_000);            // this week, this month
    lessonAtLocal('2026-10-05 09:00', 150_000);            // next week, this month
    lessonAtLocal('2026-09-29 09:00', 999_000);            // this week, but last month
    lessonAtLocal('2026-10-01 15:00', 999_000)->cancel();   // cancelled: never counted

    overviewFor($this->owner)->assertInertia(fn (Assert $page) => $page
        ->component('tenant/dashboard')
        ->where('stats.today', 2)
        ->where('stats.this_week', 4)
        ->where('stats.booked_this_month', 600_000)
        ->where('stats.students_this_month', 2),
    );
});

test('a lesson just after midnight local time belongs to tomorrow, even on the same UTC date', function () {
    // 2 Oct 00:30 in Jakarta is 1 Oct 17:30 UTC: the same UTC day as "now", but not today locally.
    lessonAtLocal('2026-10-02 00:30');

    overviewFor($this->owner)->assertInertia(fn (Assert $page) => $page
        ->where('stats.today', 0)
        ->where('today', []),
    );
});

test('tutors and students see only their own lessons, and no money', function (string $viewer, int $today) {
    $colleague = memberOf($this->tenant, Role::Tutor);
    lessonAtLocal('2026-10-01 13:00');                        // teacher + student
    lessonAtLocal('2026-10-01 15:00', teacher: $colleague, student: memberOf($this->tenant, Role::Student));

    overviewFor($this->{$viewer})->assertInertia(fn (Assert $page) => $page
        ->where('stats.today', $today)
        ->where('stats.booked_this_month', null)
        ->where('stats.students_this_month', null),
    );
})->with([
    'the tutor' => ['teacher', 1],
    'the student' => ['student', 1],
]);

test('the next lesson and today\'s schedule are shown in local time', function () {
    lessonAtLocal('2026-10-01 15:00');
    lessonAtLocal('2026-10-01 13:00');
    lessonAtLocal('2026-10-01 08:00'); // already over: in today's list, but not "next"

    overviewFor($this->student)->assertInertia(fn (Assert $page) => $page
        ->where('next.starts', '13:00')
        ->where('next.ends', '14:00')
        ->where('next.subject', 'Math Grade 10')
        ->where('next.teacher.id', $this->teacher->id)
        ->has('today', 3)
        ->where('today.0.starts', '08:00')
        ->where('today.2.starts', '15:00'),
    );
});

test('another workspace\'s lessons are never counted', function () {
    $elsewhere = Tenant::factory()->create(['timezone' => 'Asia/Jakarta']);
    Booking::factory()->create([
        'tenant_id' => $elsewhere->id,
        'subject_id' => Subject::factory()->for($elsewhere)->create()->id,
        'teacher_id' => memberOf($elsewhere, Role::Owner)->id,
        'student_id' => memberOf($elsewhere, Role::Student)->id,
        'starts_at' => CarbonImmutable::parse('2026-10-01 13:00', 'Asia/Jakarta'),
        'ends_at' => CarbonImmutable::parse('2026-10-01 14:00', 'Asia/Jakarta'),
    ]);

    overviewFor($this->owner)->assertInertia(fn (Assert $page) => $page
        ->where('stats.today', 0)
        ->where('stats.booked_this_month', 0)
        ->where('next', null),
    );
});

test('an empty workspace shows zeros and no next lesson', function () {
    overviewFor($this->owner)->assertInertia(fn (Assert $page) => $page
        ->where('stats', ['today' => 0, 'this_week' => 0, 'booked_this_month' => 0, 'students_this_month' => 0])
        ->where('next', null)
        ->where('today', []),
    );
});
