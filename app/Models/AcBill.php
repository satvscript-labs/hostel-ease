<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcBill extends Model
{
    use BelongsToHostel, HasFactory, SoftDeletes;

    protected $fillable = [
        'hostel_id',
        'room_id',
        'bill_month',
        'previous_unit',
        'current_unit',
        'unit_price',
        'total_units',
        'total_amount',
        'distribution',
    ];

    protected function casts(): array
    {
        return [
            'bill_month' => 'date',
            'previous_unit' => 'decimal:2',
            'current_unit' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_units' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(AcBillStudent::class);
    }

    /**
     * Compute units + amount from the meter readings.
     */
    public function compute(): void
    {
        $this->total_units = max(0, (float) $this->current_unit - (float) $this->previous_unit);
        $this->total_amount = round($this->total_units * (float) $this->unit_price, 2);
    }
}
