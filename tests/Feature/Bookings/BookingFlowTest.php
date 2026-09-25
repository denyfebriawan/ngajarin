<?php

use App\Enums\BookingStatus;
use App\Enums\Role;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Thursday 1 Oct 2026, 10:00 in Jakarta. The next Monday is 5 Oct.
    $this->travelTo(CarbonImmutable::parse('2026-10-01 10:00', 'Asia/Jakarta'));

    $this->tenant = Tenant::factory()->create(['name' => 'Budi Math', 'slug' => 'budi-math', 'timezone' => 'Asia/Jakarta']);
    $this->teacher = memberOf($this->tenant, Role::Tutor);
    $this->subject = Subject::factory()->for($this->tenant)->create(['name' => 'Math Grade 10', 'duration_minutes' => 60, 'price' => 150_000]);
    $this->subject->syncTeachers([$this->teacher->id]);
    AvailabilityRule::factory()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->teacher->id,
        'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00',
    ]);

    $this->student = User::factory()->create();
});

// Monday 5 Oct at a local time in Jakarta, as the page sends it (ISO 8601, UTC).
function mondayAt(string $time): string
{
    return CarbonImmutable::parse("2026-10-05 $time", 'Asia/Jakarta')->utc()->toIso8601String();
}

function book(array $overrides = [])
{
    return test()->post(route('tenant.book.store', test()->tenant), [
        'subject_id' => test()->subject->id,
        'teacher_id' => test()->teacher->id,
        'starts_at' => mondayAt('09:00'),
        ...$overrides,
    ]);
}

test('anyone can see which subjects the workspace offers', function () {
    // A subject nobody teaches can't be booked, so it isn't listed.
    Subject::factory()->for($this->tenant)->create(['name' => 'Untaught']);

    $this->get(route('tenant.book', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenant.name', 'Budi Math')
            ->has('subjects', 1)
            ->where('subjects.0.name', 'Math Grade 10')
            ->where('subjects.0.teachers', [['id' => $this->teacher->id, 'name' => $this->teacher->name]])
            ->where('slots', []),
        );
});

test('choosing a subject with one teacher shows that teacher\'s free times, in local time', function () {
    $this->get(route('tenant.book', ['tenant' => $this->tenant, 'subject' => $this->subject->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.subject_id', $this->subject->id)
            ->where('selected.teacher_id', $this->teacher->id)
            ->where('slots.0', ['starts_at' => mondayAt('09:00'), 'date' => '2026-10-05', 'time' => '09:00'])
            ->has('slots', 20), // 5 starts on each of 4 Mondays
        );
});

test('with several teachers, the student picks one', function () {
    $colleague = memberOf($this->tenant, Role::Owner);
    $this->subject->syncTeachers([$this->teacher->id, $colleague->id]);

    $this->get(route('tenant.book', ['tenant' => $this->tenant, 'subject' => $this->subject->id]))
        ->assertInertia(fn (Assert $page) => $page->where('selected.teacher_id', null)->where('slots', []));

    $this->get(route('tenant.book', ['tenant' => $this->tenant, 'subject' => $this->subject->id, 'teacher' => $this->teacher->id]))
        ->assertInertia(fn (Assert $page) => $page->where('selected.teacher_id', $this->teacher->id)->has('slots', 20));
});

test('an unknown workspace returns not found', function () {
    $this->get('/t/nobody-here/book')->assertNotFound();
});

test('guests must sign in to book, and come back to the page afterwards', function () {
    $this->get(route('tenant.book', ['tenant' => $this->tenant, 'subject' => $this->subject->id]));
    expect(session('url.intended'))->toContain('/t/budi-math/book?subject=');

    book()->assertRedirect(route('login'));
    expect(Booking::withoutGlobalScopes()->count())->toBe(0);
});

test('booking creates the lesson and makes the student a member of the workspace', function () {
    $this->actingAs($this->student);

    book(['starts_at' => mondayAt('10:00')])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.dashboard', $this->tenant))
        ->assertInertiaFlash('toast.message', 'Lesson booked for Mon 5 Oct, 10:00.');

    $booking = Booking::withoutGlobalScopes()->sole();

    expect($booking->tenant_id)->toBe($this->tenant->id)
        ->and($booking->student_id)->toBe($this->student->id)
        ->and($booking->starts_at->toIso8601String())->toBe('2026-10-05T03:00:00+00:00')
        ->and($booking->ends_at->toIso8601String())->toBe('2026-10-05T04:00:00+00:00')
        ->and($booking->price)->toBe(150_000)
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($this->student->roleIn($this->tenant))->toBe(Role::Student);
});

test('a member who books keeps their role', function () {
    $owner = memberOf($this->tenant, Role::Owner);

    $this->actingAs($owner);
    book()->assertSessionHasNoErrors();

    expect($owner->roleIn($this->tenant))->toBe(Role::Owner);
});

test('only times the page would offer can be booked', function (string $startsAt) {
    $this->actingAs($this->student);

    book(['starts_at' => $startsAt])->assertSessionHasErrors('starts_at');

    expect(Booking::withoutGlobalScopes()->count())->toBe(0)
        ->and($this->student->roleIn($this->tenant))->toBeNull();
})->with([
    'off the 30-minute grid' => fn () => mondayAt('09:10'),
    'outside the teacher\'s hours' => fn () => mondayAt('14:00'),
    'would run past the end of the hours' => fn () => mondayAt('11:30'),
    'within the two-hour notice' => fn () => CarbonImmutable::parse('2026-10-01 11:00', 'Asia/Jakarta')->toIso8601String(),
    'more than four weeks ahead' => fn () => CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Jakarta')->toIso8601String(),
]);

test('a time already booked by someone else cannot be booked', function () {
    $this->actingAs(User::factory()->create());
    book()->assertSessionHasNoErrors();

    $this->actingAs($this->student);
    book()->assertSessionHasErrors('starts_at');

    expect(Booking::withoutGlobalScopes()->count())->toBe(1);
});

test('the subject and teacher must be real choices', function (Closure $overrides, string $field) {
    $this->actingAs($this->student);

    book($overrides())->assertSessionHasErrors($field);
})->with([
    'another workspace\'s subject' => [fn () => ['subject_id' => Subject::factory()->create()->id], 'subject_id'],
    'a teacher who doesn\'t teach it' => [fn () => ['teacher_id' => memberOf(test()->tenant, Role::Owner)->id], 'teacher_id'],
]);

test('teachers cannot book a lesson with themselves', function () {
    $this->actingAs($this->teacher);

    book()->assertSessionHasErrors(['teacher_id' => 'You can\'t book a lesson with yourself.']);
});

test('losing a race for the same time gives a friendly error and joins nothing', function () {
    // After validation saw the slot free, another student's booking commits first.
    $rival = memberOf($this->tenant, Role::Student);
    Booking::creating(function () use ($rival) {
        DB::table('bookings')->insert([
            'tenant_id' => $this->tenant->id, 'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id, 'student_id' => $rival->id,
            'starts_at' => mondayAt('09:00'), 'ends_at' => mondayAt('10:00'), 'price' => 150_000,
        ]);
    });

    $this->actingAs($this->student);
    book()->assertSessionHasErrors(['starts_at' => 'Sorry, someone booked that time a moment ago. Please pick another.']);

    // The whole transaction rolled back, including joining the workspace.
    expect($this->student->roleIn($this->tenant))->toBeNull();
});

test('an unverified account cannot book yet', function () {
    $this->actingAs(User::factory()->unverified()->create());

    book()->assertRedirect(route('verification.notice'));
});
