<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SubjectRequest;
use App\Models\Subject;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The workspace's subjects. Every query here is limited to the current tenant by
 * BelongsToTenant, and {subject} in the URL can only resolve to this tenant's subjects.
 */
class SubjectController extends Controller
{
    private const FIELDS = ['id', 'name', 'description', 'duration_minutes', 'price'];

    public function index(): Response
    {
        return Inertia::render('tenant/subjects/index', [
            'subjects' => Subject::query()->orderBy('name')->get(self::FIELDS),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('tenant/subjects/create');
    }

    public function store(SubjectRequest $request, Tenant $tenant): RedirectResponse
    {
        Subject::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subject created.')]);

        return to_route('tenant.subjects.index', $tenant);
    }

    public function edit(Tenant $tenant, Subject $subject): Response
    {
        return Inertia::render('tenant/subjects/edit', [
            'subject' => $subject->only(self::FIELDS),
        ]);
    }

    public function update(SubjectRequest $request, Tenant $tenant, Subject $subject): RedirectResponse
    {
        $subject->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subject updated.')]);

        return to_route('tenant.subjects.index', $tenant);
    }

    public function destroy(Tenant $tenant, Subject $subject): RedirectResponse
    {
        $subject->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subject deleted.')]);

        return to_route('tenant.subjects.index', $tenant);
    }
}
