<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hostel_id',
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'level',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Limit to the feed visible to a user: super admins see the system feed
     * (hostel_id null); hostel admins see their hostel's feed.
     */
    public function scopeForUser($query, $user)
    {
        return $user->isSuperAdmin()
            ? $query->whereNull('hostel_id')
            : $query->where('hostel_id', $user->hostel_id);
    }

    public function markAsRead(): void
    {
        $this->forceFill(['read_at' => now()])->save();
    }
}
