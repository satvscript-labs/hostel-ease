<?php

namespace App\Http\Controllers\Admin\Presence;

use App\Http\Controllers\Controller;
use App\Models\Floor;
use App\Models\PresenceProfile;
use App\Models\PresencePunch;
use App\Models\Staff;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Services\Presence\EnrollmentService;
use App\Services\Presence\PresenceService;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Presence P2 — enrollment actions and the quarantine Match flow. Every device
 * push goes through EnrollmentService (the two-worlds handshake); state
 * re-derivation goes through PresenceService (the single source of truth).
 */
class EnrollmentController extends Controller
{
    public function __construct(
        protected EnrollmentService $enrollment,
        protected PresenceService $presence,
        protected ActivityLogger $logger,
    ) {
    }

    /** Enroll one person (student or staff), resolved by opaque public_id. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'person_type' => ['required', Rule::in(['student', 'staff'])],
            'person_id' => ['required', 'string'],
            'card_number' => ['nullable', 'string', 'max:64'],
        ]);

        $person = $this->resolvePerson($data['person_type'], $data['person_id']);

        $this->enrollment->enroll($person, $data['card_number'] ?? null);

        return back()->with('success', "Pushed to the gate — ask {$person->name} to register their face at the device.");
    }

    public function rePush(PresenceProfile $profile): RedirectResponse
    {
        $this->enrollment->rePush($profile);

        return back()->with('success', 'Re-pushed to the gate device.');
    }

    public function revoke(PresenceProfile $profile): RedirectResponse
    {
        $name = $profile->presenceable?->name ?? 'This person';
        $this->enrollment->revoke($profile);

        return back()->with('success', "{$name} removed from every gate device. Their history is kept.");
    }

    /** Floor-phased bulk enroll (owner 06 §4) — staged so a terminal isn't swamped. */
    public function enrollFloor(Request $request): RedirectResponse
    {
        // Scope the exists check to THIS branch: an unscoped rule would pass a
        // foreign floor id, then the tenant-scoped Floor::find() below returns
        // null and $floor->name fatals. Reject it cleanly at validation instead.
        // Scope the exists check to THIS branch: an unscoped rule would pass a
        // foreign floor id, then the tenant-scoped Floor::find() below returns
        // null and $floor->name fatals. Reject it cleanly at validation instead.
        $data = $request->validate([
            'floor_id' => ['required', Rule::exists('floors', 'id')->where('hostel_id', Tenant::id())],
        ]);

        $tally = $this->enrollment->enrollStudentFloor((int) $data['floor_id']);
        $floor = Floor::findOrFail($data['floor_id']);

        return back()->with('success',
            "{$floor->name}: {$tally['enrolled']} enrolled, {$tally['skipped']} already active.");
    }

    /** Confirm device-side registration for Pending profiles (Pending → Active). */
    public function reconcile(): RedirectResponse
    {
        $activated = $this->enrollment->reconcile();

        return back()->with('success', $activated > 0
            ? "{$activated} enrollment(s) confirmed active."
            : 'No new registrations found yet — ask pending people to scan their face at the device.');
    }

    /**
     * Bind an unmatched device UserID (and all its punches) to a person. The raw
     * id keeps living in the register; the punches gain a profile and the
     * person's state is re-derived from them.
     */
    public function matchQuarantine(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'device_user_id' => ['required', 'string'],
            'person_type' => ['required', Rule::in(['student', 'staff'])],
            'person_id' => ['required', 'string'],
        ]);

        $person = $this->resolvePerson($data['person_type'], $data['person_id']);

        DB::transaction(function () use ($data, $person) {
            // Ensure the person has a profile (create Active — they're clearly
            // enrolled at the device, since they're punching under this id).
            $profile = $person->presenceProfile;
            if (! $profile) {
                $profile = PresenceProfile::create([
                    'hostel_id' => $person->hostel_id,
                    'presenceable_type' => $person::class,
                    'presenceable_id' => $person->getKey(),
                    'device_user_id' => $data['device_user_id'],
                    'enrollment_status' => \App\Enums\Presence\EnrollmentStatus::Active,
                    'enrolled_at' => now(),
                ]);
            }

            // Retro-attach every unmatched punch carrying this raw id.
            PresencePunch::query()
                ->whereNull('presence_profile_id')
                ->where('device_user_id', $data['device_user_id'])
                ->update(['presence_profile_id' => $profile->id]);

            $this->presence->rebuildState($profile->fresh());
            $this->logger->log('presence.match', "Matched device id {$data['device_user_id']} to {$person->name}", $profile);
        });

        return back()->with('success', "“{$data['device_user_id']}” is now {$person->name}. Their past punches were attached.");
    }

    /** Resolve a Student/Staff by opaque public_id, tenant-scoped by the models. */
    protected function resolvePerson(string $type, string $publicId): Student|Staff
    {
        return $type === 'staff'
            ? Staff::where('public_id', $publicId)->firstOrFail()
            : Student::where('public_id', $publicId)->firstOrFail();
    }
}
