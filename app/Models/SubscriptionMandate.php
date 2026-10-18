<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A UPI Autopay mandate for one account (S6 · 31_S6_DESIGN.md). Razorpay holds the
 * mandate itself; this is our record of its ids and state, driven by token.* webhooks.
 */
class SubscriptionMandate extends Model
{
    public const PENDING = 'pending';       // set-up started, not yet confirmed by the bank

    public const ACTIVE = 'active';         // token.confirmed — debits may run

    public const PAUSED = 'paused';         // the customer paused it in their UPI app

    public const CANCELLING = 'cancelling'; // we asked Razorpay to cancel; NPCI not yet confirmed

    public const CANCELLED = 'cancelled';

    public const REJECTED = 'rejected';

    /** States that are over: a new mandate may be set up beside these. */
    public const TERMINAL = [self::CANCELLED, self::REJECTED];

    protected $fillable = [
        'account_id', 'provider', 'method', 'customer_id', 'email', 'contact', 'token_id', 'max_amount', 'status',
        'setup_order_id', 'authorised_at', 'cancelled_at', 'cancelled_by', 'failure_reason',
        'failure_notified_for', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'max_amount' => 'decimal:2',
            'authorised_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'failure_notified_for' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SubscriptionAccount::class, 'account_id');
    }

    public function setupOrder(): BelongsTo
    {
        return $this->belongsTo(SubscriptionOrder::class, 'setup_order_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE && $this->token_id !== null;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }

    public function maxAmountPaise(): int
    {
        return (int) round((float) $this->max_amount * 100);
    }

    public function label(): string
    {
        return match ($this->status) {
            self::PENDING => __('Waiting for bank confirmation'),
            self::ACTIVE => __('Active'),
            self::PAUSED => __('Paused by customer'),
            self::CANCELLING => __('Turning off…'),
            self::CANCELLED => $this->cancelled_by === 'customer' ? __('Cancelled by customer') : __('Off'),
            self::REJECTED => __('Not set up — the bank declined'),
            default => ucfirst($this->status),
        };
    }
}
