<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\CollectionMethod;
use App\Enums\OrderKind;
use App\Enums\PaymentLinkStatus;
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
        'manual_discount_id',
        'amount',
        'payment_status',
        'payment_method',
        'transaction_number',
        'razorpay_order_id',
        'payment_link_id',
        'payment_link_url',
        'payment_link_ref',
        'payment_link_status',
        'payment_link_expires_at',
        'payment_link_attempts',
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
            'payment_link_status' => PaymentLinkStatus::class,
            'payment_link_expires_at' => 'datetime',
            'payment_link_attempts' => 'integer',
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
     * Orders carrying a payment link that can still take money (S2).
     *
     * This is the operator's "sent, not yet paid" worklist, and it is a different
     * question from `outstanding()`: a charge can be owed with no link at all (an
     * offline proforma), and a link can be dead (expired/cancelled) while the
     * charge is still owed.
     */
    public function scopeWithLiveLink(Builder $query): Builder
    {
        return $query->whereNotNull('payment_link_id')
            ->whereIn('payment_link_status', [
                PaymentLinkStatus::Created->value,
                PaymentLinkStatus::PartiallyPaid->value,
            ]);
    }

    /** Whether this charge has a link that can still take money. */
    public function hasLiveLink(): bool
    {
        return (bool) $this->payment_link_id && (bool) $this->payment_link_status?->isLive();
    }

    /**
     * Whether the owner has opened this charge for online checkout (S3) — a Razorpay
     * order exists for it and it is still unpaid. Razorpay orders cannot be
     * cancelled, so this instrument stays payable for as long as the charge is open.
     */
    public function hasOpenCheckout(): bool
    {
        return $this->payment_status === PaymentStatus::Pending && (bool) $this->razorpay_order_id;
    }

    /**
     * Whether a link may be issued for this charge. Not while one is live, and not
     * while the owner has it open in checkout: one live instrument per charge, or the
     * customer has two ways to pay one bill (design 14 §3).
     */
    public function canIssueLink(): bool
    {
        return $this->payment_status === PaymentStatus::Pending
            && ! $this->hasLiveLink()
            && ! $this->hasOpenCheckout()
            && $this->amountPaise() >= 100;
    }

    /**
     * The charge in paise, which is the only unit Razorpay accepts.
     *
     * The `decimal:2` cast returns a STRING, so the float cast is load-bearing
     * rather than decorative, and round() before the int cast stops 19999.999…
     * truncating to ₹199.99 short.
     */
    public function amountPaise(): int
    {
        return (int) round(((float) $this->amount) * 100);
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
