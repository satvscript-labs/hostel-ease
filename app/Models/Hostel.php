<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hostel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'owner_name',
        'mobile',
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

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function admins(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'hostel_admin');
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

    public function isActive(): bool
    {
        return $this->status === 'active'
            && (! $this->subscription_end || ! $this->subscription_end->isPast());
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
