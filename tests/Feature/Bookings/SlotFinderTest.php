<?php

use App\Enums\Role;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Models\User;
use App\Scheduling\SlotFinder;
use Carbon\CarbonImmutable;

beforeEach(function () {
    // Thursday 1 Oct 2026, 10:00 in Jakarta (03:00 UTC). The next Monday is 5 Oct.
    $this->travelTo(CarbonImmutable::parse('2026-10-01 10:00', 'Asia/Jakarta'));

    $this->tenant = Tenant::factory()->create(['timezone' => 'Asia/Jakarta']);
    $this->teacher = memberOf($this->tenant, Role::Tutor);
    $this->hourLesson = Subject::factory()->for($this->tenant)->create(['duration_minutes' => 60]);
});

// Weekly hours for $this->teacher in $this->tenant (1 = Monday ... 7 = Sunday).
function weeklyHours(int $weekday, string $start, string $end): void
{
    AvailabilityRule::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->teacher->id,
        'weekday' => $weekday,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);
}

// The free starts as local times, optionally only those on one date: easy to read and compare.
function freeStarts(?Subject $subject = null, ?string $onDate = null, ?User $student = null): array
{
    $starts = app(SlotFinder::class)->starts(test()->tenant, test()->teacher, $subject ?? test()->hourLesson, $student);

    return collect($starts)
        ->map(fn (CarbonImmutable $start) => $start->setTimezone(test()->tenant->timezone)->format('Y-m-d H:i'))
        ->filter(fn (string $local) => $onDate === null || str_starts_with($local, $onDate))
        ->values()
        ->all();
}

// A confirmed lesson, in local time on the given date, in any workspace.
function lessonAt(Tenant $tenant, User $teacher, User $student, string $date, string $start, string $end): Booking
{
    return Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'subject_id' => Subject::factory()->for($tenant)->create()->id,
        'teacher_id' => $teacher->id,
        'student_id' => $student->id,
        'starts_at' => CarbonImmutable::parse("$date $start", $tenant->timezone),
        'ends_at' => CarbonImmutable::parse("$date $end", $tenant->timezone),
    ]);
}

test('a lesson may start every 30 minutes, as long as it ends inside the teacher\'s hours', function () {
    weeklyHours(1, '09:00', '12:00');

    // 11:30 would end at 12:30, after the block.
    expect(freeStarts(onDate: '2026-10-05'))->toBe([
        '2026-10-05 09:00', '2026-10-05 09:30', '2026-10-05 10:00', '2026-10-05 10:30', '2026-10-05 11:00',
    ]);
});

test('the lesson length decides which starts fit', function () {
    weeklyHours(1, '09:15', '11:15');
    $shortLesson = Subject::factory()->for($this->tenant)->create(['duration_minutes' => 45]);

    // Steps begin at the block's own start; 10:45 + 45 minutes would end at 11:30.
    expect(freeStarts($shortLesson, '2026-10-05'))->toBe(['2026-10-05 09:15', '2026-10-05 09:45', '2026-10-05 10:15']);
});

test('starts repeat every week, up to four weeks ahead', function () {
    weeklyHours(1, '09:00', '10:00');

    expect(freeStarts())->toBe([
        '2026-10-05 09:00', '2026-10-12 09:00', '2026-10-19 09:00', '2026-10-26 09:00',
    ]);
});

test('booking closes two hours ahead, and the window ends exactly four weeks from now', function () {
    weeklyHours(4, '09:00', '14:00'); // Thursdays

    // Today (Thu 1 Oct) it is 10:00, so nothing before 12:00.
    expect(freeStarts(onDate: '2026-10-01'))->toBe(['2026-10-01 12:00', '2026-10-01 12:30', '2026-10-01 13:00']);
    // Four weeks from now is Thu 29 Oct 10:00, so nothing after it.
    expect(freeStarts(onDate: '2026-10-29'))->toBe(['2026-10-29 09:00', '2026-10-29 09:30', '2026-10-29 10:00']);
});

