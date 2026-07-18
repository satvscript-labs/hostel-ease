<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHostel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use BelongsToHostel, SoftDeletes;

    protected $table = 'staff';

    protected $fillable = [
        'hostel_id', 'name', 'designation', 'mobile',
        'monthly_salary', 'join_date', 'address', 'is_active', 'notes',
        'aadhaar_number', 'aadhaar_file', 'photo',
    ];

    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:2',
            'join_date' => 'date',
            'is_active' => 'boolean',
            // Sensitive personal data (DPDP) — encrypted at rest (P5). Column is
            // TEXT; the number is never searched/indexed so nothing breaks.
            'aadhaar_number' => 'encrypted',
        ];
    }

    /**
     * Normalised at the boundary every write crosses, same as User/Hostel
     * (W6.4). StaffController hand-rolled its own +91 concat, so anything
     * written from elsewhere — the seeder, a test, a future importer — stored
     * a different shape and searching by mobile silently missed it.
     */
    protected function mobile(): Attribute
    {
        return Attribute::set(fn (?string $value) => hostelease_phone($value));
    }

    /**
     * The photo was uploaded, compressed on the way in, and replaced on edit —
     * and never once displayed: both pages drew an initial-letter avatar
     * instead. Write-only since the field was added (fixed W7.1).
     *
     * Returns the guarded file ROUTE, not a Storage::url() (private-disk P2):
     * the file has no public URL any more — it's streamed by
     * SecureFileController after auth + tenant scope. The route is stable
     * across the P3 move, so nothing here changes when the bytes relocate.
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->photo
            ? route('admin.files.show', ['staff', $this->id, 'photo'])
            : null);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(StaffAttendance::class);
    }

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(StaffSalaryPayment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
