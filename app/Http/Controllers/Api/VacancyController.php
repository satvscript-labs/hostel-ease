<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\BedAssignment;
use App\Models\Floor;
use App\Models\Room;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VacancyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $floorId = $request->integer('floor') ?: null;
        $roomId = $request->integer('room') ?: null;
        $sharing = $request->integer('sharing') ?: null;

        $emptyBeds = Bed::with('room.floor')
            ->whereIn('status', ['empty', 'reserved'])
            ->when($roomId, fn ($q) => $q->where('room_id', $roomId))
            ->when($floorId || $sharing, fn ($q) => $q->whereHas('room', function ($r) use ($floorId, $sharing) {
                $r->when($floorId, fn ($x) => $x->where('floor_id', $floorId))
                  ->when($sharing, fn ($x) => $x->where('sharing', $sharing));
            }))
            ->get()
            ->sortBy([['room.floor.sort_order', 'asc'], ['room.room_number', 'asc'], ['bed_number', 'asc']])
            ->values()
            ->map(fn ($b) => [
                'bed_id' => $b->id,
                'bed_number' => $b->bed_number,
                'status' => $b->status,
                'room' => $b->room?->room_number,
                'floor' => $b->room?->floor?->name,
                'sharing' => $b->room?->sharing,
            ]);

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
            ->sortBy(fn ($a) => $a->student->leave_date)
            ->values()
            ->map(fn ($a) => [
                'student' => $a->student?->name,
                'leave_date' => $a->student?->leave_date?->toDateString(),
                'room' => $a->bed?->room?->room_number,
                'bed' => $a->bed?->bed_number,
            ]);

        return response()->json([
            'empty_beds' => $emptyBeds,
            'upcoming' => $upcoming,
            'windows' => [
                '7' => Student::leavingWithin(7)->count(),
                '15' => Student::leavingWithin(15)->count(),
                '30' => Student::leavingWithin(30)->count(),
            ],
            'floors' => Floor::ordered()->get(['id', 'name']),
            'rooms' => Room::orderBy('room_number')->get(['id', 'room_number', 'floor_id']),
        ]);
    }
}
