<?php

use App\Demo\DemoWorkspace;
use App\Enums\BookingStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\User;
use App\Scheduling\SlotFinder;
use Carbon\CarbonImmutable;

// Tests have no current tenant, so tenant-owned models are read without the tenant scope.

test('resetting builds the demo workspace with its people, subjects and lessons', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 03:00', 'Asia/Jakarta'));

    $tenant = app(DemoWorkspace::class)->reset();

    $tutor = User::where('email', DemoWorkspace::TUTOR_EMAIL)->firstOrFail();
    $student = User::where('email', DemoWorkspace::STUDENT_EMAIL)->firstOrFail();
    $lessons = Booking::withoutGlobalScopes()->where('tenant_id', $tenant->id);

    expect($tenant->slug)->toBe(DemoWorkspace::SLUG)
        ->and($tutor->roleIn($tenant))->toBe(Role::Owner)
        ->and($student->roleIn($tenant))->toBe(Role::Student)
        ->and($tenant->teachers()->count())->toBe(2)
        ->and($tenant->users()->count())->toBe(7)
        ->and(Subject::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(4)
        ->and((clone $lessons)->where('ends_at', '<=', now())->count())->toBeGreaterThan(20)
        ->and((clone $lessons)->where('starts_at', '>', now())->count())->toBeGreaterThan(5)
        ->and((clone $lessons)->where('status', BookingStatus::Cancelled)->count())->toBeGreaterThan(0);
});

test('the demo works whatever day it is built', function (string $date) {
    $this->travelTo(CarbonImmutable::parse("$date 03:00", 'Asia/Jakarta'));

    // Would throw if the pattern ever broke a no-double-booking constraint.
    $tenant = app(DemoWorkspace::class)->reset();

    $tutor = User::where('email', DemoWorkspace::TUTOR_EMAIL)->firstOrFail();
    $student = User::where('email', DemoWorkspace::STUDENT_EMAIL)->firstOrFail();
    $subject = Subject::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'Math (SMP)')->firstOrFail();

    $upcoming = Booking::withoutGlobalScopes()
        ->where('student_id', $student->id)
        ->where('status', BookingStatus::Confirmed)
        ->where('starts_at', '>', now())
        ->count();

    // The demo student has something coming up, and still has free times to book.
    expect($upcoming)->toBeGreaterThan(0)
        ->and(app(SlotFinder::class)->starts($tenant, $tutor, $subject, $student))->not->toBeEmpty();
})->with([
    'Monday' => '2026-10-05',
    'Tuesday' => '2026-10-06',
    'Wednesday' => '2026-10-07',
    'Thursday' => '2026-10-08',
    'Friday' => '2026-10-09',
    'Saturday' => '2026-10-10',
    'Sunday' => '2026-10-11',
]);

test('resetting again undoes what visitors changed', function () {
    $demo = app(DemoWorkspace::class);
    $tenant = $demo->reset();
    $lessonCount = Booking::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();

    // A visitor renames the demo student and joins another workspace with it.
    $student = User::where('email', DemoWorkspace::STUDENT_EMAIL)->firstOrFail();
    $student->update(['name' => 'Someone Else']);
    $elsewhere = Tenant::factory()->create();
    $elsewhere->addMember($student, Role::Student);

    $tenant = $demo->reset();

    expect(Tenant::where('slug', DemoWorkspace::SLUG)->count())->toBe(1)
        ->and(User::where('email', 'like', '%@'.DemoWorkspace::EMAIL_DOMAIN)->count())->toBe(7)
        ->and(User::where('email', DemoWorkspace::STUDENT_EMAIL)->value('name'))->toBe('Siti Rahmawati')
        ->and($elsewhere->users()->count())->toBe(0)
        ->and(Booking::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe($lessonCount);
});

test('a real workspace using the demo address is never deleted', function () {
    $real = Tenant::factory()->create(['slug' => DemoWorkspace::SLUG]);
    $real->addMember(User::factory()->create(), Role::Owner);

    expect(fn () => app(DemoWorkspace::class)->reset())
        ->toThrow(LogicException::class);

    expect(Tenant::find($real->id))->not->toBeNull();
});

test('the demo can be reset from the command line', function () {
    $this->artisan('demo:reset')->assertSuccessful();

    expect(Tenant::where('slug', DemoWorkspace::SLUG)->exists())->toBeTrue();
});

test('the demo reset is scheduled', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('demo:reset');
});
