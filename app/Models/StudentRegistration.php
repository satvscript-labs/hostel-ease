<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentRegistration extends Model
{
    use BelongsToHostel;

    protected $fillable = [
        'hostel_id', 'name', 'mobile', 'father_mobile', 'mother_mobile',
        'aadhaar', 'address', 'city', 'state', 'occupation_type',
        'joining_date', 'photo', 'status', 'student_id', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
