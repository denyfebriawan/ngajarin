<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\TimeOff;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Freeze "now" at 1 Oct 2026, 10:00 in Jakarta (03:00 UTC).
    $this->travelTo(CarbonImmutable::parse('2026-10-01 03:00:00', 'UTC'));

    $this->tenant = Tenant::factory()->create(['slug' => 'budi-math', 'timezone' => 'Asia/Jakarta']);
    $this->teacher = memberOf($this->tenant, Role::Tutor);
});

function addTimeOff(array $data)
{
    return test()->post(route('tenant.time-off.store', test()->tenant), $data);
}

function timeOffOf(int $userId): array
{
    return TimeOff::withoutGlobalScopes()->where('user_id', $userId)->orderBy('starts_at')->get()
        ->map(fn (TimeOff $entry) => [$entry->starts_at->toIso8601String(), $entry->ends_at->toIso8601String()])
        ->all();
}

test('a teacher\'s days off are stored as exact moments in the workspace\'s timezone', function () {
    $this->actingAs($this->teacher);

    addTimeOff(['start_date' => '2026-12-24', 'end_date' => '2026-12-26', 'reason' => 'Christmas'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.time-off.index', $this->tenant));

    // 24 Dec 00:00 WIB is 23 Dec 17:00 UTC; the end is exclusive: 27 Dec 00:00 WIB.
    expect(timeOffOf($this->teacher->id))->toBe([['2026-12-23T17:00:00+00:00', '2026-12-26T17:00:00+00:00']]);
});

test('the same dates are a different moment in an eastern workspace', function () {
    $this->tenant->update(['timezone' => 'Asia/Jayapura']);
    $this->actingAs($this->teacher);

    addTimeOff(['start_date' => '2026-12-24', 'end_date' => '2026-12-24'])->assertSessionHasNoErrors();

    // 24 Dec 00:00 WIT (UTC+9) is 23 Dec 15:00 UTC.
    expect(timeOffOf($this->teacher->id))->toBe([['2026-12-23T15:00:00+00:00', '2026-12-24T15:00:00+00:00']]);
});

test('the page lists current and upcoming time off as the dates the teacher entered', function () {
    $this->actingAs($this->teacher);
    addTimeOff(['start_date' => '2026-12-24', 'end_date' => '2026-12-26', 'reason' => 'Christmas']);
    addTimeOff(['start_date' => '2026-10-01', 'end_date' => '2026-10-01']);
    // Past leave (created directly, since the form refuses past dates) is not shown.
    TimeOff::factory()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->teacher->id,
        'starts_at' => '2026-09-01 00:00:00+07', 'ends_at' => '2026-09-03 00:00:00+07',
    ]);
    // Neither is a colleague's.
    $colleague = memberOf($this->tenant, Role::Owner);
    TimeOff::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $colleague->id]);

    $this->get(route('tenant.time-off.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/time-off')
            ->where('today', '2026-10-01')
            ->has('entries', 2)
            ->where('entries.0.start_date', '2026-10-01')
            ->where('entries.0.end_date', '2026-10-01')
            ->where('entries.1.start_date', '2026-12-24')
            ->where('entries.1.end_date', '2026-12-26')
            ->where('entries.1.reason', 'Christmas'),
        );
});

test('invalid dates are rejected', function (array $data, string $field) {
    $this->actingAs($this->teacher);

    addTimeOff($data)->assertSessionHasErrors($field);

    expect(timeOffOf($this->teacher->id))->toBe([]);
})->with([
    'in the past' => [['start_date' => '2026-09-30', 'end_date' => '2026-10-02'], 'start_date'],
    'ends before it starts' => [['start_date' => '2026-12-26', 'end_date' => '2026-12-24'], 'end_date'],
    'not a date' => [['start_date' => '24/12/2026', 'end_date' => '2026-12-26'], 'start_date'],
    'longer than a year' => [['start_date' => '2026-10-01', 'end_date' => '2027-10-05'], 'end_date'],
]);

