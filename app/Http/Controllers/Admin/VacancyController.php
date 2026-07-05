<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\BedAssignment;
use App\Models\Floor;
use App\Models\Room;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VacancyController extends Controller
{
    public function index(Request $request): View
    {
        $floorId = $request->integer('floor') ?: null;
        $roomId = $request->integer('room') ?: null;
        $sharing = $request->integer('sharing') ?: null;

        // Currently free beds (empty or reserved) with the chosen filters.
        $emptyBeds = Bed::with('room.floor')
            ->whereIn('status', ['empty', 'reserved'])
            ->when($roomId, fn ($q) => $q->where('room_id', $roomId))
            ->when($floorId || $sharing, fn ($q) => $q->whereHas('room', function ($r) use ($floorId, $sharing) {
                $r->when($floorId, fn ($x) => $x->where('floor_id', $floorId))
                  ->when($sharing, fn ($x) => $x->where('sharing', $sharing));
            }))
            ->get()
            ->sortBy([['room.floor.sort_order', 'asc'], ['room.room_number', 'asc'], ['bed_number', 'asc']]);

        // Upcoming vacancies: active occupants with a leave date in the next 30 days,
        // honouring the same floor/room/sharing filters via their bed's room.
        $upcoming = BedAssignment::with(['student', 'bed.room.floor'])
            ->where('is_active', true)
            ->whereHas('student', fn ($s) => $s->whereNotNull('leave_date')
                ->whereBetween('leave_date', [now()->startOfDay(), now()->addDays(30)->endOfDay()]))
            ->when($roomId, fn ($q) => $q->whereHas('bed', fn ($b) => $b->where('room_id', $roomId)))
            ->when($floorId || $sharing, fn ($q) => $q->whereHas('bed.room', function ($r) use ($floorId, $sharing) {
                $r->when($floorId, fn ($x) => $x->where('floor_id', $floorId))
                  ->when($sharing, fn ($x) => $x->where('sharing', $sharing));
            }))
            ->get()
            ->sortBy(fn ($a) => $a->student->leave_date);

        $windows = [
            7 => Student::leavingWithin(7)->count(),
            15 => Student::leavingWithin(15)->count(),
            30 => Student::leavingWithin(30)->count(),
        ];

        $floors = Floor::ordered()->get(['id', 'name']);
        $rooms = Room::orderBy('room_number')->get(['id', 'room_number', 'floor_id']);

        return view('admin.vacancy.index', compact(
            'emptyBeds', 'upcoming', 'windows', 'floors', 'rooms', 'floorId', 'roomId', 'sharing'
        ));
    }
}
