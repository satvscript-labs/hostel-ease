<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use BelongsToHostel, HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'hostel_id',
        'category',
        'title',
        'amount',
        'expense_date',
        'paid_to',
        'mode',
        'reference_number',
        'notes',
        'recorded_by',
        'staff_salary_payment_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** The staff salary payment this expense mirrors, when auto-created (W6.2). */
    public function salaryPayment(): BelongsTo
    {
        return $this->belongsTo(StaffSalaryPayment::class, 'staff_salary_payment_id');
    }

    /**
     * Salary-mirror expenses are managed from the Staff page — editing or
     * deleting them here would silently desync the salary record they mirror.
     */
    public function isSalaryLinked(): bool
    {
        return $this->staff_salary_payment_id !== null;
    }

    public function scopeBetween($query, $from, $to)
    {
        return $query->whereBetween('expense_date', [$from, $to]);
    }
}
