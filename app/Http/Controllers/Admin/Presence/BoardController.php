<?php

namespace App\Http\Controllers\Admin\Presence;

use App\Enums\Presence\DeviceStatus;
use App\Enums\Presence\PresenceState;
use App\Http\Controllers\Controller;
use App\Models\Floor;
use App\Models\PresenceDevice;
use App\Models\PresenceProfile;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Presence P3 — the live boards. Students and Staff kept as separate pages
 * (owner's hard requirement); one shared engine underneath, the views never
 * mix. Filtering/paging fragment-swaps the list; a lightweight client poll
 * keeps it fresh to ~20s (02 §2/§3, 03 §1).
 */
class BoardController extends Controller
{
    public function students(Request $request): View
    {
        return view('admin.presence.students', $this->board($request, 'student'));
    }

    public function staff(Request $request): View
    {
        return view('admin.presence.staff', $this->board($request, 'staff'));
    }

    /** Shared board payload for a person type. */
    protected function board(Request $request, string $type): array
    {
        $modelClass = $type === 'staff' ? Staff::class : Student::class;
        $table = $type === 'staff' ? 'staff' : 'students';

        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());
        $sort = $request->string('sort')->toString() ?: 'longest';

        $query = PresenceProfile::query()
            ->where('presence_profiles.presenceable_type', $modelClass)
            ->enrolled()
            ->join($table, "{$table}.id", '=', 'presence_profiles.presenceable_id')
            ->whereNull("{$table}.deleted_at")
            ->with([
                'presenceable' => fn (MorphTo $m) => $m->morphWith(
                    $type === 'student' ? [Student::class => ['activeAssignment.bed.room.floor']] : [Staff::class => []]
                ),
                'lastPunch.device',
            ])
            ->select('presence_profiles.*');

        if ($search !== '') {
            $query->where("{$table}.name", 'like', "%{$search}%");
        }
        if (in_array($status, ['in', 'out', 'unknown'], true)) {
            $query->where('presence_profiles.state', $status);
        }

        if ($type === 'student') {
            if ($occ = $request->string('occupation')->toString()) {
                $query->where("{$table}.occupation_type", $occ);
            }
            if ($floor = $request->integer('floor')) {
                $query->whereExists(function ($q) use ($floor) {
                    $q->from('bed_assignments as ba')
                        ->join('beds', 'beds.id', '=', 'ba.bed_id')
                        ->join('rooms', 'rooms.id', '=', 'beds.room_id')
                        ->whereColumn('ba.student_id', 'students.id')
                        ->where('ba.is_active', true)
                        ->where('rooms.floor_id', $floor);
                });
            }
        } elseif ($desig = $request->string('designation')->toString()) {
            $query->where("{$table}.designation", $desig);
        }

        // Default: the person you're worried about is row one — Out first,
        // longest-out at the top (oldest state_changed_at).
        match ($sort) {
            'name' => $query->orderBy("{$table}.name"),
            'recent' => $query->orderByDesc('presence_profiles.state_changed_at'),
            default => $query
                ->orderByRaw("CASE presence_profiles.state WHEN 'out' THEN 0 WHEN 'unknown' THEN 1 ELSE 2 END")
                ->orderBy('presence_profiles.state_changed_at')
                ->orderBy("{$table}.name"),
        };

        $profiles = $query->paginate(20)->withQueryString();

        return [
            'type' => $type,
            'profiles' => $profiles,
            'stats' => $this->stats($modelClass, $table, $type),
            'freshness' => $this->freshness(),
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'floors' => $type === 'student' ? Floor::orderBy('name')->get(['id', 'name']) : collect(),
            'occupations' => $type === 'student' ? config('hostelease.occupation_types') : [],
            'designations' => $type === 'staff'
                ? Staff::active()->whereNotNull('designation')->distinct()->orderBy('designation')->pluck('designation')
                : collect(),
            'isFiltered' => $search !== '' || in_array($status, ['in', 'out', 'unknown'], true)
                || $request->filled('floor') || $request->filled('occupation') || $request->filled('designation'),
        ];
    }

    /** @return array{inside:int,out:int,unknown:int,stale:int,not_enrolled:int} */
    protected function stats(string $modelClass, string $table, string $type): array
    {
        $base = fn () => PresenceProfile::query()
            ->where('presence_profiles.presenceable_type', $modelClass)
            ->enrolled()
            ->join($table, "{$table}.id", '=', 'presence_profiles.presenceable_id')
            ->whereNull("{$table}.deleted_at");

        $byState = $base()->selectRaw('presence_profiles.state, COUNT(*) as c')
            ->groupBy('presence_profiles.state')->pluck('c', 'state');

        $notEnrolled = $type === 'staff'
            ? Staff::active()->whereDoesntHave('presenceProfile')->count()
            : Student::active()->whereDoesntHave('presenceProfile')->count();

        return [
            'inside' => (int) ($byState[PresenceState::In->value] ?? 0),
            'out' => (int) ($byState[PresenceState::Out->value] ?? 0),
            'unknown' => (int) ($byState[PresenceState::Unknown->value] ?? 0),
            'stale' => (int) $base()->where('presence_profiles.has_missed_punch', true)->count(),
            'not_enrolled' => $notEnrolled,
        ];
    }

    /** @return array{synced_at:?\Illuminate\Support\Carbon,online:int,devices:int,ok:bool} */
    protected function freshness(): array
    {
        $devices = PresenceDevice::query()->where('is_active', true)->get(['device_status', 'last_synced_at']);
        $syncedAt = $devices->max('last_synced_at');

        return [
            'synced_at' => $syncedAt,
            'online' => $devices->where('device_status', DeviceStatus::Online)->count(),
            'devices' => $devices->count(),
            // Fresh if a sync landed in the last 3 minutes (03 §1).
            'ok' => $syncedAt !== null && $syncedAt->gt(now()->subMinutes(3)),
        ];
    }

    /**
     * Evacuation muster (approved idea #2) — a print-ready roster of everyone
     * currently INSIDE (+ status-uncertain, called out separately). A frozen,
     * timestamped snapshot: the sheet a warden carries out in an emergency.
     */
    public function muster(Request $request): View
    {
        $scope = $request->string('type')->toString() ?: 'students';
        $scope = in_array($scope, ['students', 'staff', 'all'], true) ? $scope : 'students';

        $collect = function (string $modelClass, string $table) {
            return PresenceProfile::query()
                ->where('presence_profiles.presenceable_type', $modelClass)
                ->enrolled()
                ->whereIn('presence_profiles.state', [PresenceState::In->value, PresenceState::Unknown->value])
                ->join($table, "{$table}.id", '=', 'presence_profiles.presenceable_id')
                ->whereNull("{$table}.deleted_at")
                ->with(['presenceable' => fn (MorphTo $m) => $m->morphWith([
                    Student::class => ['activeAssignment.bed.room.floor'],
                    Staff::class => [],
                ])])
                ->select('presence_profiles.*')
                ->orderBy("{$table}.name")
                ->get();
        };

        $students = in_array($scope, ['students', 'all'], true) ? $collect(Student::class, 'students') : collect();
        $staff = in_array($scope, ['staff', 'all'], true) ? $collect(Staff::class, 'staff') : collect();

        return view('admin.presence.muster', [
            'scope' => $scope,
            'students' => $students,
            'staff' => $staff,
            'generatedAt' => now(),
            'branch' => \App\Models\Hostel::find(\App\Support\Tenant::id()),
        ]);
    }
}
