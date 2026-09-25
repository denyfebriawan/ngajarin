<?php

namespace App\Http\Requests\Tenant;

use App\Models\Subject;
use App\Models\Tenant;
use App\Models\User;
use App\Scheduling\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * A student's booking: a subject, one of its teachers, and a start time.
 *
 * Nothing the browser sends is trusted: the subject must belong to this workspace, the teacher
 * must teach it, and the start must be one the SlotFinder would offer right now.
 */
class StoreBookingRequest extends FormRequest
{
    private ?Subject $subject = null;

    private ?User $teacher = null;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer'],
            'teacher_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'],
        ];
    }

    /**
     * Checks against this workspace's data and the teacher's free times.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $tenant = $this->tenant();

                // No current tenant on this public route, so filter by the workspace explicitly.
                $this->subject = Subject::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->find($this->integer('subject_id'));

                if ($this->subject === null) {
                    $validator->errors()->add('subject_id', 'Choose one of this workspace\'s subjects.');

                    return;
                }

                $this->teacher = $this->subject->teachers()->find($this->integer('teacher_id'));

                if ($this->teacher === null) {
                    $validator->errors()->add('teacher_id', 'Choose a teacher of this subject.');

                    return;
                }

                if ($this->teacher->is($this->user())) {
                    $validator->errors()->add('teacher_id', 'You can\'t book a lesson with yourself.');

                    return;
                }

                // The same calculation that produced the times on the page. This also rejects
                // times inside the notice period, beyond the horizon, or already taken.
                $offered = app(SlotFinder::class)->starts($tenant, $this->teacher, $this->subject, $this->user());
                $start = $this->start();

                if (! collect($offered)->contains(fn (CarbonImmutable $offer) => $offer->equalTo($start))) {
                    $validator->errors()->add('starts_at', 'That time is not available. Please pick another.');
                }
            },
        ];
    }

    public function subject(): Subject
    {
        return $this->subject ?? throw new LogicException('Only available after validation.');
    }

    public function teacher(): User
    {
        return $this->teacher ?? throw new LogicException('Only available after validation.');
    }

    public function start(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('starts_at')->value())->utc();
    }

    private function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        return $tenant instanceof Tenant ? $tenant : throw new LogicException('The route must bind {tenant}.');
    }
}
