<?php

use App\Enums\Role;
use App\Models\AvailabilityRule;
use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->teacher = memberOf($this->tenant, Role::Tutor);
});

// A block of hours for $this->teacher in $this->tenant; each test overrides what it cares about.
function hours(array $attributes = []): AvailabilityRule
{
    return AvailabilityRule::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->teacher->id,
        ...$attributes,
    ]);
}

test('blocks that touch are allowed: 09:00-12:00 then 12:00-15:00', function () {
    hours(['starts_at' => '09:00', 'ends_at' => '12:00']);
    hours(['starts_at' => '12:00', 'ends_at' => '15:00']);

    expect(AvailabilityRule::withoutGlobalScopes()->count())->toBe(2);
});

test('overlapping blocks on the same day are rejected by the database', function (string $start, string $end) {
    hours(['starts_at' => '09:00', 'ends_at' => '12:00']);
    hours(['starts_at' => $start, 'ends_at' => $end]);
})->with([
    'starts inside' => ['11:00', '13:00'],
    'ends inside' => ['08:00', '10:00'],
    'inside' => ['10:00', '11:00'],
    'covers it' => ['08:00', '13:00'],
    'identical' => ['09:00', '12:00'],
])->throws(QueryException::class, 'availability_rules_no_overlap');

test('the same hours are fine on another weekday, for another teacher, or in another workspace', function () {
    hours(['weekday' => 1]);
    hours(['weekday' => 2]);

    hours(['user_id' => memberOf($this->tenant, Role::Owner)->id]);

    // The same person teaching in a second workspace keeps separate hours there.
    $otherTenant = Tenant::factory()->create();
    $otherTenant->addMember($this->teacher, Role::Tutor);
    hours(['tenant_id' => $otherTenant->id]);

    expect(AvailabilityRule::withoutGlobalScopes()->count())->toBe(4);
});

test('blocks must end after they start', function (string $start, string $end) {
    hours(['starts_at' => $start, 'ends_at' => $end]);
})->with([
    'backwards' => ['12:00', '09:00'],
    'empty' => ['09:00', '09:00'],
])->throws(QueryException::class, 'availability_rules_order_check');

test('the weekday must be 1 (Monday) to 7 (Sunday)', function (int $weekday) {
    hours(['weekday' => $weekday]);
})->with([0, 8])->throws(QueryException::class, 'availability_rules_weekday_check');

test('hours can only belong to a member of the same workspace', function () {
    $outsider = memberOf(Tenant::factory()->create(), Role::Tutor);

    hours(['user_id' => $outsider->id]);
})->throws(QueryException::class, 'availability_rules_tenant_id_user_id_foreign');

test('leaving a workspace removes the teacher\'s hours there', function () {
    hours();

    $this->tenant->users()->detach($this->teacher);

    expect(AvailabilityRule::withoutGlobalScopes()->count())->toBe(0);
});

test('hours are only visible inside their own workspace', function () {
    hours();
    $otherTenant = Tenant::factory()->create();

    app()->instance(CurrentTenant::class, new CurrentTenant($otherTenant, Role::Owner));
    expect(AvailabilityRule::count())->toBe(0);

    app()->instance(CurrentTenant::class, new CurrentTenant($this->tenant, Role::Owner));
    expect(AvailabilityRule::count())->toBe(1)
        ->and(AvailabilityRule::first()->teacher->is($this->teacher))->toBeTrue();
});
