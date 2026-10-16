<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    /** Feed entries only a hostel admin (owner / co-admin) sees. */
    public const ADMIN_ONLY_TYPES = ['renewal_reminder', 'subscription_due'];

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
        if ($user->isSuperAdmin()) {
            return $query->whereNull('hostel_id');
        }

        $query->where('hostel_id', $user->hostel_id);

        // Billing reminders are for whoever can act on them — the owner and
        // co-admins — never for staff roles sharing the branch feed (S4 · R7).
        if (! $user->isHostelAdmin()) {
            $query->whereNotIn('type', self::ADMIN_ONLY_TYPES);
        }

        return $query;
    }

    public function markAsRead(): void
    {
        $this->forceFill(['read_at' => now()])->save();
    }
}
