<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits every query on a tenant-owned model to the current tenant's rows.
 *
 * @implements Scope<Model>
 */
class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $current = CurrentTenant::resolve();

        if ($current === null) {
            // Fail closed: with no tenant known, match no rows rather than every tenant's rows.
            $builder->whereRaw('false');

            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $current->tenant->id);
    }
}
