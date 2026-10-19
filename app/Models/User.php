<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements HasLocalePreference
{
    use HasApiTokens, HasFactory, HasPublicId, Notifiable, SoftDeletes;

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

    /**
     * S7.1 columns default to null on a NEW model too, so code that asks "is two-factor
     * on?" or "which language?" works on a user that was just created, not only on one
     * read back from the database (strict mode forbids reading a missing attribute).
     */
    protected $attributes = [
        'locale' => null,
        'two_factor_method' => null,
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
        'two_factor_last_step' => null,
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            // S7.1 — two-factor sign-in. Never mass-assignable: only TwoFactor writes these.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_step' => 'integer',
        ];
    }

    /**
     * The language this person reads (S7.1): mail sent to them — receipts, reminders,
     * codes — goes out in it, wherever it is sent from. The Super Admin reads English.
     */
    public function preferredLocale(): string
    {
        if ($this->role === 'super_admin') {
            return 'en';
        }

        return array_key_exists((string) $this->locale, config('app.available_locales', [])) ? $this->locale : config('app.locale');
    }

    public function hasTwoFactor(): bool
    {
        return app(\App\Services\Auth\TwoFactor::class)->enabled($this);
    }

    public function trustedDevices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }

    /**
     * The mobile IS the login username, so it must be stored in exactly ONE
     * shape or a user simply cannot sign in.
     *
     * It wasn't: LoginController normalises what you type to +91XXXXXXXXXX and
     * looks that up, and UserController/StaffController stored that shape — but
     * HostelService::provision() stored whatever it was handed. Provision a
     * hostel with a plain 10-digit mobile (the natural way to type one) and the
     * owner login it created could NEVER authenticate. Normalising here, at the
     * boundary every write crosses, makes that unrepresentable rather than
     * relying on each caller to remember. Idempotent — callers already passing
     * +91… are unaffected.
     */
    protected function mobile(): Attribute
    {
        return Attribute::set(fn (?string $value) => hostelease_phone($value));
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

    /** Branches this user is the explicit OWNER of (hostels.owner_id). */
    public function ownedHostels(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Hostel::class, 'owner_id');
    }

    /** Whether this login owns at least one branch (owners must stay enabled). */
    public function isOwner(): bool
    {
        return $this->ownedHostels()->exists();
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
        return $this->role === 'hostel_admin' || array_key_exists($this->role, config('hostelease.staff_roles', []));
    }

    /** Areas + readonly flag for this user's role. */
    public function roleAccess(): array
    {
        return config('hostelease.role_access.'.$this->role, ['areas' => [], 'readonly' => true]);
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

    /**
     * Presence module access — an explicit allow-list, NOT the area matrix
     * (owner Q6): viewer holds ['*'] but must be excluded. Owner (+ co-admins)
     * + manager + warden only. Mirrors CheckPresenceAccess so the sidebar shows
     * exactly what the routes allow.
     */
    public function canAccessPresence(): bool
    {
        return config('presence.enabled') && $this->roleMayUsePresence();
    }

    /** The roles Presence is FOR — independent of whether the module is switched on. */
    public function roleMayUsePresence(): bool
    {
        return in_array($this->role, ['hostel_admin', 'manager', 'warden'], true);
    }

    /**
     * Presence is switched off but this role would use it: the sidebar shows a
     * "Coming soon" teaser instead of the module (premium add-on, not on sale yet).
     */
    public function seesPresenceTeaser(): bool
    {
        return ! config('presence.enabled') && $this->roleMayUsePresence();
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

