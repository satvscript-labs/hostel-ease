<?php

namespace App\Models;

use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An automatic volume-discount tier: applies when the billable quantity
 * (branch count) reaches min_quantity (BR-26). Managed in Super Admin Settings.
 */
class DiscountRule extends Model
{
    protected $fillable = [
        'min_quantity',
        'type',
        'value',
        'max_amount',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'active' => 'boolean',
            'min_quantity' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
