<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Models\Tenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TenantController extends Controller
{
    /**
     * Create a workspace and make the signed-in user its owner.
     */
    public function store(StoreTenantRequest $request): RedirectResponse
    {
        try {
            $tenant = DB::transaction(function () use ($request): Tenant {
                $tenant = Tenant::create($request->validated());
                $tenant->addMember($request->user(), Role::Owner);

                return $tenant;
            });
        } catch (UniqueConstraintViolationException) {
            // Two people can pass the `unique` validation rule for the same slug at the same
            // moment; the database's unique index lets only one insert through.
            throw ValidationException::withMessages([
                'slug' => 'This address is already taken.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace created.')]);

        return to_route('tenant.dashboard', $tenant);
    }
}
