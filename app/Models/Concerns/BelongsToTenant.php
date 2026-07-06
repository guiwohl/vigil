<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Row-level multi-tenancy. When a user is authenticated, every query on the model
 * is auto-scoped to that user's tenant, and new records inherit their tenant_id.
 * Unauthenticated contexts (queue workers, the scheduler, the public status page)
 * are intentionally un-scoped — they address tenants explicitly and system-wide.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            if ($tenantId = self::currentTenantId()) {
                $query->where($query->getModel()->getTable().'.tenant_id', $tenantId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->tenant_id) && $tenantId = self::currentTenantId()) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    protected static function currentTenantId(): ?int
    {
        return Auth::check() ? Auth::user()->tenant_id : null;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
