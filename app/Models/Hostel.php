<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hostel extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'name',
        'owner_name',
        'mobile',
        'owner_id',
        'email',
        'address',
        'city',
        'state',
        'gst_number',
        'subscription_start',
        'subscription_end',
        'status',
        'logo',
        'settings',
        'registration_token',
    ];

    /** Ensure (and return) the public self-registration token for this hostel. */
    public function ensureRegistrationToken(): string
    {
        if (! $this->registration_token) {
            $this->forceFill(['registration_token' => \Illuminate\Support\Str::random(24)])->save();
        }

        return $this->registration_token;
    }

    /**
     * Largest number of beds this hostel allows in a single room, set via the
     * Layout Builder's "Room Settings" panel. Falls back to the system
     * default until an owner has configured their own.
     */
    public function maxRoomSharing(): int
    {
        return (int) ($this->settings['max_room_sharing'] ?? config('hostelease.default_max_room_sharing', 7));
    }

    protected function casts(): array
    {
        return [
            'subscription_start' => 'date',
            'subscription_end' => 'date',
            'settings' => 'array',
        ];
    }

    /**
     * Normalised for the same reason as User::mobile — and it must match that
     * one exactly: provisioning finds an existing owner with
     * `User::where('mobile', $hostel->mobile)`, so if the two models stored
     * different shapes, a second branch for the same owner would fail to link
     * and silently mint a duplicate admin login instead.
     */
    protected function mobile(): Attribute
    {
        return Attribute::set(fn (?string $value) => hostelease_phone($value));
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The ONE explicit owner of this branch (a hostel_admin User) — the same
     * user the SubscriptionAccount hangs off. Replaces the old fragile
     * inference by mobile match / "first admin in the pivot" (P4 item 14).
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Admin logins with ACCESS to this branch — pivot-based, so a multi-branch
     * owner shows on every branch they hold, not just their primary. (The old
     * hasMany on users.hostel_id listed an owner only on their first branch.)
     */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'hostel_user')
            ->where('role', 'hostel_admin')->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Access gate. A short grace window (config('hostelease.grace_days')) extends
     * access past subscription_end without moving the date itself — the anchor
     * stays the true renewal date everywhere else (quotes, proration, co-termination);
     * only this check sees the grace extension (BR-18).
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if (! $this->subscription_end) {
            return true;
        }

        $graceDays = (int) config('hostelease.grace_days', 0);

        return ! $this->subscription_end->copy()->addDays($graceDays)->isPast();
    }

    public function isExpired(): bool
    {
        return $this->subscription_end && $this->subscription_end->isPast();
    }

    public function daysUntilExpiry(): ?int
    {
        return $this->subscription_end
            ? now()->startOfDay()->diffInDays($this->subscription_end->startOfDay(), false)
            : null;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpiringWithin($query, int $days)
    {
        return $query->whereNotNull('subscription_end')
            ->whereBetween('subscription_end', [now()->startOfDay(), now()->addDays($days)->endOfDay()]);
    }
}
