<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\BedAssignment;
use App\Models\Floor;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Services\BedAssignmentService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssignmentController extends Controller
{
    public function __construct(
        protected BedAssignmentService $service,
        protected ActivityLogger $logger,
    ) {
    }

    public function index(): JsonResponse
    {
        $assignments = BedAssignment::with(['student', 'bed.room.floor'])
            ->where('is_active', true)
            ->orderByDesc('join_date')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'student' => $a->student?->name,
                'student_id' => $a->student_id,
                'room' => $a->bed?->room?->room_number,
                'bed' => $a->bed?->bed_number,
                'floor' => $a->bed?->room?->floor?->name,
                'join_date' => $a->join_date?->toDateString(),
                'fee_amount' => (float) $a->fee_amount,
                'fee_frequency' => $a->fee_frequency,
            ]);

        return response()->json(['assignments' => $assignments]);
    }

    /**
     * Options for the assign form: unassigned active students + available beds grouped by floor/room.
     */
    public function options(): JsonResponse
    {
        $students = Student::active()
            ->whereDoesntHave('activeAssignment')
            ->orderBy('name')
            ->get(['id', 'name', 'mobile', 'occupation_type']);

        $floors = Floor::ordered()
            ->with(['rooms' => fn ($q) => $q->orderBy('room_number')
                ->with(['beds' => fn ($b) => $b->whereIn('status', ['empty', 'reserved'])
                    ->orderByRaw('CAST(SUBSTRING(bed_number, 2) AS UNSIGNED)')])])
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'rooms' => $f->rooms->map(fn ($r) => [
                    'id' => $r->id,
                    'room_number' => $r->room_number,
                    'beds' => $r->beds->map(fn ($b) => ['id' => $b->id, 'bed_number' => $b->bed_number, 'status' => $b->status]),
                ])->filter(fn ($r) => count($r['beds']) > 0)->values(),
            ])->filter(fn ($f) => count($f['rooms']) > 0)->values();

        return response()->json([
            'students' => $students,
            'floors' => $floors,
            'fee_frequencies' => config('hostelease.fee_frequencies'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', Rule::exists('students', 'id')->where('hostel_id', Tenant::id())],
            'bed_id' => ['required', Rule::exists('beds', 'id')->where('hostel_id', Tenant::id())],
            'join_date' => ['required', 'date'],
            'fee_amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'fee_frequency' => ['required', Rule::in(array_keys(config('hostelease.fee_frequencies')))],
            'semester' => ['nullable', 'integer', Rule::in(config('hostelease.semesters'))],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $bed = Bed::with('room')->findOrFail($data['bed_id']);
        $assignment = $this->service->assign($student, $bed, $data);
        $this->logger->log('assignment.create', "Assigned {$student->name} to {$bed->bed_number}", $assignment);

        return response()->json(['message' => "{$student->name} assigned to bed {$bed->bed_number}.", 'id' => $assignment->id], 201);
    }

    /**
     * Release EVERY active bed assignment (frees all beds) without deleting or
     * marking students as left. Used by "Clear hostel".
     */
    public function clearAll(): JsonResponse
    {
        $assignments = BedAssignment::where('is_active', true)->get();
        $count = 0;
        foreach ($assignments as $a) {
            $this->service->release($a, null, false); // keep student active
            $count++;
        }
        $this->logger->log('assignment.clear_all', "Released all beds ({$count})");

        return response()->json(['message' => "Released {$count} bed(s). Students kept.", 'released' => $count]);
    }

    public function updateFee(Request $request, int $assignment): JsonResponse
    {
        $model = BedAssignment::with('student')->findOrFail($assignment);
        $data = $request->validate([
            'fee_amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'fee_frequency' => ['required', Rule::in(array_keys(config('hostelease.fee_frequencies')))],
            'semester' => ['nullable', 'integer', Rule::in(config('hostelease.semesters'))],
        ]);

        $this->service->updateFee($model, $data);
        $this->logger->log('assignment.fee_update',
            "Updated fee for {$model->student?->name} to {$data['fee_amount']} ({$data['fee_frequency']})", $model);

        return response()->json(['message' => 'Fee updated.']);
    }

    public function release(Request $request, int $assignment): JsonResponse
    {
        $model = BedAssignment::findOrFail($assignment);
        $data = $request->validate([
            'leave_date' => ['nullable', 'date', 'after_or_equal:'.$model->join_date->toDateString()],
            'mark_student_left' => ['nullable', 'boolean'],
        ]);

        $this->service->release($model, $data['leave_date'] ?? null, $request->boolean('mark_student_left'));
        $this->logger->log('assignment.release', "Released {$model->student?->name} from bed", $model);

        return response()->json(['message' => 'Student released — bed is now empty.']);
    }

    public function transfer(Request $request, int $assignment): JsonResponse
    {
        $model = BedAssignment::findOrFail($assignment);
        $data = $request->validate([
            'bed_id' => ['required', Rule::exists('beds', 'id')->where('hostel_id', Tenant::id())],
            'join_date' => ['required', 'date'],
        ]);

        $target = Bed::with('room')->findOrFail($data['bed_id']);
        $this->service->transfer($model, $target, $data);
        $this->logger->log('assignment.transfer', "Transferred {$model->student?->name} to {$target->bed_number}", $target);

        return response()->json(['message' => 'Student transferred.']);
    }
}

