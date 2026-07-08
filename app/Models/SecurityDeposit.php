<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityDeposit extends Model
{
    protected $fillable = [
        'hostel_id',
        'student_id',
        'amount',
        'status',
        'payment_mode_id',
        'receipt_number',
        'collected_on',
        'refunded_on',
        'deducted_amount',
        'refunded_amount',
        'refund_note',
        'created_by',
    ];

    protected $casts = [
        'collected_on' => 'date',
        'refunded_on' => 'date',
        'amount' => 'decimal:2',
        'deducted_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
    ];

    public function hostel()
    {
        return $this->belongsTo(Hostel::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function paymentMode()
    {
        return $this->belongsTo(PaymentMode::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
