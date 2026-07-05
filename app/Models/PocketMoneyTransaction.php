<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PocketMoneyTransaction extends Model
{
    use BelongsToHostel, SoftDeletes;

    protected $fillable = ['hostel_id', 'student_id', 'type', 'amount', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** Current pocket-money balance for a student (deposits − withdrawals). */
    public static function balanceFor(int $studentId): float
    {
        $deposit = (float) static::where('student_id', $studentId)->where('type', 'deposit')->sum('amount');
        $withdraw = (float) static::where('student_id', $studentId)->where('type', 'withdraw')->sum('amount');

        return round($deposit - $withdraw, 2);
    }
}
