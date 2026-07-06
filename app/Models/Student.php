<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Student extends Model
{
    use BelongsToHostel, HasFactory, SoftDeletes;

    protected $fillable = [
        'hostel_id',
        'name',
        'photo',
        'mobile',
        'father_mobile',
        'mother_mobile',
        'guardian_mobile',
        'aadhaar',
        'address',
        'city',
        'state',
        'occupation_type',
        'join_date',
        'leave_date',
        'room_preference',
        'sharing_preference',
        'fee_amount',
        'fee_frequency',
        'credit_balance',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'leave_date' => 'date',
            'credit_balance' => 'decimal:2',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BedAssignment::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(BedAssignment::class)->where('is_active', true);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function getPhotoUrlAttribute(): string
    {
        return $this->photo
            ? Storage::disk('public')->url($this->photo)
            : 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&background=2563eb&color=fff';
    }

    public function getFormattedMobileAttribute(): ?string
    {
        return hostelease_phone($this->mobile);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeLeavingWithin($query, int $days)
    {
        return $query->whereNotNull('leave_date')
            ->where('status', 'active')
            ->whereBetween('leave_date', [now()->startOfDay(), now()->addDays($days)->endOfDay()]);
    }
}

