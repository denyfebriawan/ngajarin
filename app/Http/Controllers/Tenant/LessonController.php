<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\BookingStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The workspace's lessons, as the signed-in member may see them: owners see all of them;
 * everyone else sees the lessons they teach or take.
 */
class LessonController extends Controller
{
    public function index(Request $request, Tenant $tenant, CurrentTenant $current): Response
    {
        $user = $request->user();

        // Booking queries are already limited to this workspace by the tenant scope.
        $visible = fn (): Builder => Booking::query()
            ->with(['subject', 'teacher', 'student'])
            ->when($current->role !== Role::Owner, fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->where('teacher_id', $user?->id)->orWhere('student_id', $user?->id),
            ));

        $upcoming = $visible()
            ->where('status', BookingStatus::Confirmed)
            ->where('ends_at', '>', now())
            ->orderBy('starts_at')
            ->get();

        $pastOrCancelled = $visible()
            ->where(fn (Builder $query) => $query
                ->where('ends_at', '<=', now())
                ->orWhere('status', BookingStatus::Cancelled))
            ->orderByDesc('starts_at')
            ->limit(50)
            ->get();

        return Inertia::render('tenant/lessons', [
            'upcoming' => $upcoming->map(fn (Booking $b) => $this->present($b, $tenant, $user)),
            'past' => $pastOrCancelled->map(fn (Booking $b) => $this->present($b, $tenant, $user)),
        ]);
    }

    public function cancel(Tenant $tenant, Booking $booking): RedirectResponse
    {
        // Frees the slot: the no-double-booking constraints only count confirmed lessons.
        $booking->cancel();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lesson cancelled.')]);

        return back();
    }

    /**
     * A lesson for the page, with its times in the workspace's timezone.
     *
     * @return array<string, mixed>
     */
    private function present(Booking $booking, Tenant $tenant, ?User $user): array
    {
        $start = $booking->starts_at->setTimezone($tenant->timezone);
        $end = $booking->ends_at->setTimezone($tenant->timezone);

        return [
            'id' => $booking->id,
            'subject' => $booking->subject->name,
            'teacher' => ['id' => $booking->teacher->id, 'name' => $booking->teacher->name],
            'student' => ['id' => $booking->student->id, 'name' => $booking->student->name],
            'date' => $start->toDateString(),
            'starts' => $start->format('H:i'),
            'ends' => $end->format('H:i'),
            'price' => $booking->price,
            'status' => $booking->status->value,
            'can_cancel' => $user?->can('cancel', $booking) ?? false,
        ];
    }
}
