<?php

namespace App\Models\Concerns;

use App\Models\Hostel;
use App\Models\Scopes\TenantScope;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applies multi-tenant behaviour to a model:
 *  - global TenantScope so every query is hostel-bound,
 *  - auto-fills hostel_id on create from the active tenant.
 */
trait BelongsToHostel
{
    public static function bootBelongsToHostel(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (empty($model->hostel_id) && Tenant::check()) {
                $model->hostel_id = Tenant::id();
            }
        });
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    /**
     * Query builder that ignores the tenant scope (Super Admin / jobs).
     */
    public function scopeAcrossHostels($query)
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