test('weekly hours are local to the workspace\'s timezone', function (string $timezone, string $utc) {
    $this->tenant->update(['timezone' => $timezone]);
    weeklyHours(1, '09:00', '10:00');

    $first = app(SlotFinder::class)->starts($this->tenant, $this->teacher, $this->hourLesson)[0];

    expect($first->toIso8601String())->toBe($utc);
})->with([
    'WIB (UTC+7)' => ['Asia/Jakarta', '2026-10-05T02:00:00+00:00'],
    'WITA (UTC+8)' => ['Asia/Makassar', '2026-10-05T01:00:00+00:00'],
    'WIT (UTC+9)' => ['Asia/Jayapura', '2026-10-05T00:00:00+00:00'],
]);

test('time off removes those days', function () {
    weeklyHours(1, '09:00', '10:00');
    TimeOff::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->teacher->id,
        'starts_at' => CarbonImmutable::parse('2026-10-05 00:00', 'Asia/Jakarta'),
        'ends_at' => CarbonImmutable::parse('2026-10-06 00:00', 'Asia/Jakarta'),
    ]);

    expect(freeStarts(onDate: '2026-10-05'))->toBe([])
        ->and(freeStarts(onDate: '2026-10-12'))->toBe(['2026-10-12 09:00']);
});

test('a booked lesson removes every start that would overlap it', function () {
    weeklyHours(1, '09:00', '12:00');
    lessonAt($this->tenant, $this->teacher, memberOf($this->tenant, Role::Student), '2026-10-05', '10:00', '11:00');

    // 09:30 would run until 10:30; 10:30 would start inside the lesson.
    expect(freeStarts(onDate: '2026-10-05'))->toBe(['2026-10-05 09:00', '2026-10-05 11:00']);
});

test('a cancelled lesson does not block anything', function () {
    weeklyHours(1, '09:00', '11:00');
    lessonAt($this->tenant, $this->teacher, memberOf($this->tenant, Role::Student), '2026-10-05', '09:00', '10:00')->cancel();

    expect(freeStarts(onDate: '2026-10-05'))->toHaveCount(3);
});

test('the teacher\'s lessons in another workspace block them here too, teaching or learning', function () {
    weeklyHours(1, '09:00', '12:00');
    $elsewhere = Tenant::factory()->create(['timezone' => 'Asia/Jakarta']);
    $elsewhere->addMember($this->teacher, Role::Tutor);
    lessonAt($elsewhere, $this->teacher, memberOf($elsewhere, Role::Student), '2026-10-05', '09:00', '10:00');

    $piano = Tenant::factory()->create(['timezone' => 'Asia/Jakarta']);
    $piano->addMember($this->teacher, Role::Student);
    lessonAt($piano, memberOf($piano, Role::Owner), $this->teacher, '2026-10-05', '11:00', '12:00');

    expect(freeStarts(onDate: '2026-10-05'))->toBe(['2026-10-05 10:00']);
});

test('given a student, their own lessons are skipped too', function () {
    weeklyHours(1, '09:00', '11:00');
    $student = memberOf($this->tenant, Role::Student);
    lessonAt($this->tenant, memberOf($this->tenant, Role::Owner), $student, '2026-10-05', '09:00', '10:00');

    expect(freeStarts(onDate: '2026-10-05'))->toHaveCount(3)
        ->and(freeStarts(onDate: '2026-10-05', student: $student))->toBe(['2026-10-05 10:00']);
});

test('other teachers\' lessons and the teacher\'s hours in other workspaces are ignored', function () {
    weeklyHours(1, '09:00', '10:00');
    lessonAt($this->tenant, memberOf($this->tenant, Role::Owner), memberOf($this->tenant, Role::Student), '2026-10-05', '09:00', '10:00');

    $elsewhere = Tenant::factory()->create();
    $elsewhere->addMember($this->teacher, Role::Tutor);
    AvailabilityRule::factory()->create(['tenant_id' => $elsewhere->id, 'user_id' => $this->teacher->id, 'weekday' => 2]);

    expect(freeStarts())->toHaveCount(4)
        ->and(collect(freeStarts())->every(fn (string $start) => CarbonImmutable::parse($start)->isMonday()))->toBeTrue();
});

test('no weekly hours means no starts', function () {
    expect(freeStarts())->toBe([]);
});
