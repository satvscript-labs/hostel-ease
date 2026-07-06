<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\BedAssignment;
use App\Models\Floor;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Services\BedAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function __construct(
        protected BedAssignmentService $service,
        protected ActivityLogger $logger,
    ) {
    }

    /**
     * Unified Property Board serving the liquid UI
     */
    public function index(Request $request): View
    {
        // 1. Eager load the entire property hierarchy for the visual board
        $floors = Floor::with(['rooms' => function($q) {
            $q->orderBy('room_number');
        }, 'rooms.beds' => function($q) {
            $q->orderBy('bed_number');
        }, 'rooms.beds.activeAssignment.student'])->ordered()->get();

        // Also fetch floors again just for the transfer dropdown, grouped correctly
        $allFloors = Floor::ordered()
            ->with(['rooms' => fn ($q) => $q->orderBy('room_number')
                ->with(['beds' => fn ($b) => $b->whereIn('status', ['empty', 'available'])
                    ->orderByRaw('CAST(SUBSTRING(bed_number, 2) AS UNSIGNED)')])])
            ->get();

        // 2. Global stats for the Bento dashboard header
        $totalBeds = Bed::count();
        $occupied = Bed::where('status', 'occupied')->count();
        $vacant = Bed::whereIn('status', ['empty', 'available'])->count();
        $maintenance = Bed::where('status', 'maintenance')->count();

        // 3. Unassigned active students for the quick-assign modal
        $unassignedStudents = Student::where('status', 'active')
            ->whereDoesntHave('activeAssignment')
            ->orderBy('name')
            ->get();

        return view('admin.property.index', compact(
            'floors',
            'allFloors',
            'totalBeds',
            'occupied',
            'vacant',
            'maintenance',
            'unassignedStudents'
        ));
    }

    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'bed_id' => ['required', 'integer', 'exists:beds,id'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $bed = Bed::with('room')->findOrFail($data['bed_id']);

        $assignment = $this->service->assign($student, $bed, $data);

        $this->logger->log('assignment.create',
            "Assigned {$student->name} to {$bed->room->room_number}/{$bed->bed_number}", $assignment);

        return redirect()->route('admin.property.index')
            ->with('success', "{$student->name} assigned to bed {$bed->bed_number}.");
    }

    public function release(Request $request, BedAssignment $assignment): RedirectResponse
    {
        $data = $request->validate([
            'leave_date' => ['nullable', 'date', 'after_or_equal:'.$assignment->join_date->toDateString()],
            'mark_student_left' => ['nullable', 'boolean'],
        ]);

        $this->service->release(
            $assignment,
            $data['leave_date'] ?? null,
            $request->boolean('mark_student_left'),
        );

        $this->logger->log('assignment.release',
            "Released {$assignment->student->name} from bed", $assignment);

        return back()->with('success', 'Student released from bed.');
    }

    public function transfer(Request $request, BedAssignment $assignment): RedirectResponse
    {
        $data = $request->validate([
            'bed_id' => ['required', 'integer'],
            'join_date' => ['required', 'date'],
        ]);

        $target = Bed::with('room')->findOrFail($data['bed_id']);
        $this->service->transfer($assignment, $target, $data);

        $this->logger->log('assignment.transfer',
            "Transferred {$assignment->student->name} to {$target->room->room_number}/{$target->bed_number}", $target);

        return redirect()->route('admin.property.index')->with('success', 'Student transferred successfully.');
    }
}
