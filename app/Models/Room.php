<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use BelongsToHostel, HasFactory, SoftDeletes;

    protected $fillable = [
        'hostel_id',
        'floor_id',
        'room_number',
        'room_type',
        'sharing',
        'rent',
    ];

    protected function casts(): array
    {
        return [
            'sharing' => 'integer',
            'rent' => 'decimal:2',
        ];
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }

    public function acBills(): HasMany
    {
        return $this->hasMany(AcBill::class);
    }

    public function isAc(): bool
    {
        return $this->room_type === 'ac';
    }

    public function occupiedBedsCount(): int
    {
        return $this->beds()->where('status', 'occupied')->count();
    }
}
