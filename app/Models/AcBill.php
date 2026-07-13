<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcBill extends Model
{
    use HasFactory, BelongsToHostel;

    protected $fillable = [
        'hostel_id',
        'room_id',
        'bill_month',
        'previous_reading',
        'current_reading',
        'total_units',
        'unit_price',
        'total_amount'
    ];

    protected function casts(): array
    {
        return [
            'bill_month' => 'date',
            'previous_reading' => 'decimal:2',
            'current_reading' => 'decimal:2',
            'total_units' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'ac_bill_id');
    }
}
