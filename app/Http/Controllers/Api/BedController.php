<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\Floor;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use DB;

class BedController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function layout(Request $request): JsonResponse
    {
        $floors = Floor::ordered()
            ->with(['rooms' => function($q) {
                $q->orderBy('room_number')
                  ->with(['beds' => function($b) {
                      $b->orderByRaw('CAST(SUBSTRING(bed_number, 2) AS UNSIGNED)')
                        ->with('activeAssignment.student');
                  }]);
            }])
            ->get();

        $result = [];
        foreach ($floors as $floor) {
            $floorData = [
                'id' => $floor->id,
                'name' => $floor->name,
                'rooms' => []
            ];

            foreach ($floor->rooms as $room) {
                $roomData = [
                    'id' => $room->id,
                    'room_number' => $room->room_number,
                    'room_type' => $room->room_type,
                    'beds' => []
                ];

                foreach ($room->beds as $bed) {
                    $student = $bed->activeAssignment?->student;
                    $hasPending = false;

                    if ($student && $student->id) {
                        $pending = DB::table('semester_fees')
                            ->where('student_id', $student->id)
                            ->where('status', '!=', 'paid')
                            ->count();
                        $hasPending = $pending > 0;
                    }

                    $bedData = [
                        'id' => $bed->id,
                        'bed_number' => $bed->bed_number,
                        'status' => $bed->status,
                        'color' => $bed->statusColor(),
                        'student' => $student?->name,
                        'student_id' => $student?->id,
                        'has_pending' => $hasPending,
                    ];

                    $roomData['beds'][] = $bedData;
                }

                $floorData['rooms'][] = $roomData;
            }

            $result[] = $floorData;
        }

        if ($request->filled('floor')) {
            $result = array_filter($result, fn($f) => $f['id'] == $request->integer('floor'));
        }

        $totalBeds = Bed::count();
        $occupied = Bed::where('status', 'occupied')->count();

        return response()->json([
            'floors' => array_values($result),
            'all_floors' => Floor::ordered()->get(['id', 'name']),
            'summary' => [
                'total' => $totalBeds,
                'occupied' => $occupied,
                'empty' => Bed::where('status', 'empty')->count(),
                'reserved' => Bed::where('status', 'reserved')->count(),
                'maintenance' => Bed::where('status', 'maintenance')->count(),
                'occupancy_pct' => $totalBeds > 0 ? round(($occupied / $totalBeds) * 100, 1) : 0,
            ],
            'statuses' => config('hostelease.bed_statuses'),
        ]);
    }

    public function history(int $bed): JsonResponse
    {
        $model = Bed::with('room.floor')->findOrFail($bed);

        $assignments = $model->assignments()->with('student')->orderByDesc('join_date')->get()
            ->map(function ($a) {
                $end = $a->leave_date ?? now();

                return [
                    'student' => $a->student?->name,
                    'student_id' => $a->student_id,
                    'join_date' => $a->join_date?->toDateString(),
                    'leave_date' => $a->leave_date?->toDateString(),
                    'is_active' => (bool) $a->is_active,
                    'paid_in_window' => (float) ($a->student?->payments()
                        ->whereBetween('paid_on', [$a->join_date, $end])->sum('amount') ?? 0),
                ];
            });

        return response()->json([
            'bed' => [
                'id' => $model->id,
                'bed_number' => $model->bed_number,
                'status' => $model->status,
                'room' => $model->room?->room_number,
                'floor' => $model->room?->floor?->name,
            ],
            'assignments' => $assignments,
        ]);
    }

    public function updateStatus(Request $request, int $bed): JsonResponse
    {
        $model = Bed::findOrFail($bed);
        $data = $request->validate([
            'status' => ['required', Rule::in(['empty', 'reserved', 'maintenance'])],
        ]);

        if ($model->status === 'occupied') {
            return response()->json(['message' => 'This bed is occupied — release the student from Bed Assignment first.'], 422);
        }

        $model->update($data);
        $this->logger->log('bed.status', "Bed {$model->bed_number} → {$data['status']}", $model);

        return response()->json(['message' => "Bed {$model->bed_number} marked as {$data['status']}."]);
    }
}