test('"today" is the workspace\'s today', function () {
    // 1 Oct 16:00 UTC is already 2 Oct 01:00 in Jayapura, but still 1 Oct 23:00 in Jakarta.
    $this->travelTo(CarbonImmutable::parse('2026-10-01 16:00:00', 'UTC'));
    $this->actingAs($this->teacher);

    addTimeOff(['start_date' => '2026-10-01', 'end_date' => '2026-10-01'])->assertSessionHasNoErrors();

    $this->tenant->update(['timezone' => 'Asia/Jayapura']);
    TimeOff::withoutGlobalScopes()->delete();
    addTimeOff(['start_date' => '2026-10-01', 'end_date' => '2026-10-01'])->assertSessionHasErrors('start_date');
});

test('overlapping time off is rejected, adjacent time off is fine', function () {
    $this->actingAs($this->teacher);
    addTimeOff(['start_date' => '2026-12-24', 'end_date' => '2026-12-26'])->assertSessionHasNoErrors();

    addTimeOff(['start_date' => '2026-12-26', 'end_date' => '2026-12-28'])
        ->assertSessionHasErrors(['start_date' => 'You already have time off during these dates.']);

    addTimeOff(['start_date' => '2026-12-27', 'end_date' => '2026-12-28'])->assertSessionHasNoErrors();

    expect(timeOffOf($this->teacher->id))->toHaveCount(2);
});

test('the database rejects overlapping time off for the same teacher', function () {
    $insert = fn (string $start, string $end) => DB::table('time_off')->insert([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->teacher->id,
        'starts_at' => $start, 'ends_at' => $end,
    ]);

    $insert('2026-12-24 00:00:00+07', '2026-12-27 00:00:00+07');
    $insert('2026-12-26 00:00:00+07', '2026-12-28 00:00:00+07');
})->throws(QueryException::class, 'time_off_no_overlap');

test('a save that races another save becomes a friendly error, not a crash', function () {
    // Just before our insert, an overlapping entry appears (a second tab, say).
    TimeOff::creating(function () {
        DB::table('time_off')->insert([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->teacher->id,
            'starts_at' => '2026-12-25 00:00:00+07', 'ends_at' => '2026-12-26 00:00:00+07',
        ]);
    });

    $this->actingAs($this->teacher);
    addTimeOff(['start_date' => '2026-12-24', 'end_date' => '2026-12-26'])->assertSessionHasErrors('start_date');
});

test('teachers can remove their own time off, but not a colleague\'s', function () {
    $mine = TimeOff::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->teacher->id]);
    $colleague = memberOf($this->tenant, Role::Owner);
    $theirs = TimeOff::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $colleague->id]);

    $this->actingAs($this->teacher);

    $this->delete(route('tenant.time-off.destroy', ['tenant' => $this->tenant, 'timeOff' => $theirs]))
        ->assertForbidden();
    $this->delete(route('tenant.time-off.destroy', ['tenant' => $this->tenant, 'timeOff' => $mine]))
        ->assertRedirect(route('tenant.time-off.index', $this->tenant));

    expect(TimeOff::withoutGlobalScopes()->pluck('id')->all())->toBe([$theirs->id]);
});

test('another workspace\'s time off cannot be removed through this workspace\'s URL', function () {
    $otherTenant = Tenant::factory()->create();
    $otherTenant->addMember($this->teacher, Role::Tutor);
    $elsewhere = TimeOff::factory()->create(['tenant_id' => $otherTenant->id, 'user_id' => $this->teacher->id]);

    $this->actingAs($this->teacher)
        ->delete(route('tenant.time-off.destroy', ['tenant' => $this->tenant, 'timeOff' => $elsewhere]))
        ->assertNotFound();

    expect($elsewhere->fresh())->not->toBeNull();
});

test('students have no time off page', function () {
    $this->actingAs(memberOf($this->tenant, Role::Student));

    $this->get(route('tenant.time-off.index', $this->tenant))->assertForbidden();
    addTimeOff(['start_date' => '2026-12-24', 'end_date' => '2026-12-26'])->assertForbidden();
});

test('leaving a workspace removes the teacher\'s time off there', function () {
    TimeOff::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->teacher->id]);

    $this->tenant->users()->detach($this->teacher);

    expect(TimeOff::withoutGlobalScopes()->count())->toBe(0);
});
