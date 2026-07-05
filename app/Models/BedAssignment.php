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
        'monthly_rent',
        'fee_amount',
        'fee_frequency',
        'is_active',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'leave_date' => 'date',
            'monthly_rent' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function feeFrequencyLabel(): string
    {
        return config('hostelease.fee_frequencies.'.$this->fee_frequency, ucfirst((string) $this->fee_frequency));
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

