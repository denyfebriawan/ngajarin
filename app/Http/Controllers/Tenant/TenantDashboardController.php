<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\BookingStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The workspace overview: a few numbers, the next lesson and today's schedule. Like the lessons
 * page, owners see the whole workspace and everyone else the lessons they teach or take; only
 * owners see money.
 */
class TenantDashboardController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant, CurrentTenant $current): Response
    {
        $user = $request->user();
        $isOwner = $current->role === Role::Owner;

        // "Today", "this week" and "this month" are the workspace's, not UTC's.
        $now = CarbonImmutable::now($tenant->timezone);
        $today = [$now->startOfDay(), $now->startOfDay()->addDay()];
        $week = [$now->startOfWeek(), $now->startOfWeek()->addWeek()]; // Monday to Monday
        $month = [$now->startOfMonth(), $now->startOfMonth()->addMonth()];

        // Confirmed lessons this user may see; the tenant scope already limits them to this workspace.
        $visible = fn (): Builder => Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->when(! $isOwner, fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->where('teacher_id', $user?->id)->orWhere('student_id', $user?->id),
            ));

        // One pass over the lessons: each FILTER counts only the rows matching its own condition.
        $stats = $visible()
            ->selectRaw(
                'count(*) filter (where starts_at >= ? and starts_at < ?) as today,
                 count(*) filter (where starts_at >= ? and starts_at < ?) as this_week,
                 coalesce(sum(price) filter (where starts_at >= ? and starts_at < ?), 0) as booked_this_month,
                 count(distinct student_id) filter (where starts_at >= ? and starts_at < ?) as students_this_month',
                [...$today, ...$week, ...$month, ...$month],
            )
            ->toBase()
            ->first();

        $next = $visible()
            ->with(['subject', 'teacher', 'student'])
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->first();

        $todaysLessons = $visible()
            ->with(['subject', 'teacher', 'student'])
            ->where('starts_at', '>=', $today[0])
            ->where('starts_at', '<', $today[1])
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('tenant/dashboard', [
            'stats' => [
                'today' => (int) ($stats->today ?? 0),
                'this_week' => (int) ($stats->this_week ?? 0),
                // Money and student numbers are for owners only.
                'booked_this_month' => $isOwner ? (int) ($stats->booked_this_month ?? 0) : null,
                'students_this_month' => $isOwner ? (int) ($stats->students_this_month ?? 0) : null,
            ],
            'next' => $next ? $this->present($next, $tenant) : null,
            'today' => $todaysLessons->map(fn (Booking $b) => $this->present($b, $tenant)),
        ]);
    }

    /**
     * @return array{id: int, subject: string, teacher: array{id: int, name: string}, student: array{id: int, name: string}, date: string, starts: string, ends: string}
     */
    private function present(Booking $booking, Tenant $tenant): array
    {
        $start = $booking->starts_at->setTimezone($tenant->timezone);

        return [
            'id' => $booking->id,
            'subject' => $booking->subject->name,
            'teacher' => $this->person($booking->teacher),
            'student' => $this->person($booking->student),
            'date' => $start->toDateString(),
            'starts' => $start->format('H:i'),
            'ends' => $booking->ends_at->setTimezone($tenant->timezone)->format('H:i'),
        ];
    }

    /**
     * @return array{id: int, name: string}
     */
    private function person(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name];
    }
}
