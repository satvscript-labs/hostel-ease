<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BedAssignment extends Model
{
    use BelongsToHostel, HasFactory, SoftDeletes;

    protected $fillable = [
        'hostel_id',
        'bed_id',
        'student_id',
        'join_date',
        'leave_date',
        // AC meter readings captured at the occupancy change (W6.3): the
        // anchors that make the AC bill split exact instead of day-estimated.
        // Null on non-AC rooms and on rows from before the feature.
        'join_meter_reading',
        'leave_meter_reading',
        // The room's rent AT THE TIME OF THIS STAY. Room rents change; the
        // history of what a stay was worth shouldn't change with them.
        // (There is deliberately NO fee_amount/fee_frequency here: the
        // 2026_07_06 recreate_student_fees_flow migration moved the fee plan
        // to `students` and dropped those columns — the student holds one
        // current plan, re-confirmed on every move. See W6.4.)
        'monthly_rent',
        'is_active',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'leave_date' => 'date',
            'join_meter_reading' => 'decimal:2',
            'leave_meter_reading' => 'decimal:2',
            'monthly_rent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function durationInDays(): int
    {
        $end = $this->leave_date ?? now();

        return (int) $this->join_date->diffInDays($end);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

