<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use BelongsToHostel, HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'hostel_id',
        'name',
        'photo',
        'mobile',
        'father_mobile',
        'mother_mobile',
        'guardian_mobile',
        'aadhaar',
        'aadhaar_file',
        'address',
        'city',
        'state',
        'occupation_type',
        'college',
        'field_of_study',
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
            // Sensitive personal data (DPDP) — encrypted at rest (P5). Column is
            // TEXT; the number is never searched/indexed so nothing breaks.
            'aadhaar' => 'encrypted',
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

    /**
     * Private-disk P2: a real photo is served through the guarded file route
     * (SecureFileController — auth + tenant scope), never a public Storage URL.
     * The ui-avatars fallback for students with no photo is unchanged.
     */
    public function getPhotoUrlAttribute(): string
    {
        return $this->photo
            ? route('admin.files.show', ['student', $this->id, 'photo'])
            : 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&background=C7D2FE&color=3730A3&bold=true&font-size=0.4&rounded=true';
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

    public function securityDeposits()
    {
        return $this->hasMany(SecurityDeposit::class);
    }
}

