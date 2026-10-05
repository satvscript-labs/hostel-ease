<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\BillingMode;
use App\Enums\BillingPeriod;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
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
    use HasPublicId, SoftDeletes;

    protected $fillable = [
        'owner_id',
        'period',
        'current_period_start',
        'current_period_end',
        'status',
        'unit_price_override_yearly',
        'unit_price_override_monthly',
        'auto_debit',
        'razorpay_subscription_id',
        'notes',
        // billing_mode is NOT fillable on purpose: only the operator's Account 360
        // action sets it (forceFill), so no create()/update() elsewhere can flip a
        // customer between self-serve and managed by accident.
    ];

    /** A model made in memory is self-serve too — before any refresh() reads the DB default. */
    protected $attributes = [
        'billing_mode' => 'self_serve',
    ];

    protected function casts(): array
    {
        return [
            'period' => BillingPeriod::class,
            'status' => AccountStatus::class,
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'unit_price_override_yearly' => 'decimal:2',
            'unit_price_override_monthly' => 'decimal:2',
            'auto_debit' => 'boolean',
            'billing_mode' => BillingMode::class,
            'billing_mode_changed_at' => 'datetime',
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

    /**
     * May the OWNER start a charge from their own Subscription page right now?
     *
     * The one answer every owner-side surface asks — the page, the checkout and
     * add-branch endpoints, the reminder email, the dashboard notice. Both must hold:
     * the platform-wide kill switch is on, AND this customer is self-serve.
     *
     * Settling money already taken never asks this: a payment in flight when an
     * account is switched to managed still lands (confirm + webhook are ungated).
     */
    public function selfServeEnabled(): bool
    {
        return (bool) config('hostelease.owner_self_serve')
            && $this->billing_mode === BillingMode::SelfServe;
    }

    public function isManaged(): bool
    {
        return $this->billing_mode === BillingMode::Managed;
    }

    /** Whether branches under this account are currently entitled to work. */
    public function isEntitled(): bool
    {
        return $this->status->isEntitled();
    }

    /**
     * Days until the anchor (positive = days remaining, 0 = today, negative =
     * days since it passed). Mirrors Hostel::daysUntilExpiry()'s sign convention.
     */
    public function daysUntilAnchor(): ?int
    {
        return $this->current_period_end
            ? now()->startOfDay()->diffInDays($this->current_period_end->copy()->startOfDay(), false)
            : null;
    }

    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query->whereNotNull('current_period_end')
            ->whereBetween('current_period_end', [now()->startOfDay(), now()->addDays($days)->endOfDay()]);
    }
}
