<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use BelongsToHostel, SoftDeletes;

    protected $table = 'staff';

    protected $fillable = [
        'hostel_id', 'name', 'designation', 'mobile',
        'monthly_salary', 'join_date', 'address', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:2',
            'join_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(StaffAttendance::class);
    }

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(StaffSalaryPayment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
