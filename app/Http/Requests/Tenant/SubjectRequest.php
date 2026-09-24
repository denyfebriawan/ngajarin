<?php

namespace App\Http\Requests\Tenant;

use App\Enums\Role;
use App\Models\Subject;
use App\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates creating and updating a subject (the rules are the same for both).
 */
class SubjectRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $subject = $this->route('subject');
        $tenantId = CurrentTenant::resolve()?->tenant->id;

        return [
            'name' => [
                'required', 'string', 'max:100',
                // Unique within this workspace only; other workspaces may use the same name.
                Rule::unique('subjects', 'name')
                    ->where('tenant_id', $tenantId)
                    ->ignore($subject instanceof Subject ? $subject->id : null),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['required', 'integer', 'between:15,480', 'multiple_of:5'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            // Optional: a subject may have no teacher yet.
            'teacher_ids' => ['array'],
            'teacher_ids.*' => [
                'integer', 'distinct',
                // Must be an owner or tutor of this workspace, not a student or an outsider.
                Rule::exists('tenant_user', 'user_id')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('role', array_map(fn (Role $role) => $role->value, Role::teaching())),
            ],
        ];
    }

    /**
     * The subject's own fields, without teacher_ids (which live in the pivot table).
     *
     * @return array<string, mixed>
     */
    public function subjectData(): array
    {
        return $this->safe()->except('teacher_ids');
    }

    /**
     * The chosen teachers' user ids; empty when none are ticked.
     *
     * @return list<int>
     */
    public function teacherIds(): array
    {
        return array_values(array_map('intval', $this->validated('teacher_ids', [])));
    }
}
