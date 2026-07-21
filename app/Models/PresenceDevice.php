<?php

namespace App\Models;

use App\Enums\Presence\DeviceDirectionMode;
use App\Enums\Presence\DeviceStatus;
use App\Models\Concerns\BelongsToHostel;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A physical gate unit, tenant-scoped to one branch. Health columns are a cache
 * of the last GetDeviceList; the punch pipeline is identical whether or not the
 * unit also drives a lock (logger-only for now, 01 §5.1).
 */
class PresenceDevice extends Model
{
    use BelongsToHostel, HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'hostel_id', 'serial_number', 'name', 'direction_mode', 'is_active',
        'device_status', 'last_connected_at', 'last_log_at', 'last_synced_at',
        'enrolled_count', 'face_count',
    ];

    protected function casts(): array
    {
        return [
            'direction_mode' => DeviceDirectionMode::class,
            'device_status' => DeviceStatus::class,
            'is_active' => 'boolean',
            'last_connected_at' => 'datetime',
            'last_log_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function punches(): HasMany
    {
        return $this->hasMany(PresencePunch::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
