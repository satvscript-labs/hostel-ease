<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use BelongsToHostel, HasFactory, SoftDeletes;

    protected $fillable = [
        'hostel_id',
        'plan',
        'start_date',
        'end_date',
        'amount',
        'payment_status',
        'payment_method',
        'transaction_number',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }
}
