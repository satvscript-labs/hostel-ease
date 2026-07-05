<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bed extends Model
{
    use BelongsToHostel, HasFactory, SoftDeletes;

    protected $fillable = [
        'hostel_id',
        'room_id',
        'bed_number',
        'status',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BedAssignment::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(BedAssignment::class)->where('is_active', true);
    }

    public function currentStudent(): HasOne
    {
        return $this->hasOne(BedAssignment::class)->where('is_active', true)
            ->with('student');
    }

    public function isEmpty(): bool
    {
        return $this->status === 'empty';
    }

    public function statusColor(): string
    {
        return config("hsms.bed_statuses.{$this->status}.color", '#9ca3af');
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
