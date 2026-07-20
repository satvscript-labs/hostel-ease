<?php

namespace App\Http\Controllers\Admin\Presence;

use App\Enums\Presence\PresenceState;
use App\Enums\Presence\PunchSource;
use App\Http\Controllers\Controller;
use App\Models\PresenceProfile;
use App\Models\PresencePunch;
use App\Services\ActivityLogger;
use App\Services\Presence\PresenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Presence P4 — per-person history (the slide-over drawer body) and the manual
 * corrections that write to the register. A manual punch is real admin intent,
 * so it legitimately joins the immutable log (source=manual, reason required,
 * logged); the derived state is then rebuilt from the log (01 §4, 03 §4).
 */
class HistoryController extends Controller
{
    public function __construct(protected PresenceService $presence, protected ActivityLogger $logger)
    {
    }

    /** Drawer body: header + mini-stats + day-grouped timeline (fetched via AJAX). */
    public function show(PresenceProfile $profile): View
    {
        $profile->load('presenceable');

        $windowStart = now()->subDays(60)->startOfDay();
        $punches = PresencePunch::query()
            ->where('presence_profile_id', $profile->id)
            ->where('punched_at', '>=', $windowStart)
            ->with('device:id,name')
            ->orderByDesc('punched_at')->orderByDesc('id')
            ->get();

        // Group by day (desc); within each day, order ascending so the inter-
        // event durations read forward in time.
        $days = $punches->groupBy(fn ($p) => $p->punched_at->toDateString())
            ->map(fn ($group) => $group->sortBy('punched_at')->values());

        $stats = [
            'punches' => $punches->count(),
            'active_days' => $days->count(),
            'last' => $punches->first(),
        ];

        return view('admin.presence._history_body', compact('profile', 'days', 'stats'));
    }

    public function correct(Request $request, PresenceProfile $profile): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'occurred_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $at = ! empty($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : now();
        // Guard: no future, no older than 24h (a correction, not history rewriting).
        if ($at->isFuture()) {
            $at = now();
        }
        if ($at->lt(now()->subDay())) {
            return back()->with('error', 'Corrections can only be back-dated up to 24 hours.');
        }

        $punch = PresencePunch::create([
            'hostel_id' => $profile->hostel_id,
            'presence_device_id' => null, // a manual correction has no gate device
            'presence_profile_id' => $profile->id,
            'device_user_id' => $profile->device_user_id,
            'punched_at' => $at,
            'direction' => $data['direction'],
            'source' => PunchSource::Manual,
            'note' => $data['reason'],
        ]);

        // Re-derive state from the log (the manual punch may or may not be latest).
        $this->presence->rebuildState($profile->fresh());

        $name = $profile->presenceable?->name ?? 'this person';
        $this->logger->log('presence.correct', "Manual presence correction for {$name}: {$data['direction']} — {$data['reason']}", $punch);

        return back()->with('success', "Correction saved — {$name} marked ".($data['direction'] === 'in' ? 'inside' : 'out').'.');
    }

    public function reset(PresenceProfile $profile): RedirectResponse
    {
        $profile->forceFill([
            'state' => PresenceState::Unknown,
            'state_changed_at' => null,
            'last_punch_id' => null,
            'has_missed_punch' => false,
        ])->save();

        $name = $profile->presenceable?->name ?? 'this person';
        $this->logger->log('presence.reset', "Reset presence state to unknown for {$name}", $profile);

        return back()->with('success', "{$name}'s state reset to unknown.");
    }
}
