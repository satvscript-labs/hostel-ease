<?php

namespace App\Models;

use App\Enums\Presence\EnrollmentStatus;
use App\Enums\Presence\PresenceState;
use App\Models\Concerns\BelongsToHostel;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The enrollment / identity mapping: one enrolled person (Student or Staff),
 * carrying the DERIVED current state so boards read it directly. State is
 * maintained transactionally by PresenceService and can be fully re-derived
 * from the punch log at any time (01 §3.2).
 */
class PresenceProfile extends Model
{
    use BelongsToHostel, HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'hostel_id', 'presenceable_type', 'presenceable_id', 'device_user_id',
        'card_number', 'state', 'state_changed_at', 'last_punch_id',
        'has_missed_punch', 'on_leave_until', 'enrollment_status', 'enrolled_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => PresenceState::class,
            'enrollment_status' => EnrollmentStatus::class,
            'has_missed_punch' => 'boolean',
            'state_changed_at' => 'datetime',
            'on_leave_until' => 'date',
            'enrolled_at' => 'datetime',
        ];
    }

    /** A known absence (gone home / on leave) — suppresses curfew + stale flags. */
    public function isOnLeave(): bool
    {
        return $this->on_leave_until !== null && $this->on_leave_until->gte(now()->startOfDay());
    }

    /**
     * "Late" = out, inside the branch curfew window, and not a known absence.
     * The person a warden actually needs to chase (03 §6). Students only —
     * callers gate on person type. Pass the branch to avoid an N+1 per row.
     */
    public function isLate(?\App\Models\Hostel $branch): bool
    {
        return (bool) $branch?->inCurfewWindow()
            && $this->state === PresenceState::Out
            && ! $this->isOnLeave();
    }

    public function presenceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function punches(): HasMany
    {
        return $this->hasMany(PresencePunch::class);
    }

    public function lastPunch(): BelongsTo
    {
        return $this->belongsTo(PresencePunch::class, 'last_punch_id');
    }

    /**
     * "Inside" for longer than the stale threshold — the always-works half of
     * the missed-punch detector (01 §4). Someone genuinely inside for two days
     * without scanning out reads as stale, prompting a manual correction.
     */
    public function isStale(): bool
    {
        if ($this->state !== PresenceState::In || ! $this->state_changed_at) {
            return false;
        }

        $hours = (int) config('presence.sync.stale_hours', 24);

        return $this->state_changed_at->lt(now()->subHours($hours));
    }

    public function scopeInside($query)
    {
        return $query->where('state', PresenceState::In->value);
    }

    public function scopeOutside($query)
    {
        return $query->where('state', PresenceState::Out->value);
    }

    public function scopeEnrolled($query)
    {
        return $query->whereIn('enrollment_status', [
            EnrollmentStatus::Pending->value, EnrollmentStatus::Active->value,
        ]);
    }
}
