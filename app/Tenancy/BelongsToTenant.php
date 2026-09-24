<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * For models that belong to one tenant (a `tenant_id` column): reads are limited to the current
 * tenant by TenantScope, new records get the current tenant automatically, and records can never
 * be written into, or moved to, another tenant.
 *
 * Code that must see every tenant's rows (admin tools, some jobs) opts out explicitly with
 * Model::withoutGlobalScope(TenantScope::class).
 *
 * @phpstan-require-extends Model
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $currentId = CurrentTenant::resolve()?->tenant->id;
            $tenantId = $model->getAttribute('tenant_id');

            if ($tenantId === null) {
                if ($currentId === null) {
                    throw new LogicException('Cannot create a '.$model::class.' without a tenant.');
                }

                $model->setAttribute('tenant_id', $currentId);
            } elseif ($currentId !== null && (int) $tenantId !== $currentId) {
                throw new LogicException('Cannot create a '.$model::class.' for another tenant.');
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw new LogicException('Cannot move a '.$model::class.' to another tenant.');
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
