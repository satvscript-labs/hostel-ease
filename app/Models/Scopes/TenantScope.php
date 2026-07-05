<?php

namespace App\Models\Scopes;

use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restrict queries to the currently resolved hostel (tenant).
 *
 * Super Admin requests run without a bound tenant, so the scope is a no-op
 * for them and they see data across all hostels.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $hostelId = Tenant::id();

        if ($hostelId !== null) {
            $builder->where($model->getTable().'.hostel_id', $hostelId);
        }
    }
}
