<?php

namespace App\Models;

use App\Enums\DiscountRecurrence;
use App\Enums\DiscountStatus;
use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A manual/negotiated discount attached to an account (optionally scoped to one
 * branch). See BRD §6.7. The discount engine (Phase 3) applies these.
 */
class Discount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_id',
        'branch_id',
        'recurrence',
        'type',
        'value',
        'max_amount',
        'starts_at',
        'ends_at',
        'reason',
        'status',
        'consumed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'recurrence' => DiscountRecurrence::class,
            'type' => DiscountType::class,
            'status' => DiscountStatus::class,
            'value' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'consumed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SubscriptionAccount::class, 'account_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Hostel::class, 'branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', DiscountStatus::Active->value);
    }

    /** Active and within its validity window on the given date. */
    public function scopeAvailableOn(Builder $query, $date): Builder
    {
        return $query->active()
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', $date))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $date));
    }
}
