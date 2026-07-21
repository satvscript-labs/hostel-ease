<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SecurityDeposit extends Model
{
    /**
     * BelongsToHostel was MISSING until W6.4 — the only model in the app
     * without it. Every other table got the global tenant scope; this one's
     * refund/revert routes bound deposits by bare id, so any authenticated
     * admin could mutate another hostel's deposit money. The trait makes
     * cross-tenant rows invisible (route binding 404s them) and auto-fills
     * hostel_id on create.
     */
    use BelongsToHostel, HasPublicId, SoftDeletes;

    protected $fillable = [
        'hostel_id',
        'student_id',
        'amount',
        'status', // 'collected' | 'refunded'
        'payment_mode_id',
        'receipt_number',
        'collected_on',
        'refunded_on',
        'deducted_amount',
        'refunded_amount',
        'refund_note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'collected_on' => 'date',
            'refunded_on' => 'date',
            'amount' => 'decimal:2',
            'deducted_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function paymentMode(): BelongsTo
    {
        return $this->belongsTo(PaymentMode::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
