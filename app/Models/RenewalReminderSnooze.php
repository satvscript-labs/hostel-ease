<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** "Remind me later" on the dashboard renewal card — per user, per cycle (S4). */
class RenewalReminderSnooze extends Model
{
    protected $fillable = ['user_id', 'account_id', 'cycle_end', 'until'];

    protected function casts(): array
    {
        return [
            'cycle_end' => 'date',
            'until' => 'date',
        ];
    }
}
