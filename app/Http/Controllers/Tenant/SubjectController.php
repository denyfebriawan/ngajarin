<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SubjectRequest;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The workspace's subjects. Every query here is limited to the current tenant by
 * BelongsToTenant, and {subject} in the URL can only resolve to this tenant's subjects.
 */
class SubjectController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('tenant/subjects/index', [
            // with('teachers') loads every subject's teachers in one extra query, not one per subject.
            'subjects' => Subject::query()
                ->with('teachers')
                ->orderBy('name')
                ->get()
                ->map(fn (Subject $subject) => $this->present($subject)),
        ]);
    }

    public function create(Tenant $tenant): Response
    {
        return Inertia::render('tenant/subjects/create', [
            'teachers' => $this->teacherOptions($tenant),
        ]);
    }

    public function store(SubjectRequest $request, Tenant $tenant): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $subject = Subject::create($request->subjectData());
            $subject->syncTeachers($request->teacherIds());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subject created.')]);

        return to_route('tenant.subjects.index', $tenant);
    }

    public function edit(Tenant $tenant, Subject $subject): Response
    {
        return Inertia::render('tenant/subjects/edit', [
            'subject' => $this->present($subject->load('teachers')),
            'teachers' => $this->teacherOptions($tenant),
        ]);
    }

    public function update(SubjectRequest $request, Tenant $tenant, Subject $subject): RedirectResponse
    {
        DB::transaction(function () use ($request, $subject) {
            $subject->update($request->subjectData());
            $subject->syncTeachers($request->teacherIds());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subject updated.')]);

        return to_route('tenant.subjects.index', $tenant);
    }

    public function destroy(Tenant $tenant, Subject $subject): RedirectResponse
    {
        // Lesson history must survive. This check gives a friendly message; the bookings table's
        // foreign key is what guarantees it, even for a lesson booked a moment after the check.
        if ($subject->bookings()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This subject has lessons booked, so it can\'t be deleted.')]);

            return back();
        }

        $subject->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subject deleted.')]);

        return to_route('tenant.subjects.index', $tenant);
    }

    /**
     * What the frontend gets for a subject: no tenant_id, timestamps or pivot data.
     *
     * @return array<string, mixed>
     */
    private function present(Subject $subject): array
    {
        return [
            ...$subject->only(['id', 'name', 'description', 'duration_minutes', 'price']),
            'teachers' => $subject->teachers
                ->sortBy('name')
                ->map(fn (User $teacher) => ['id' => $teacher->id, 'name' => $teacher->name])
                ->values(),
        ];
    }

    /**
     * Everyone who can be picked as a teacher in this workspace.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function teacherOptions(Tenant $tenant): array
    {
        return $tenant->teachers()
            ->orderBy('name')
            ->get(['users.id', 'users.name'])
            ->map(fn (User $teacher) => ['id' => $teacher->id, 'name' => $teacher->name])
            ->all();
    }
}
