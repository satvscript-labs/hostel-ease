<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAttendance extends Model
{
    use BelongsToHostel;

    protected $fillable = ['hostel_id', 'staff_id', 'date', 'status', 'notes'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
