<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AvailabilityRequest;
use App\Models\AvailabilityRule;
use App\Models\Tenant;
use App\Support\PostgresError;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in teacher's own weekly hours in the current workspace.
 */
class AvailabilityController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('tenant/availability', [
            'blocks' => AvailabilityRule::query()
                ->where('user_id', $request->user()?->id)
                ->orderBy('weekday')
                ->orderBy('starts_at')
                ->get()
                ->map(fn (AvailabilityRule $rule) => [
                    'weekday' => $rule->weekday,
                    // "09:00:00" from Postgres -> "09:00" for the time inputs.
                    'starts_at' => substr($rule->starts_at, 0, 5),
                    'ends_at' => substr($rule->ends_at, 0, 5),
                ]),
        ]);
    }

    /**
     * Replace the teacher's whole week with the submitted blocks.
     */
    public function update(AvailabilityRequest $request, Tenant $tenant): RedirectResponse
    {
        $userId = $request->user()?->id;

        try {
            DB::transaction(function () use ($request, $userId) {
                AvailabilityRule::query()->where('user_id', $userId)->delete();

                foreach ($request->blocks() as $block) {
                    AvailabilityRule::create(['user_id' => $userId, ...$block]);
                }
            });
        } catch (QueryException $exception) {
            // Validation already rejects overlaps within one save, so this only happens when two
            // saves race (say, from two tabs). The database constraint lets one of them through.
            if (! PostgresError::isExclusionViolation($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'blocks' => 'Your hours were changed somewhere else at the same time. Reload the page and try again.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Availability saved.')]);

        return to_route('tenant.availability.edit', $tenant);
    }
}
