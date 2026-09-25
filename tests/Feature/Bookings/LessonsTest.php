<?php

use App\Enums\BookingStatus;
use App\Enums\Role;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\User;
use App\Scheduling\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Thursday 1 Oct 2026, 10:00 in Jakarta.
    $this->travelTo(CarbonImmutable::parse('2026-10-01 10:00', 'Asia/Jakarta'));

    $this->tenant = Tenant::factory()->create(['slug' => 'budi-math', 'timezone' => 'Asia/Jakarta']);
    $this->owner = memberOf($this->tenant, Role::Owner);
    $this->teacher = memberOf($this->tenant, Role::Tutor);
    $this->student = memberOf($this->tenant, Role::Student);
    // An explicit length: the factory picks 30-90 minutes at random, and a 90-minute lesson
    // doesn't fit the one-hour blocks some tests use, which made them fail 1 run in 4.
    $this->subject = Subject::factory()->for($this->tenant)->create(['name' => 'Math Grade 10', 'duration_minutes' => 60]);
});

// A lesson in $this->tenant at a local Jakarta date and time.
function lessonOn(string $date, string $start, string $end, array $attributes = []): Booking
{
    return Booking::factory()->create([
        'tenant_id' => test()->tenant->id,
        'subject_id' => test()->subject->id,
        'teacher_id' => test()->teacher->id,
        'student_id' => test()->student->id,
        'starts_at' => CarbonImmutable::parse("$date $start", 'Asia/Jakarta'),
        'ends_at' => CarbonImmutable::parse("$date $end", 'Asia/Jakarta'),
        ...$attributes,
    ]);
}

function lessonIds(array $lessons): array
{
    return collect($lessons)->pluck('id')->sort()->values()->all();
}

