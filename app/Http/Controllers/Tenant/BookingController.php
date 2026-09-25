<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\User;
use App\Scheduling\SlotFinder;
use App\Support\PostgresError;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A workspace's public booking page. Anyone can look; booking needs a verified account, and the
 * first booking makes the user a student of the workspace.
 *
 * These routes don't use EnsureTenantMember (visitors aren't members), so there is no current
 * tenant: every query filters by $tenant explicitly.
 */
class BookingController extends Controller
{
    public function create(Request $request, Tenant $tenant, SlotFinder $finder): Response
    {
        $subjects = Subject::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereHas('teachers')
            ->with('teachers')
            ->orderBy('name')
            ->get();

        // The choices so far come from the URL (?subject=..&teacher=..), so the page can be shared.
        $subject = $subjects->firstWhere('id', $request->integer('subject'));
        $teacher = $subject?->teachers->firstWhere('id', $request->integer('teacher'))
            // With only one teacher there is nothing to choose.
            ?? ($subject?->teachers->count() === 1 ? $subject->teachers->first() : null);

        // Guests who log in from here come straight back to the times they were looking at.
        if ($request->user() === null) {
            redirect()->setIntendedUrl($request->fullUrl());
        }

        return Inertia::render('booking/create', [
            'tenant' => ['name' => $tenant->name, 'slug' => $tenant->slug, 'timezone' => $tenant->timezone],
            'subjects' => $subjects->map(fn (Subject $s) => [
                ...$s->only(['id', 'name', 'description', 'duration_minutes', 'price']),
                'teachers' => $s->teachers->sortBy('name')->map(fn (User $t) => ['id' => $t->id, 'name' => $t->name])->values(),
            ]),
            'selected' => ['subject_id' => $subject?->id, 'teacher_id' => $teacher?->id],
            'slots' => $subject !== null && $teacher !== null
                ? $this->slots($finder->starts($tenant, $teacher, $subject, $request->user()), $tenant)
                : [],
        ]);
    }

    public function store(StoreBookingRequest $request, Tenant $tenant): RedirectResponse
    {
        $student = $request->user();
        $subject = $request->subject();
        $start = $request->start();

        try {
            DB::transaction(function () use ($tenant, $student, $subject, $request, $start) {
                // Joining and booking succeed or fail together: no membership for a lost race.
                $tenant->ensureMember($student, Role::Student);

                $booking = new Booking([
                    'subject_id' => $subject->id,
                    'teacher_id' => $request->teacher()->id,
                    'student_id' => $student->id,
                    'starts_at' => $start,
                    'ends_at' => $start->addMinutes($subject->duration_minutes),
                    'price' => $subject->price,
                ]);
                // Not fillable, and there is no current tenant to fill it from on this route.
                $booking->tenant_id = $tenant->id;
                $booking->save();
            });
        } catch (QueryException $exception) {
            // Validation saw the slot free, but another booking committed first: the
            // no-double-booking constraint let only one of them through.
            if (! PostgresError::isExclusionViolation($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'starts_at' => 'Sorry, someone booked that time a moment ago. Please pick another.',
            ]);
        }

        $local = $start->setTimezone($tenant->timezone);
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Lesson booked for :time.', ['time' => $local->format('D j M, H:i')]),
        ]);

        return to_route('tenant.lessons.index', $tenant);
    }

    /**
     * Free starts for the page, with the local date and time worked out here, in the
     * workspace's timezone (the browser's own timezone may differ).
     *
     * @param  list<CarbonImmutable>  $starts
     * @return list<array{starts_at: string, date: string, time: string}>
     */
    private function slots(array $starts, Tenant $tenant): array
    {
        return array_map(function (CarbonImmutable $start) use ($tenant) {
            $local = $start->setTimezone($tenant->timezone);

            return [
                'starts_at' => $start->toIso8601String(),
                'date' => $local->toDateString(),
                'time' => $local->format('H:i'),
            ];
        }, $starts);
    }
}
