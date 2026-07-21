<?php

namespace App\Http\Controllers\Admin\Presence;

use App\Enums\Presence\PresenceState;
use App\Enums\Presence\PunchSource;
use App\Http\Controllers\Controller;
use App\Models\PresenceDevice;
use App\Models\PresencePunch;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Presence P4 — the Gate Log: the raw in/out register, every punch, both
 * audiences, chronological. Also the quarantine surface (unmatched IDs) and the
 * CSV export (02 §4).
 */
class GateLogController extends Controller
{
    public function index(Request $request)
    {
        $punches = $this->filtered($request)->paginate(40)->withQueryString();

        return view('admin.presence.gate_log', [
            'punches' => $punches,
            'devices' => PresenceDevice::orderBy('name')->get(['id', 'public_id', 'name']),
            'unmatchedCount' => PresencePunch::unmatched()->count(),
            'filters' => $this->currentFilters($request),
            'matchPeople' => $this->matchPeople($request),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'gate-log-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Time', 'Person', 'Type', 'Device ID', 'Direction', 'Verify', 'Device', 'Source']);

            $this->filtered($request)->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $p) {
                    $person = $p->profile?->presenceable;
                    fputcsv($out, [
                        $p->punched_at->format('Y-m-d'),
                        $p->punched_at->format('H:i:s'),
                        $person?->name ?? '(unmatched)',
                        $person ? class_basename($p->profile->presenceable_type) : '',
                        $p->device_user_id,
                        ucfirst($p->direction->value),
                        $p->verify_mode ?? '',
                        $p->device?->name ?? '',
                        $p->source->value,
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** The shared filtered query (used by both the list and the export). */
    protected function filtered(Request $request)
    {
        $query = PresencePunch::query()
            ->with(['device:id,name', 'profile.presenceable'])
            ->orderByDesc('punched_at')->orderByDesc('id');

        // Date (default: today). A single day keeps the feed readable.
        $date = rescue(fn () => $request->filled('date') ? Carbon::parse($request->date('date')) : now(), now(), false);
        $query->whereBetween('punched_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);

        if ($request->boolean('unmatched')) {
            $query->whereNull('presence_profile_id');
        }

        $type = $request->string('type')->toString();
        if (in_array($type, ['students', 'staff'], true)) {
            $modelClass = $type === 'staff' ? Staff::class : Student::class;
            $query->whereHas('profile', fn ($q) => $q->where('presenceable_type', $modelClass));
        }

        if (in_array($dir = $request->string('direction')->toString(), ['in', 'out'], true)) {
            $query->where('direction', $dir);
        }

        if ($deviceId = $request->integer('device')) {
            $query->where('presence_device_id', $deviceId);
        }

        if ($search = trim($request->string('search')->toString())) {
            $query->where(function ($q) use ($search) {
                $q->where('device_user_id', 'like', "%{$search}%")
                    ->orWhereHas('profile', fn ($p) => $p->whereHasMorph('presenceable', [Student::class, Staff::class],
                        fn ($m) => $m->where('name', 'like', "%{$search}%")));
            });
        }

        return $query;
    }

    protected function currentFilters(Request $request): array
    {
        $date = rescue(fn () => $request->filled('date') ? Carbon::parse($request->date('date')) : now(), now(), false);

        return [
            'date' => $date->format('Y-m-d'),
            'type' => $request->string('type')->toString(),
            'direction' => $request->string('direction')->toString(),
            'device' => $request->integer('device'),
            'search' => trim($request->string('search')->toString()),
            'unmatched' => $request->boolean('unmatched'),
        ];
    }

    /** People options for the inline quarantine Match picker (only if needed). */
    protected function matchPeople(Request $request)
    {
        if (! $request->boolean('unmatched') && PresencePunch::unmatched()->count() === 0) {
            return collect();
        }

        return Student::query()->active()->orderBy('name')->get(['id', 'public_id', 'name'])
            ->map(fn ($s) => ['id' => 'student:'.$s->public_id, 'name' => $s->name, 'sub' => __('Student')])
            ->concat(Staff::query()->active()->orderBy('name')->get(['id', 'public_id', 'name', 'designation'])
                ->map(fn ($s) => ['id' => 'staff:'.$s->public_id, 'name' => $s->name, 'sub' => $s->designation ?: __('Staff')]))
            ->values();
    }
}
