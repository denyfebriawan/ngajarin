<?php

namespace App\Http\Requests\Tenant;

use App\Demo\DemoWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Lowercase words joined by single hyphens, e.g. "budi-math". It is the tenant's URL
            // (/t/budi-math), so it is checked here and again by the unique index in Postgres.
            // The demo's address is reserved even before the demo has been built.
            'slug' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn([DemoWorkspace::SLUG]), 'unique:tenants,slug'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Use lowercase letters, numbers and single hyphens only.',
            'slug.not_in' => 'This address is already taken.',
            'slug.unique' => 'This address is already taken.',
        ];
    }
}
