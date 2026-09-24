<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TenantSettingsController extends Controller
{
    /**
     * Show the workspace settings page. The tenant's name and slug come from the shared
     * `currentTenant` prop, so no page props are needed.
     */
    public function edit(): Response
    {
        return Inertia::render('tenant/settings');
    }

    /**
     * Update the workspace's settings. The slug is deliberately not editable: changing it would
     * break every link a tutor has already shared.
     */
    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace updated.')]);

        return back();
    }
}
