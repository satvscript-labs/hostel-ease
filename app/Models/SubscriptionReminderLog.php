<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reminder window, for one cycle of one account (S4 · 24_S4_DESIGN.md).
 *
 * `channel` is the best channel that delivered it (email › in_app); `status` says how
 * the email went (sent · no_email · failed); `detail` keeps the per-channel result —
 * the data S6 uses to decide whether WhatsApp is worth building (D7).
 */
class SubscriptionReminderLog extends Model
{
    protected $fillable = ['account_id', 'cycle_end', 'window', 'channel', 'status', 'attempts', 'detail'];

    protected function casts(): array
    {
        return [
            'cycle_end' => 'date',
            'detail' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SubscriptionAccount::class, 'account_id');
    }

    /** Plain-words label for a window key, for Account 360. */
    public static function windowLabel(string $window): string
    {
        return match (true) {
            $window === 'due_0' => __('Renewal day'),
            str_starts_with($window, 'due_') => __(':n days before', ['n' => (int) substr($window, 4)]),
            $window === 'grace' => __('Grace started'),
            $window === 'expired' => __('Expired'),
            default => $window,
        };
    }
}
