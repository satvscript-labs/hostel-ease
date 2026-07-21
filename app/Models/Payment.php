<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use BelongsToHostel, HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'hostel_id',
        'student_id',
        'receipt_number',
        'amount',
        'credit_used',
        'mode', // 'cash', 'upi', 'cheque', etc.
        'reference_number',
        'paid_on',
        'remarks',
        'collected_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_payment')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function scopeBetween($query, $from, $to)
    {
        return $query->whereBetween('paid_on', [$from, $to]);
    }

    /**
     * Real money that entered the hostel — the only rows that count as income
     * (owner decision, W6.2). Two payment modes are internal bookkeeping, not
     * revenue, and summing them inflates every P&L they touch:
     *
     *  - 'credit'      applies a student's EXISTING credit balance to a new
     *                  invoice. That cash was already income the day it
     *                  arrived; counting the application counts it twice.
     *  - 'credit_note' is a proration REFUND for unused days, stored as a
     *                  positive amount. It's money owed back — a liability
     *                  masquerading as revenue if summed.
     */
    public function scopeIncome($query)
    {
        return $query->whereNotIn('mode', ['credit', 'credit_note']);
    }
}
