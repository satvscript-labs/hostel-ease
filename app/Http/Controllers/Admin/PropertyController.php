<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\BedAssignment;
use App\Models\Floor;
use App\Models\Room;
use App\Models\Student;
use App\Services\AcMeterService;
use App\Services\ActivityLogger;
use App\Services\BedAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function __construct(
        protected BedAssignmentService $service,
        protected ActivityLogger $logger,
        protected AcMeterService $meter,
    ) {
    }

    /**
     * Unified Property Board serving the liquid UI
     */
    public function index(Request $request): View
    {
        // 1. Eager load the entire property hierarchy for the visual board.
        //    Beds sort by their numeric part (B1..B10..Bn), not as text —
        //    a plain orderBy('bed_number') would put B10 right after B1.
        $floors = Floor::with(['rooms' => function($q) {
            $q->orderBy('room_number');
        }, 'rooms.beds' => function($q) {
            $q->orderByRaw('CAST(SUBSTRING(bed_number, 2) AS UNSIGNED)');
        }, 'rooms.beds.activeAssignment.student'])->ordered()->get();

        // Vacant beds for the transfer sheet's picker (W6.4). A flat payload
        // rather than a nested <select>: the picker shows what the move
        // actually depends on — room, floor, AC, rent (which prices the new
        // plan) and how full the room is.
        // Every AC room's last recorded meter (derived — bills + moves), so
        // the meter inputs can hint the floor and warn inline before a typo
        // ever reaches the server (meter-floor, 2026-07-18).
        $roomFloors = $this->meter->lastReadingsForRooms(
            Room::where('room_type', 'ac')->pluck('id')->all()
        );

        $vacantBeds = Bed::with('room.floor')
            ->whereIn('status', ['empty', 'available'])
            ->get()
            ->sortBy(fn ($b) => [$b->room?->floor?->sort_order ?? 0, $b->room?->room_number, $b->bed_number])
            ->map(fn (Bed $b) => [
                'id' => $b->id,
                'bed' => (string) $b->bed_number,
                'room' => (string) $b->room?->room_number,
                'floor' => $b->room?->floor?->name,
                'is_ac' => (bool) $b->room?->isAc(),
                'last_reading' => $roomFloors[$b->room?->id] ?? null,
                'rent' => (float) ($b->room?->rent ?? 0),
                'sharing' => (int) ($b->room?->sharing ?? 0),
            ])->values();

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
            'vacantBeds',
            'totalBeds',
            'occupied',
            'vacant',
            'maintenance',
            'unassignedStudents',
            'roomFloors'
        ));
    }

    /**
     * Assign a student to a bed — plan and all, in one atomic request (W6.4).
     *
     * The old flow PUT the fee plan to a separate endpoint via fetch, then
     * submitted this form: two round-trips, and a plan could save while the
     * assignment failed. The plan is now part of the move itself.
     */
    public function assign(Request $request, \App\Services\ProrationService $proration): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'bed_id' => ['required', 'integer', 'exists:beds,id'],
            'join_date' => ['nullable', 'date'],
            'meter_reading' => ['nullable', 'numeric', 'min:0'],
            'meter_reset' => ['nullable', 'boolean'],
            // Every move is a re-pricing — each room has its own cost, so the
            // plan is confirmed on the way in, never assumed (owner, W6.4).
            'fee_frequency' => ['required', 'string', Rule::in(array_keys(config('hostelease.fee_frequencies')))],
            'fee_amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $bed = Bed::with('room')->findOrFail($data['bed_id']);

        // W6.3 (owner decision: REQUIRED for AC rooms): the meter at move-in
        // is the anchor that makes every future AC bill split exact instead
        // of day-estimated. Checked here, not in the rules array — which room
        // the bed belongs to isn't known until the bed is loaded.
        $this->validateMeterReading($bed->room, $data['meter_reading'] ?? null, 'meter_reading',
            $request->boolean('meter_reset'), 'assign');

        $assignment = $this->service->assign($student, $bed, $data);

        // Their first bill, exactly as the profile's plan editor would raise
        // it (shared implementation). No-ops when they already have invoices.
        $proration->generateInitialInvoice($student->refresh());

        $this->logger->log('assignment.create',
            "Assigned {$student->name} to {$bed->room->room_number}/{$bed->bed_number} at "
            .hostelease_money($data['fee_amount'])." ({$data['fee_frequency']})", $assignment);

        return $this->afterMove($request, $student)
            ->with('success', "{$student->name} assigned to bed {$bed->bed_number}.");
    }

    public function release(Request $request, BedAssignment $assignment): RedirectResponse
    {
        $data = $request->validate([
            'leave_date' => ['nullable', 'date', 'after_or_equal:'.$assignment->join_date->toDateString()],
            'mark_student_left' => ['nullable', 'boolean'],
            'meter_reading' => ['nullable', 'numeric', 'min:0'],
            'meter_reset' => ['nullable', 'boolean'],
        ]);

        $assignment->loadMissing('bed.room');
        // The floor already includes this student's own join reading, so a
        // leaver can never record negative consumption either.
        $this->validateMeterReading($assignment->bed->room, $data['meter_reading'] ?? null, 'meter_reading',
            $request->boolean('meter_reset'), 'release');

        $this->service->release(
            $assignment,
            $data['leave_date'] ?? null,
            $request->boolean('mark_student_left'),
            isset($data['meter_reading']) ? (float) $data['meter_reading'] : null,
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
            'meter_reading' => ['nullable', 'numeric', 'min:0'],       // the room being ENTERED
            'old_meter_reading' => ['nullable', 'numeric', 'min:0'],   // the room being LEFT
            'meter_reset' => ['nullable', 'boolean'],                  // reset declared on the NEW room's meter
            'old_meter_reset' => ['nullable', 'boolean'],              // reset declared on the OLD room's meter
            // A transfer is a re-pricing: the new room has its own cost, and
            // billing must not keep charging the old room's rate (owner, W6.4
            // — this silence is what let a student be billed for a room they
            // no longer occupied).
            'fee_frequency' => ['required', 'string', Rule::in(array_keys(config('hostelease.fee_frequencies')))],
            'fee_amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $target = Bed::with('room')->findOrFail($data['bed_id']);
        $assignment->loadMissing('bed.room', 'student');

        // A transfer can touch TWO meters (W6.3): the old room's reading caps
        // what the student bears there; the new room's reading starts them.
        // Each meter carries its own floor — and its own reset declaration.
        $this->validateMeterReading($assignment->bed->room, $data['old_meter_reading'] ?? null, 'old_meter_reading',
            $request->boolean('old_meter_reset'), 'transfer (room left)');
        $this->validateMeterReading($target->room, $data['meter_reading'] ?? null, 'meter_reading',
            $request->boolean('meter_reset'), 'transfer (room entered)');

        // Plan-forward-only (owner decision, W6.4): the new rate applies from
        // the next billing cycle. No proration, no credit, no new invoice —
        // the current invoice stands as issued. The move sheet says so before
        // you confirm, so the money behaviour is never a surprise.
        $this->service->transfer($assignment, $target, $data);

        $this->logger->log('assignment.transfer',
            "Transferred {$assignment->student->name} to {$target->room->room_number}/{$target->bed_number} at "
            .hostelease_money($data['fee_amount'])." ({$data['fee_frequency']})", $target);

        return $this->afterMove($request, $assignment->student)
            ->with('success', 'Student transferred successfully.');
    }

    /**
     * Where a move returns to. The Property Board is the default, but the
     * student profile now drives assign/transfer inline (W10 UX fix) and passes
     * redirect_to=profile so the operator stays where they started. Whitelisted
     * — never redirect to an arbitrary caller-supplied URL.
     */
    protected function afterMove(Request $request, Student $student): RedirectResponse
    {
        if ($request->input('redirect_to') === 'profile') {
            return redirect()->route('admin.students.show', $student);
        }

        return redirect()->route('admin.property.index');
    }

    /**
     * W6.3, owner decision: an occupancy change in an AC room MUST record
     * the meter — that reading is what keeps the AC bill split honest.
     */
    /**
     * The AC meter gate for every move (W6.3 presence + meter-floor 2026-07-18):
     * an AC room requires a reading, and that reading can't be below the
     * room's last recorded meter — a meter only counts up. A genuine meter
     * reset/replacement passes with $meterReset (confirmed in the UI only
     * after a warning) and is logged by the service.
     */
    protected function validateMeterReading(?Room $room, $reading, string $field, bool $meterReset = false, string $context = 'move'): void
    {
        if ($room?->room_type !== 'ac') {
            return;
        }

        if ($reading === null || $reading === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => __('Room :room is an AC room — enter its current meter reading for this move (it keeps the AC bill split exact).', ['room' => $room->room_number]),
            ]);
        }

        $this->meter->assertNotBelow($room, (float) $reading, $field, $meterReset, context: $context);
    }
}
