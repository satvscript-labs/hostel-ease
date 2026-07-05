<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MonthlyRent extends Model
{
    use BelongsToHostel, HasFactory, SoftDeletes;

    protected $fillable = [
        'hostel_id',
        'student_id',
        'rent_month',
        'amount',
        'paid_amount',
        'balance',
        'status',
        'due_date',
        'promise_date',
        'promise_note',
    ];

    protected function casts(): array
    {
        return [
            'rent_month' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'due_date' => 'date',
            'promise_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function recalculate(): void
    {
        $this->balance = max(0, (float) $this->amount - (float) $this->paid_amount);
        $this->status = $this->balance <= 0
            ? 'paid'
            : ((float) $this->paid_amount > 0 ? 'partial' : 'due');
    }
}
