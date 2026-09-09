<?php

namespace App\Http\Controllers\Admin\Presence;

use App\Enums\Presence\DeviceConnectionMode;
use App\Enums\Presence\DeviceDirectionMode;
use App\Enums\Presence\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Floor;
use App\Models\PresenceDevice;
use App\Models\PresencePunch;
use App\Models\Staff;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Services\Presence\PresenceDeviceAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Presence P2 — the Devices & Enrollment control room. Hardware registry +
 * the enrollment manager + the unmatched-scan quarantine, one workflow surface
 * (02 §6).
 */
class DeviceController extends Controller
{
    public function __construct(
        protected PresenceDeviceAdapter $adapter,
        protected ActivityLogger $logger,
    ) {
    }

    public function index(Request $request): View
    {
        $devices = PresenceDevice::query()->orderBy('name')->get();

        // Enrollment manager: students OR staff, with their profile (if any).
        $tab = $request->string('people', 'students')->toString();
        $tab = in_array($tab, ['students', 'staff'], true) ? $tab : 'students';

        $roster = $tab === 'staff'
            ? Staff::query()->active()->with('presenceProfile')->orderBy('name')->paginate(20, ['*'], 'roster')
            : Student::query()->active()->with(['presenceProfile', 'activeAssignment.bed.room.floor'])->orderBy('name')->paginate(20, ['*'], 'roster');

        $roster->withQueryString();

        // Quarantine — distinct unmatched device ids in the register.
        $unmatched = PresencePunch::query()
            ->unmatched()
            ->selectRaw('device_user_id, COUNT(*) as punch_count, MAX(punched_at) as last_seen')
            ->groupBy('device_user_id')
            ->orderByDesc('last_seen')
            ->get();

        $stats = [
            'devices' => $devices->count(),
            'online' => $devices->where('device_status', \App\Enums\Presence\DeviceStatus::Online)->count(),
            'enrolled' => \App\Models\PresenceProfile::query()->enrolled()->count(),
            'pending' => \App\Models\PresenceProfile::query()->where('enrollment_status', EnrollmentStatus::Pending->value)->count(),
            'unmatched' => $unmatched->count(),
        ];

        $floors = Floor::query()->orderBy('name')->get(['id', 'name']);

        // People options for the quarantine Match picker — only when needed.
        // Composite id "type:public_id" so one searchable picker spans both.
        $matchPeople = collect();
        if ($unmatched->isNotEmpty()) {
            $matchPeople = Student::query()->active()->orderBy('name')->get(['id', 'public_id', 'name'])
                ->map(fn ($s) => ['id' => 'student:'.$s->public_id, 'name' => $s->name, 'sub' => __('Student')])
                ->concat(
                    Staff::query()->active()->orderBy('name')->get(['id', 'public_id', 'name', 'designation'])
                        ->map(fn ($s) => ['id' => 'staff:'.$s->public_id, 'name' => $s->name, 'sub' => $s->designation ?: __('Staff')])
                )->values();
        }

        return view('admin.presence.devices', compact('devices', 'roster', 'tab', 'unmatched', 'stats', 'floors', 'matchPeople'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'serial_number' => ['required', 'string', 'max:64', Rule::unique('presence_devices', 'serial_number')],
            'name' => ['required', 'string', 'max:120'],
            'direction_mode' => ['required', Rule::enum(DeviceDirectionMode::class)],
            // Optional per-model ceiling (from the datasheet) — never assumed.
            'face_capacity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ] + $this->connectionRules());

        $device = PresenceDevice::create($this->withConnectionDefaults($data) + ['is_active' => true]);
        $this->logger->log('presence.device.add', "Added gate device {$device->name}", $device);

        return back()->with('success', "Device “{$device->name}” added. Enroll people, then run a sync.");
    }

    public function update(Request $request, PresenceDevice $device): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'direction_mode' => ['required', Rule::enum(DeviceDirectionMode::class)],
            'face_capacity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
        ] + $this->connectionRules());

        $data = $this->withConnectionDefaults($data);

        // A blank password means "keep the stored one" — the current password is
        // never sent to the browser, so an empty field is absence of intent to
        // change it, not an instruction to clear it.
        if (($data['password'] ?? null) === null || $data['password'] === '') {
            unset($data['password']);
        }

        $device->update($data + ['is_active' => $request->boolean('is_active')]);
        $this->logger->log('presence.device.update', "Updated gate device {$device->name}", $device);

        return back()->with('success', 'Device updated.');
    }

    public function destroy(PresenceDevice $device): RedirectResponse
    {
        $device->delete();
        $this->logger->log('presence.device.remove', "Removed gate device {$device->name}", $device);

        return back()->with('success', "Device “{$device->name}” removed. Its punch history is kept.");
    }

    public function syncTime(PresenceDevice $device): RedirectResponse
    {
        $result = $this->adapter->syncTime([$device->serial_number]);

        return back()->with(
            $result->success ? 'success' : 'error',
            $result->success ? "Clock sync sent to {$device->name}." : ($result->message ?? 'Could not reach the device.')
        );
    }

    public function pullLogs(PresenceDevice $device): RedirectResponse
    {
        $result = $this->adapter->pullLogs([$device->serial_number], now()->subDay(), now());

        return back()->with(
            $result->success ? 'success' : 'error',
            $result->success ? "Log pull requested from {$device->name}. New punches appear on the next sync." : ($result->message ?? 'Could not reach the device.')
        );
    }

    /**
     * Connection fields (S1) — how the Connector reaches this unit (01 §3.1).
     * All optional: a device can be registered before the Connector exists, and
     * an auto-registering unit tells us its address when it dials in.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function connectionRules(): array
    {
        return [
            'connection_mode' => ['nullable', Rule::enum(DeviceConnectionMode::class)],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:64'],
            'password' => ['nullable', 'string', 'max:128'],
        ];
    }

    /**
     * Normalise the connection block: default the port to the device family's
     * standard rather than storing null, and drop an address for a unit that
     * dials us (where it would be stale fiction).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withConnectionDefaults(array $data): array
    {
        if (array_key_exists('port', $data) && ! $data['port']) {
            $data['port'] = 37777;
        }

        $mode = $data['connection_mode'] ?? null;
        if ($mode === DeviceConnectionMode::AutoRegister->value) {
            $data['ip_address'] = null;
        }

        return $data;
    }

    /** Discover serials to help the Add-device form (no blind typing). */
    public function discover(): JsonResponse
    {
        $known = PresenceDevice::query()->pluck('serial_number')->all();

        $found = $this->adapter->getDevices()
            ->reject(fn ($d) => in_array($d->serial, $known, true))
            ->map(fn ($d) => ['serial' => $d->serial, 'name' => $d->name, 'status' => $d->status->value])
            ->values();

        return response()->json(['devices' => $found]);
    }
}
