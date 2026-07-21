<?php

namespace App\Console\Commands;

use App\Enums\Presence\PresenceState;
use App\Models\Hostel;
use App\Models\PresenceProfile;
use App\Models\Student;
use App\Services\NotificationService;
use App\Support\Tenant;
use Illuminate\Console\Command;

/**
 * Presence P5 — the curfew alert (03 §6). Runs every 15 min; for each branch
 * with a curfew + notifications on, once the grace window past curfew has
 * passed and it hasn't already alerted today, it notifies the warden of the
 * students still out (excluding known absences / on-leave).
 *
 * Guardian-facing messaging stays OFF (owner Q7) — this is a warden/owner
 * dashboard alert. A WhatsApp digest is a small future add on the same list.
 */
class PresenceCurfewCheck extends Command
{
    protected $signature = 'hostelease:presence-curfew-check';

    protected $description = 'Alert the warden about students still out past the branch curfew.';

    public function handle(NotificationService $notifications): int
    {
        $grace = (int) config('presence.curfew_grace_minutes', 30);
        $alerted = 0;

        Hostel::query()
            ->where('curfew_notify', true)
            ->whereNotNull('curfew_from')
            ->cursor()
            ->each(function (Hostel $hostel) use ($notifications, $grace, &$alerted) {
                // Only inside the curfew window, and only after the grace period.
                $windowStart = $hostel->curfewWindowStart();
                if (! $windowStart || now()->lt($windowStart->copy()->addMinutes($grace))) {
                    return;
                }

                // Already alerted for THIS window? Don't repeat.
                if ($hostel->curfew_notified_at && $hostel->curfew_notified_at->gte($windowStart)) {
                    return;
                }

                Tenant::set($hostel->id);

                $out = PresenceProfile::query()
                    ->where('presenceable_type', Student::class)
                    ->enrolled()
                    ->where('state', PresenceState::Out->value)
                    ->where(fn ($q) => $q->whereNull('on_leave_until')
                        ->orWhere('on_leave_until', '<', now()->startOfDay()))
                    ->with('presenceable:id,name')
                    ->get();

                if ($out->isNotEmpty()) {
                    $names = $out->take(5)->map(fn ($p) => $p->presenceable?->name)->filter()->implode(', ');
                    $more = $out->count() > 5 ? ' +'.($out->count() - 5).' more' : '';

                    $notifications->push(
                        $hostel->id, 'presence.curfew', 'curfew:'.$windowStart->toDateString(),
                        $out->count().' '.($out->count() === 1 ? 'student' : 'students').' out past curfew',
                        "Still out during curfew ({$hostel->curfew_from}–{$hostel->curfew_to}): {$names}{$more}.",
                        'danger'
                    );
                    $alerted++;
                }

                $hostel->forceFill(['curfew_notified_at' => now()])->save();
                Tenant::clear();
            });

        $this->info("Curfew check: {$alerted} branch(es) alerted.");

        return self::SUCCESS;
    }
}
