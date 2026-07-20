<?php

namespace App\Models;

use App\Enums\Presence\PresenceState;
use App\Enums\Presence\PunchSource;
use App\Models\Concerns\BelongsToHostel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of the immutable in/out register — exactly what a device reported (or
 * a real admin correction, source=manual). Append-only: never edited, never
 * carries a fabricated event. Deduped by (device, device_user_id, punched_at).
 */
class PresencePunch extends Model
{
    use BelongsToHostel;

    protected $fillable = [
        'hostel_id', 'presence_device_id', 'presence_profile_id',
        'device_user_id', 'punched_at', 'direction', 'verify_mode', 'source', 'note',
    ];

    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'direction' => PresenceState::class,
            'source' => PunchSource::class,
        ];
    }

    // Explicit foreign keys: the relation names (device/profile) would otherwise
    // make Eloquent infer device_id / profile_id, but the columns are prefixed.
    public function device(): BelongsTo
    {
        return $this->belongsTo(PresenceDevice::class, 'presence_device_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(PresenceProfile::class, 'presence_profile_id');
    }

    /** Unmatched scans — a device UserID we could not bind to a person. */
    public function scopeUnmatched($query)
    {
        return $query->whereNull('presence_profile_id');
    }
}
