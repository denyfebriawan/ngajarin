<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in user's home: their upcoming lessons across every workspace, whether they teach
 * or study in it. (The workspaces themselves come from the shared `tenants` prop.)
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $userId = $request->user()?->id;

        // This page belongs to no workspace, so there is no current tenant and the tenant scope
        // would match nothing. Opting out is safe here: only the user's own lessons are read.
        $lessons = Booking::withoutGlobalScopes()
            ->with([
                'tenant',
                'subject' => fn ($query) => $query->withoutGlobalScopes(),
                'teacher',
                'student',
            ])
            ->where('status', BookingStatus::Confirmed)
            ->where('ends_at', '>', now())
            ->where(fn (Builder $query) => $query->where('student_id', $userId)->orWhere('teacher_id', $userId))
            ->orderBy('starts_at')
            ->limit(10)
            ->get();

        return Inertia::render('dashboard', [
            'lessons' => $lessons->map(function (Booking $booking) use ($userId) {
                // Each lesson in its own workspace's timezone.
                $start = $booking->starts_at->setTimezone($booking->tenant->timezone);

                return [
                    'id' => $booking->id,
                    'tenant' => ['name' => $booking->tenant->name, 'slug' => $booking->tenant->slug],
                    'subject' => $booking->subject->name,
                    'teaching' => $booking->teacher_id === $userId,
                    // The other person: the teacher for a student, the student for a teacher.
                    'with' => $booking->teacher_id === $userId ? $booking->student->name : $booking->teacher->name,
                    'date' => $start->toDateString(),
                    'starts' => $start->format('H:i'),
                    'ends' => $booking->ends_at->setTimezone($booking->tenant->timezone)->format('H:i'),
                ];
            }),
        ]);
    }
}
