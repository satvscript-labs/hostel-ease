<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\Floor;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BedController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    /**
     * Visual floor-wise / room-wise bed layout.
     */
    public function layout(Request $request): View
    {
        $floorQuery = Floor::ordered()
            ->with([
                'rooms' => fn ($q) => $q->orderBy('room_number')->with([
                    'beds' => fn ($b) => $b->orderByRaw('CAST(SUBSTRING(bed_number, 2) AS UNSIGNED)')
                        ->with('activeAssignment.student'),
                ]),
            ]);

        if ($request->filled('floor')) {
            $floorQuery->where('id', $request->integer('floor'));
        }

        $floors = $floorQuery->get();
        $allFloors = Floor::ordered()->get(['id', 'name']);

        $totalBeds = Bed::count();
        $occupied = Bed::where('status', 'occupied')->count();

        $summary = [
            'total' => $totalBeds,
            'occupied' => $occupied,
            'empty' => Bed::where('status', 'empty')->count(),
            'reserved' => Bed::where('status', 'reserved')->count(),
            'maintenance' => Bed::where('status', 'maintenance')->count(),
            'occupancy_pct' => $totalBeds > 0 ? round(($occupied / $totalBeds) * 100, 1) : 0,
        ];

        return view('admin.beds.layout', compact('floors', 'allFloors', 'summary'));
    }

    /**
     * Full occupancy history for a single bed — who stayed, when, for how long,
     * and how much they paid during that stay.
     */
    public function history(Bed $bed): View
    {
        $bed->load('room.floor');

        $assignments = $bed->assignments()
            ->with('student')
            ->orderByDesc('join_date')
            ->get()
            ->map(function ($assignment) {
                // Payments made by this student within the occupancy window.
                $end = $assignment->leave_date ?? now();
                $assignment->window_paid = (float) $assignment->student?->payments()
                    ->whereBetween('paid_on', [$assignment->join_date, $end])
                    ->sum('amount');

                return $assignment;
            });

        return view('admin.beds.history', compact('bed', 'assignments'));
    }

    /**
     * Manually change a bed's status between empty / reserved / maintenance.
     * Occupied state is driven by the Bed Assignment module, not here.
     */
    public function updateStatus(Request $request, Bed $bed): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['empty', 'reserved', 'maintenance'])],
        ]);

        if ($bed->status === 'occupied') {
            return back()->with('error', 'This bed is occupied — release the student from Bed Assignment to change it.');
        }

        $bed->update($data);
        $this->logger->log('bed.status', "Bed {$bed->bed_number} → {$data['status']}", $bed);

        return back()->with('success', "Bed {$bed->bed_number} marked as {$data['status']}.");
    }
}
