<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\CollectionMethod;
use App\Enums\OrderKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One payment/charge on an account, covering N branches via its lines
 * (one order = one Razorpay payment = N branch lines).
 */
class SubscriptionOrder extends Model
{
    use HasPublicId, SoftDeletes;

    protected $fillable = [
        'account_id',
        'period',
        'kind',
        'collection',
        'quantity',
        'subtotal',
        'discount_total',
        'amount',
        'payment_status',
        'payment_method',
        'transaction_number',
        'razorpay_order_id',
        'remarks',
        'legacy_subscription_id',
    ];

    protected function casts(): array
    {
        return [
            'period' => BillingPeriod::class,
            'kind' => OrderKind::class,
            'collection' => CollectionMethod::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'quantity' => 'integer',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SubscriptionAccount::class, 'account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SubscriptionOrderLine::class, 'order_id');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('payment_status', PaymentStatus::Paid->value);
    }

    /**
     * Money we are still waiting for — the receivables worklist (S1 item 14).
     * Excludes the kinds that are ₹0 by definition (comp, trial, adjustment): a
     * pending one of those is not owed, it would just inflate the figure.
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        $free = [OrderKind::Comp->value, OrderKind::Trial->value, OrderKind::Adjustment->value];

        // The orWhereNull matters: in SQL `kind NOT IN (…)` is UNKNOWN when kind is
        // NULL, so a bare whereNotIn would silently DROP any order whose kind was
        // never set — money you are owed, invisible in receivables. Every order the
        // service writes has a kind and the S1 migration back-filled the rest, so
        // this is a belt for a row that arrives any other way.
        return $query->where('payment_status', PaymentStatus::Pending->value)
            ->where(fn (Builder $q) => $q->whereNotIn('kind', $free)->orWhereNull('kind'));
    }

    /**
     * A stable, human invoice number for this order — {PREFIX}-{YYYY}-{00042}.
     * Derived from the immutable id + creation year, so it never renumbers and
     * two invoices can't collide. The order id is the ledger's own sequence.
     */
    public function invoiceNumber(): string
    {
        $prefix = config('hostelease.company.invoice_prefix', 'HE');
        $year = ($this->created_at ?? now())->format('Y');

        return sprintf('%s-%s-%05d', $prefix, $year, $this->id);
    }

    /** Paid → a tax invoice / receipt; anything else → a proforma. */
    public function isBillable(): bool
    {
        return $this->payment_status !== PaymentStatus::Failed;
    }
}
