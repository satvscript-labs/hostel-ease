<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use BelongsToHostel, HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'hostel_id',
        'student_id',
        'type', // 'fee', 'rent', 'ac', 'other'
        'ac_bill_id',
        'title',
        'amount',
        'billing_cycle_start',
        'billing_cycle_end',
        'paid_amount',
        'balance', // Note: this is typically virtual in migration, or calculated
        'status',
        'due_date',
        'promise_date',
        'promise_note',
        'is_generated_by_system',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'billing_cycle_start' => 'date',
            'billing_cycle_end' => 'date',
            'due_date' => 'date',
            'promise_date' => 'date',
            'is_generated_by_system' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** The AC bill this invoice is a share of (null for every other type). */
    public function acBill(): BelongsTo
    {
        return $this->belongsTo(AcBill::class);
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'invoice_payment')
            ->withPivot('amount')
            ->withTimestamps();
    }

    /**
     * Recalculate balance + status from the stored amounts.
     */
    public function recalculate(): void
    {
        // balance is virtual in DB but we might need it computed dynamically
        // if not automatically fetched from DB after modification
        // $this->balance = max(0, (float) $this->amount - (float) $this->paid_amount);
        // However, if it's virtual we can't manually assign to it easily without causing SQL errors
        // on save depending on DB setup. Let's just update the status.
        $balance = max(0, (float) $this->amount - (float) $this->paid_amount);
        $this->status = $balance <= 0
            ? 'paid'
            : ((float) $this->paid_amount > 0 ? 'partial' : 'pending');
    }
}
