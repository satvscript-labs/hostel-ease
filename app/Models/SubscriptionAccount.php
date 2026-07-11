<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\BillingPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One billing account per owner. Holds the single subscription clock (the
 * anchor = current_period_end) all of the owner's branches renew against.
 */
class SubscriptionAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_id',
        'period',
        'current_period_start',
        'current_period_end',
        'status',
        'unit_price_override',
        'auto_debit',
        'razorpay_subscription_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period' => BillingPeriod::class,
            'status' => AccountStatus::class,
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'unit_price_override' => 'decimal:2',
            'auto_debit' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SubscriptionOrder::class, 'account_id');
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class, 'account_id');
    }

    /** Whether branches under this account are currently entitled to work. */
    public function isEntitled(): bool
    {
        return $this->status->isEntitled();
    }
}