test('lessons show in the workspace\'s local time', function () {
    lessonOn('2026-10-05', '09:00', '10:00');

    $this->actingAs($this->student)
        ->get(route('tenant.lessons.index', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/lessons')
            ->where('upcoming.0.date', '2026-10-05')
            ->where('upcoming.0.starts', '09:00')
            ->where('upcoming.0.ends', '10:00')
            ->where('upcoming.0.subject', 'Math Grade 10')
            ->where('upcoming.0.teacher.id', $this->teacher->id)
            ->where('upcoming.0.status', 'confirmed'),
        );
});

test('each member sees the lessons they may see', function () {
    $mine = lessonOn('2026-10-05', '09:00', '10:00');
    $otherStudent = memberOf($this->tenant, Role::Student);
    $colleague = memberOf($this->tenant, Role::Tutor);
    $notMine = lessonOn('2026-10-05', '11:00', '12:00', ['student_id' => $otherStudent->id, 'teacher_id' => $colleague->id]);

    $visibleTo = fn (User $user) => $this->actingAs($user)
        ->get(route('tenant.lessons.index', $this->tenant))
        ->viewData('page')['props']['upcoming'];

    // Students see their own; teachers see the ones they teach; owners see everything.
    expect(lessonIds($visibleTo($this->student)))->toBe([$mine->id])
        ->and(lessonIds($visibleTo($this->teacher)))->toBe([$mine->id])
        ->and(lessonIds($visibleTo($colleague)))->toBe([$notMine->id])
        ->and(lessonIds($visibleTo($this->owner)))->toBe(lessonIds([['id' => $mine->id], ['id' => $notMine->id]]));
});

test('past and cancelled lessons are listed apart from upcoming ones', function () {
    $upcoming = lessonOn('2026-10-05', '09:00', '10:00');
    $cancelled = lessonOn('2026-10-06', '09:00', '10:00');
    $cancelled->cancel();
    $past = lessonOn('2026-09-28', '09:00', '10:00');

    $this->actingAs($this->student)
        ->get(route('tenant.lessons.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->has('upcoming', 1)
            ->where('upcoming.0.id', $upcoming->id)
            ->has('past', 2)
            ->where('past.0.id', $cancelled->id) // newest first
            ->where('past.0.status', 'cancelled')
            ->where('past.1.id', $past->id),
        );
});

test('another workspace\'s lessons never show', function () {
    $elsewhere = Tenant::factory()->create();
    $elsewhere->addMember($this->student, Role::Student);
    Booking::factory()->create([
        'tenant_id' => $elsewhere->id,
        'subject_id' => Subject::factory()->for($elsewhere)->create()->id,
        'teacher_id' => memberOf($elsewhere, Role::Owner)->id,
        'student_id' => $this->student->id,
    ]);

    $this->actingAs($this->student)
        ->get(route('tenant.lessons.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->has('upcoming', 0)->has('past', 0));
});

test('the student, the teacher and the owner can cancel an upcoming lesson', function (string $who) {
    $lesson = lessonOn('2026-10-05', '09:00', '10:00');

    $this->actingAs($this->{$who})
        ->from(route('tenant.lessons.index', $this->tenant))
        ->patch(route('tenant.lessons.cancel', ['tenant' => $this->tenant, 'booking' => $lesson]))
        ->assertRedirect(route('tenant.lessons.index', $this->tenant));

    expect($lesson->fresh()->status)->toBe(BookingStatus::Cancelled);
})->with(['student', 'teacher', 'owner']);

test('other members cannot cancel someone else\'s lesson', function (Role $role) {
    $lesson = lessonOn('2026-10-05', '09:00', '10:00');

    $this->actingAs(memberOf($this->tenant, $role))
        ->patch(route('tenant.lessons.cancel', ['tenant' => $this->tenant, 'booking' => $lesson]))
        ->assertForbidden();

    expect($lesson->fresh()->status)->toBe(BookingStatus::Confirmed);
})->with([Role::Student, Role::Tutor]);

test('lessons that have started, ended or were cancelled cannot be cancelled', function (Closure $makeLesson) {
    $lesson = $makeLesson();

    $this->actingAs($this->student)
        ->patch(route('tenant.lessons.cancel', ['tenant' => $this->tenant, 'booking' => $lesson]))
        ->assertForbidden();
})->with([
    'in the past' => [fn () => lessonOn('2026-09-28', '09:00', '10:00')],
    'already started' => [fn () => lessonOn('2026-10-01', '09:30', '10:30')],
    'already cancelled' => [fn () => tap(lessonOn('2026-10-05', '09:00', '10:00'))->cancel()],
]);

test('the list tells the page which lessons the viewer may cancel', function () {
    lessonOn('2026-10-05', '09:00', '10:00');
    lessonOn('2026-09-28', '09:00', '10:00');

    $this->actingAs($this->student)
        ->get(route('tenant.lessons.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('upcoming.0.can_cancel', true)
            ->where('past.0.can_cancel', false),
        );
});

test('another workspace\'s lesson cannot be cancelled through this workspace\'s URL', function () {
    $elsewhere = Tenant::factory()->create();
    $foreign = Booking::factory()->create([
        'tenant_id' => $elsewhere->id,
        'subject_id' => Subject::factory()->for($elsewhere)->create()->id,
        'teacher_id' => memberOf($elsewhere, Role::Owner)->id,
        'student_id' => memberOf($elsewhere, Role::Student)->id,
    ]);

    $this->actingAs($this->owner)
        ->patch(route('tenant.lessons.cancel', ['tenant' => $this->tenant, 'booking' => $foreign]))
        ->assertNotFound();
});

test('cancelling frees the time to be booked again', function () {
    AvailabilityRule::factory()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->teacher->id,
        'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '10:00',
    ]);
    $lesson = lessonOn('2026-10-05', '09:00', '10:00');
    $mondayNine = fn () => collect(app(SlotFinder::class)->starts($this->tenant, $this->teacher, $this->subject))
        ->contains(fn (CarbonImmutable $start) => $start->equalTo(CarbonImmutable::parse('2026-10-05 09:00', 'Asia/Jakarta')));

    expect($mondayNine())->toBeFalse();

    $this->actingAs($this->student)
        ->patch(route('tenant.lessons.cancel', ['tenant' => $this->tenant, 'booking' => $lesson]));

    expect($mondayNine())->toBeTrue();
});

test('an owner\'s lesson list does not look up their role once per lesson', function () {
    $queriesFor = function (int $lessons): int {
        Booking::withoutGlobalScopes()->delete();
        foreach (range(1, $lessons) as $day) {
            lessonOn(sprintf('2026-10-%02d', 4 + $day), '09:00', '10:00', [
                'student_id' => memberOf($this->tenant, Role::Student)->id,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->owner)->get(route('tenant.lessons.index', $this->tenant))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    expect($queriesFor(8))->toBe($queriesFor(1));
});

test('after booking, the student lands on their lessons', function () {
    AvailabilityRule::factory()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->teacher->id,
        'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00',
    ]);
    $this->subject->syncTeachers([$this->teacher->id]);

    $this->actingAs(User::factory()->create())
        ->post(route('tenant.book.store', $this->tenant), [
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'starts_at' => CarbonImmutable::parse('2026-10-05 09:00', 'Asia/Jakarta')->toIso8601String(),
        ])
        ->assertRedirect(route('tenant.lessons.index', $this->tenant));
});
