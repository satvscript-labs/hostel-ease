<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'hostel_id',
        'name',
        'mobile',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    /**
     * Branches (hostels) this admin can access and switch between.
     */
    public function hostels(): BelongsToMany
    {
        return $this->belongsToMany(Hostel::class, 'hostel_user')->withTimestamps();
    }

    /**
     * IDs of every branch this admin may access (pivot + primary).
     */
    public function accessibleHostelIds(): array
    {
        $ids = $this->hostels()->pluck('hostels.id')->all();

        if ($this->hostel_id && ! in_array($this->hostel_id, $ids, true)) {
            $ids[] = $this->hostel_id;
        }

        return $ids;
    }

    public function canAccessHostel(?int $hostelId): bool
    {
        return $hostelId !== null && in_array($hostelId, $this->accessibleHostelIds(), true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isHostelAdmin(): bool
    {
        return $this->role === 'hostel_admin';
    }

    /** The owner login plus any sub-user role — all hostel-scoped app users. */
    public function isHostelStaff(): bool
    {
        return $this->role === 'hostel_admin' || array_key_exists($this->role, config('hsms.staff_roles', []));
    }

    /** Areas + readonly flag for this user's role. */
    public function roleAccess(): array
    {
        return config('hsms.role_access.'.$this->role, ['areas' => [], 'readonly' => true]);
    }

    public function canAccessArea(string $area): bool
    {
        $areas = $this->roleAccess()['areas'] ?? [];

        return in_array('*', $areas, true) || in_array($area, $areas, true);
    }

    public function isReadonly(): bool
    {
        return (bool) ($this->roleAccess()['readonly'] ?? false);
    }

    /** Concrete list of areas (expanding the '*' wildcard) for the client. */
    public function accessibleAreas(): array
    {
        $all = ['property', 'students', 'people', 'staff', 'finance', 'reports', 'backup', 'users'];
        $areas = $this->roleAccess()['areas'] ?? [];

        if (in_array('*', $areas, true)) {
            // Owner manages users; viewer (also '*') does not.
            return $this->isHostelAdmin() ? $all : array_values(array_diff($all, ['users']));
        }

        return array_values(array_intersect($all, $areas));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
