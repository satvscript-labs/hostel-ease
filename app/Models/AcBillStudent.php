<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcBillStudent extends Model
{
    use BelongsToHostel, HasFactory, SoftDeletes;

    protected $fillable = [
        'hostel_id',
        'ac_bill_id',
        'student_id',
        'amount',
        'paid_amount',
        'status',
        'promise_date',
        'promise_note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'promise_date' => 'date',
        ];
    }

    public function acBill(): BelongsTo
    {
        return $this->belongsTo(AcBill::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function getBalanceAttribute(): float
    {
        return max(0, (float) $this->amount - (float) $this->paid_amount);
    }

    /**
     * Sync status from amount vs paid_amount (used by PaymentService).
     */
    public function recalculate(): void
    {
        $this->status = $this->balance <= 0
            ? 'paid'
            : ((float) $this->paid_amount > 0 ? 'partial' : 'due');
    }
}
