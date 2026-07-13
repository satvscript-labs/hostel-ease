<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One branch's coverage under an order: end_date is the account anchor after
 * this charge.
 */
class SubscriptionOrderLine extends Model
{
    protected $fillable = [
        'order_id',
        'branch_id',
        'amount',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SubscriptionOrder::class, 'order_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Hostel::class, 'branch_id');
    }
}
