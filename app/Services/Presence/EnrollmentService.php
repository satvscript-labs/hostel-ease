<?php

namespace App\Services\Presence;

use App\Enums\Presence\EnrollmentStatus;
use App\Models\PresenceDevice;
use App\Models\PresenceProfile;
use App\Models\Staff;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Services\Presence\DTO\DeviceUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Enrollment orchestration (P2, 01 §6): the two-worlds handshake between our DB
 * and the wall-mounted devices. Generates the device_user_id, pushes AddUser to
 * every active device of the branch, tracks the profile lifecycle, and revokes
 * cleanly on leave. Face capture happens AT the device — no biometric ever
 * transits our server (01 §3.4).
 *
 * Runs in a tenant (admin) request, so PresenceDevice/PresenceProfile queries
 * are tenant-scoped automatically.
 */
class EnrollmentService
{
    public function __construct(
        protected PresenceDeviceAdapter $adapter,
        protected ActivityLogger $logger,
    ) {
    }

    /** S{id} / T{id} — prefix encodes audience, numeric part binds the record. */
    public function deviceUserIdFor(Model $person): string
    {
        $prefixes = config('presence.user_id_prefixes');
        $prefix = $person instanceof Staff ? $prefixes['staff'] : $prefixes['student'];

        return $prefix.$person->getKey();
    }

    /**
     * Enroll (or re-activate) a person: create/refresh the profile as Pending
     * and push AddUser to every active device. Idempotent — re-enrolling an
     * existing person re-pushes rather than duplicating.
     */
    public function enroll(Model $person, ?string $card = null): PresenceProfile
    {
        $profile = PresenceProfile::query()
            ->withTrashed()
            ->where('presenceable_type', $person::class)
            ->where('presenceable_id', $person->getKey())
            ->first();

        $deviceUserId = $profile?->device_user_id ?? $this->deviceUserIdFor($person);

        if ($profile) {
            if ($profile->trashed()) {
                $profile->restore();
            }
            $profile->forceFill([
                'card_number' => $card ?? $profile->card_number,
                'enrollment_status' => EnrollmentStatus::Pending,
                'enrolled_at' => $profile->enrolled_at ?? now(),
            ])->save();
        } else {
            $profile = PresenceProfile::create([
                'hostel_id' => $person->hostel_id,
                'presenceable_type' => $person::class,
                'presenceable_id' => $person->getKey(),
                'device_user_id' => $deviceUserId,
                'card_number' => $card,
                'enrollment_status' => EnrollmentStatus::Pending,
                'enrolled_at' => now(),
            ]);
        }

        $this->pushToDevices($profile, $person->name, $card);
        $this->logger->log('presence.enroll', "Enrolled {$person->name} on the gate device", $profile);

        return $profile;
    }

    /** Re-push an existing profile to the devices (nudge after a Pending stall). */
    public function rePush(PresenceProfile $profile): void
    {
        $profile->forceFill(['enrollment_status' => EnrollmentStatus::Pending])->save();
        $this->pushToDevices($profile, $profile->presenceable?->name ?? 'User', $profile->card_number);
        $this->logger->log('presence.repush', 'Re-pushed enrollment to devices', $profile);
    }

    /**
     * Revoke: remove the person from every device, mark the profile Removed.
     * Punch history is retained (the register is immutable). Soft-deletes the
     * profile so a future re-enroll reuses the same device_user_id.
     */
    public function revoke(PresenceProfile $profile): void
    {
        foreach ($this->activeDevices() as $device) {
            $this->adapter->deleteUser($profile->device_user_id, $device->serial_number);
        }

        $name = $profile->presenceable?->name ?? 'User';
        $profile->forceFill(['enrollment_status' => EnrollmentStatus::Removed])->save();
        $profile->delete();

        $this->logger->log('presence.revoke', "Revoked gate access for {$name}", $profile);
    }

    /**
     * Bulk-enroll active students on one floor (owner 06 §4 — staged rollout so
     * a terminal isn't swamped). Skips anyone already Active. Returns a small
     * tally for the progress readout.
     *
     * @return array{enrolled:int, skipped:int}
     */
    public function enrollStudentFloor(int $floorId): array
    {
        $students = Student::query()
            ->active()
            ->whereHas('activeAssignment.bed.room', fn ($q) => $q->where('floor_id', $floorId))
            ->with('presenceProfile')
            ->get();

        $enrolled = 0;
        $skipped = 0;
        foreach ($students as $student) {
            if ($student->presenceProfile && $student->presenceProfile->enrollment_status === EnrollmentStatus::Active) {
                $skipped++;

                continue;
            }
            $this->enroll($student);
            $enrolled++;
        }

        return ['enrolled' => $enrolled, 'skipped' => $skipped];
    }

    /**
     * Reconcile Pending profiles against the devices: once the person has
     * registered their face, the device reports them and we flip Pending →
     * Active. Cheap; runs on demand from the UI and can ride the sync command.
     */
    public function reconcile(?PresenceProfile $only = null): int
    {
        $devices = $this->activeDevices();
        if ($devices->isEmpty()) {
            return 0;
        }

        $pending = $only
            ? collect([$only])->filter(fn ($p) => $p->enrollment_status === EnrollmentStatus::Pending)
            : PresenceProfile::query()->where('enrollment_status', EnrollmentStatus::Pending->value)->get();

        $activated = 0;
        foreach ($pending as $profile) {
            foreach ($devices as $device) {
                if ($this->adapter->verifyUserOnDevice($profile->device_user_id, $device->serial_number)) {
                    $profile->forceFill(['enrollment_status' => EnrollmentStatus::Active])->save();
                    $activated++;
                    break;
                }
            }
        }

        return $activated;
    }

    protected function pushToDevices(PresenceProfile $profile, string $name, ?string $card): void
    {
        $user = new DeviceUser(deviceUserId: $profile->device_user_id, name: $name, card: $card);

        $anyFailed = false;
        foreach ($this->activeDevices() as $device) {
            $result = $this->adapter->addUser($user, $device->serial_number);
            if (! $result->success) {
                $anyFailed = true;
            }
        }

        if ($anyFailed) {
            $profile->forceFill(['enrollment_status' => EnrollmentStatus::Failed])->save();
        }
    }

    protected function activeDevices()
    {
        return PresenceDevice::query()->where('is_active', true)->get();
    }
}
