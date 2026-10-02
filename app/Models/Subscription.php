<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @deprecated since S1 (decision D8) — HISTORICAL ARCHIVE ONLY. Do not write.
 *
 * The original per-branch ledger. Every charge is now a {@see SubscriptionOrder}
 * with a {@see SubscriptionOrderLine} per branch, and branch coverage is a
 * projection over those lines ({@see \App\Services\Billing\CoverageMirror}).
 * Keeping two ledgers meant consolidated renewals appeared in only one of them, so
 * per-branch history silently under-reported (finding F4).
 *
 * The rows are preserved and readable at /superadmin/subscriptions. Nothing in the
 * application writes them, and nothing should: a write here would be invisible to
 * the projection and would be undone by the next coverage sync.
 */
class Subscription extends Model
{
    use BelongsToHostel, HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'hostel_id',
        'plan',
        'start_date',
        'end_date',
        'amount',
        'payment_status',
        'payment_method',
        'transaction_number',
        'razorpay_order_id',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }
}
