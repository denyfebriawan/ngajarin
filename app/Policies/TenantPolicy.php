<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;

/**
 * Who may do what to a tenant. Found automatically by Laravel through the naming convention
 * (App\Models\Tenant -> App\Policies\TenantPolicy).
 */
class TenantPolicy
{
    /**
     * Rename the workspace and change its settings: owners only.
     */
    public function update(User $user, Tenant $tenant): bool
    {
        return $user->roleIn($tenant) === Role::Owner;
    }
}
