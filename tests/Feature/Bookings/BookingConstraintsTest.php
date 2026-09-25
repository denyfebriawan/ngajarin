<?php

use App\Enums\BookingStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\Subject;
use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => 'budi-math']);
    $this->subject = Subject::factory()->for($this->tenant)->create(['price' => 150_000]);
    $this->teacher = memberOf($this->tenant, Role::Tutor);
    $this->student = memberOf($this->tenant, Role::Student);
});

// A lesson in $this->tenant; each test overrides what it cares about. Times are UTC.
function lesson(string $start, string $end, array $attributes = []): Booking
{
    return Booking::factory()->create([
        'tenant_id' => test()->tenant->id,
        'subject_id' => test()->subject->id,
        'teacher_id' => test()->teacher->id,
        'student_id' => test()->student->id,
        'starts_at' => "2026-10-05 $start:00+00",
        'ends_at' => "2026-10-05 $end:00+00",
        ...$attributes,
    ]);
}

test('a new booking is confirmed and keeps the price it was booked at', function () {
    $booking = lesson('10:00', '11:00');
    $this->subject->update(['price' => 200_000]);

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->fresh()->price)->toBe(150_000);
});

test('Postgres stores the lesson as a half-open tstzrange', function () {
    $booking = lesson('10:00', '11:00');

    $period = DB::table('bookings')->where('id', $booking->id)->value('period');

    expect($period)->toBe('["2026-10-05 10:00:00+00","2026-10-05 11:00:00+00")');
});

test('back-to-back lessons are fine: 10:00-11:00 then 11:00-12:00', function () {
    lesson('10:00', '11:00');
    lesson('11:00', '12:00', ['student_id' => memberOf($this->tenant, Role::Student)->id]);

    expect(Booking::withoutGlobalScopes()->count())->toBe(2);
});

test('a teacher can never be double-booked', function (string $start, string $end) {
    lesson('10:00', '11:00');

    // A different student, so only the teacher constraint can object.
    lesson($start, $end, ['student_id' => memberOf($this->tenant, Role::Student)->id]);
})->with([
    'the same slot' => ['10:00', '11:00'],
    'starts during it' => ['10:30', '11:30'],
    'ends during it' => ['09:30', '10:30'],
    'inside it' => ['10:15', '10:45'],
    'around it' => ['09:00', '12:00'],
])->throws(QueryException::class, 'bookings_teacher_no_overlap');

test('a student can never be in two lessons at once, even with different teachers', function () {
    lesson('10:00', '11:00');

    lesson('10:30', '11:30', ['teacher_id' => memberOf($this->tenant, Role::Owner)->id]);
})->throws(QueryException::class, 'bookings_student_no_overlap');

test('a teacher working in two workspaces cannot be booked in both at once', function () {
    lesson('10:00', '11:00');

    $otherTenant = Tenant::factory()->create();
    $otherTenant->addMember($this->teacher, Role::Tutor);
    Booking::factory()->create([
        'tenant_id' => $otherTenant->id,
        'subject_id' => Subject::factory()->for($otherTenant)->create()->id,
        'teacher_id' => $this->teacher->id,
        'student_id' => memberOf($otherTenant, Role::Student)->id,
        'starts_at' => '2026-10-05 10:30:00+00',
        'ends_at' => '2026-10-05 11:30:00+00',
    ]);
})->throws(QueryException::class, 'bookings_teacher_no_overlap');

test('a cancelled lesson frees its slot', function () {
    $booking = lesson('10:00', '11:00');

    $booking->cancel();
    $rebooked = lesson('10:00', '11:00', ['student_id' => memberOf($this->tenant, Role::Student)->id]);

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->fresh()->cancelled_at)->not->toBeNull()
        ->and($rebooked->status)->toBe(BookingStatus::Confirmed);
});

test('the database rejects impossible lessons', function (string $start, string $end, array $attributes, string $error) {
    expect(fn () => lesson($start, $end, $attributes))->toThrow(QueryException::class, $error);
})->with([
    // A backwards range is invalid in itself: Postgres refuses to compute `period` for it.
    'ends before it starts' => ['10:00', '09:00', [], 'range lower bound must be less than or equal to range upper bound'],
    // A zero-length lesson is a valid, empty range, so only the CHECK stops it.
    'zero length' => ['10:00', '10:00', [], 'bookings_order_check'],
    'negative price' => ['10:00', '11:00', ['price' => -1], 'bookings_price_check'],
]);

test('nobody can book a lesson with themselves', function () {
    lesson('10:00', '11:00', ['student_id' => $this->teacher->id]);
})->throws(QueryException::class, 'bookings_student_is_not_teacher');

test('subject, teacher and student must all belong to the booking\'s workspace', function (Closure $attributes, string $constraint) {
    $otherTenant = Tenant::factory()->create();

    expect(fn () => lesson('10:00', '11:00', $attributes($otherTenant)))->toThrow(QueryException::class, $constraint);
})->with([
    'another workspace\'s subject' => [fn (Tenant $other) => ['subject_id' => Subject::factory()->for($other)->create()->id], 'bookings_tenant_id_subject_id_foreign'],
    'a teacher from elsewhere' => [fn (Tenant $other) => ['teacher_id' => memberOf($other, Role::Tutor)->id], 'bookings_tenant_id_teacher_id_foreign'],
    'a student from elsewhere' => [fn (Tenant $other) => ['student_id' => memberOf($other, Role::Student)->id], 'bookings_tenant_id_student_id_foreign'],
]);

test('leaving a workspace removes that person\'s bookings there', function () {
    lesson('10:00', '11:00');

    $this->tenant->users()->detach($this->student);

    expect(Booking::withoutGlobalScopes()->count())->toBe(0);
});

test('deleting a workspace removes its bookings, even though subjects with lessons are protected', function () {
    lesson('10:00', '11:00');

    $this->tenant->delete();

    expect(Booking::withoutGlobalScopes()->count())->toBe(0);
});

test('bookings are only visible inside their own workspace', function () {
    lesson('10:00', '11:00');

    app()->instance(CurrentTenant::class, new CurrentTenant(Tenant::factory()->create(), Role::Owner));
    expect(Booking::count())->toBe(0);

    app()->instance(CurrentTenant::class, new CurrentTenant($this->tenant, Role::Owner));
    expect(Booking::count())->toBe(1);
});

test('a subject with lessons cannot be deleted, and the owner is told why', function () {
    lesson('10:00', '11:00');
    $owner = memberOf($this->tenant, Role::Owner);

    $this->actingAs($owner)
        ->from(route('tenant.subjects.edit', ['tenant' => $this->tenant, 'subject' => $this->subject]))
        ->delete(route('tenant.subjects.destroy', ['tenant' => $this->tenant, 'subject' => $this->subject]))
        ->assertRedirect(route('tenant.subjects.edit', ['tenant' => $this->tenant, 'subject' => $this->subject]))
        ->assertInertiaFlash('toast.type', 'error');

    expect($this->subject->fresh())->not->toBeNull();
});
