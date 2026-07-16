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
        'previous_reading',
        'current_reading',
        'total_units',
        'unit_price',
        'total_amount',
        // The persisted day-ledger explanation (W6.3): who occupied the room,
        // for which days, bearing what share — stored at generation time so
        // the bill keeps telling its own story even after assignments change.
        'split_breakdown',
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
            'split_breakdown' => 'array',
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
