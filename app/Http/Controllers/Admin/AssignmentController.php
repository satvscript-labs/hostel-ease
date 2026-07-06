<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssignmentRequest;
use App\Models\Bed;
use App\Models\BedAssignment;
use App\Models\Floor;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Services\BedAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function __construct(
        protected BedAssignmentService $service,
        protected ActivityLogger $logger,
    ) {
    }

    public function index(): View
    {
        $assignments = BedAssignment::with(['student', 'bed.room.floor'])
            ->where('is_active', true)
            ->orderByDesc('join_date')
            ->get();

        return view('admin.assignments.index', compact('assignments'));
    }

    public function create(Request $request): View
    {
        // Students who are active and not currently holding a bed.
        $students = Student::active()
            ->whereDoesntHave('activeAssignment')
            ->orderBy('name')
            ->get(['id', 'name', 'mobile', 'occupation_type']);

        // Available beds grouped by floor → room for an optgroup picker.
        $floors = Floor::ordered()
            ->with(['rooms' => fn ($q) => $q->orderBy('room_number')
                ->with(['beds' => fn ($b) => $b->whereIn('status', ['empty', 'reserved'])
                    ->orderByRaw('CAST(SUBSTRING(bed_number, 2) AS UNSIGNED)')])])
            ->get();

        $selectedBed = $request->filled('bed')
            ? Bed::with('room')->find($request->integer('bed'))
            : null;

        return view('admin.assignments.create', compact('students', 'floors', 'selectedBed'));
    }

    public function store(StoreAssignmentRequest $request): RedirectResponse
    {
        $student = Student::findOrFail($request->integer('student_id'));
        $bed = Bed::with('room')->findOrFail($request->integer('bed_id'));

        $assignment = $this->service->assign($student, $bed, $request->validated());

        $this->logger->log('assignment.create',
            "Assigned {$student->name} to {$bed->room->room_number}/{$bed->bed_number}", $assignment);

        return redirect()->route('admin.assignments.index')
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

        return back()->with('success', 'Student released — bed is now empty.');
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

        return redirect()->route('admin.assignments.index')->with('success', 'Student transferred.');
    }
}

