<?php

namespace App\Tenancy;

use App\Enums\Role;
use App\Models\Tenant;

/**
 * The tenant the current request is for, and the signed-in user's role in it.
 *
 * Bound into the container by the EnsureTenantMember middleware, so controllers can type-hint it.
 */
final readonly class CurrentTenant
{
    public function __construct(
        public Tenant $tenant,
        public Role $role,
    ) {}

    /**
     * The current tenant, or null when none is set (outside tenant routes, in the console, in jobs).
     */
    public static function resolve(): ?self
    {
        return app()->bound(self::class) ? app(self::class) : null;
    }
}
