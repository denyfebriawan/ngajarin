<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTimeOffRequest;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Support\PostgresError;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in teacher's own leave in the current workspace.
 */
class TimeOffController extends Controller
{
    public function index(Request $request, Tenant $tenant): Response
    {
        return Inertia::render('tenant/time-off', [
            // Current and upcoming leave only; past leave no longer matters for booking.
            'entries' => TimeOff::query()
                ->where('user_id', $request->user()?->id)
                ->where('ends_at', '>', now())
                ->orderBy('starts_at')
                ->get()
                ->map(fn (TimeOff $entry) => [
                    'id' => $entry->id,
                    // Back to the local dates the teacher entered; the stored end is exclusive.
                    'start_date' => $this->localDate($entry->starts_at, $tenant),
                    'end_date' => $this->localDate($entry->ends_at->subDay(), $tenant),
                    'reason' => $entry->reason,
                ]),
            'today' => CarbonImmutable::now($tenant->timezone)->toDateString(),
        ]);
    }

    public function store(StoreTimeOffRequest $request, Tenant $tenant): RedirectResponse
    {
        [$start, $end] = $request->period();

        try {
            TimeOff::create([
                'user_id' => $request->user()?->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'reason' => $request->validated('reason'),
            ]);
        } catch (QueryException $exception) {
            // Validation checked for clashes, but a second save at the same moment can still win.
            if (! PostgresError::isExclusionViolation($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'start_date' => 'You already have time off during these dates.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time off added.')]);

        return to_route('tenant.time-off.index', $tenant);
    }

    public function destroy(Tenant $tenant, TimeOff $timeOff): RedirectResponse
    {
        $timeOff->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time off removed.')]);

        return to_route('tenant.time-off.index', $tenant);
    }

    private function localDate(CarbonImmutable $moment, Tenant $tenant): string
    {
        return $moment->setTimezone($tenant->timezone)->toDateString();
    }
}
