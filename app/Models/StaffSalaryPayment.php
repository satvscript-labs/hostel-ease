<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffSalaryPayment extends Model
{
    use BelongsToHostel, SoftDeletes;

    protected $fillable = [
        'hostel_id', 'staff_id', 'salary_month', 'amount',
        'paid_on', 'mode', 'reference_number', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'salary_month' => 'date',
            'amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
