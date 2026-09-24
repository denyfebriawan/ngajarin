<?php

namespace App\Http\Requests\Tenant;

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

        return [
            'name' => [
                'required', 'string', 'max:100',
                // Unique within this workspace only; other workspaces may use the same name.
                Rule::unique('subjects', 'name')
                    ->where('tenant_id', CurrentTenant::resolve()?->tenant->id)
                    ->ignore($subject instanceof Subject ? $subject->id : null),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['required', 'integer', 'between:15,480', 'multiple_of:5'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
        ];
    }
}
