<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffSalaryPayment;
use App\Services\ActivityLogger;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function index(): JsonResponse
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $staff = Staff::orderByDesc('is_active')->orderBy('name')->get()->map(function ($s) use ($monthStart, $monthEnd) {
            $present = StaffAttendance::where('staff_id', $s->id)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->whereIn('status', ['present', 'half_day'])->count();
            $paid = (float) StaffSalaryPayment::where('staff_id', $s->id)
                ->whereBetween('salary_month', [$monthStart, $monthEnd])->sum('amount');

            return [
                'id' => $s->id,
                'name' => $s->name,
                'designation' => $s->designation,
                'mobile' => $s->mobile,
                'monthly_salary' => (float) $s->monthly_salary,
                'is_active' => (bool) $s->is_active,
                'present_this_month' => $present,
                'salary_paid_this_month' => $paid,
            ];
        });

        return response()->json([
            'staff' => $staff,
            'summary' => [
                'total' => $staff->count(),
                'active' => $staff->where('is_active', true)->count(),
                'monthly_payroll' => (float) Staff::active()->sum('monthly_salary'),
            ],
            'attendance_statuses' => ['present' => 'Present', 'absent' => 'Absent', 'half_day' => 'Half day', 'leave' => 'Leave'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateStaff($request);
        $staff = Staff::create($data);
        $this->logger->log('staff.create', "Added staff {$staff->name}", $staff);

        return response()->json(['message' => 'Staff added.', 'id' => $staff->id], 201);
    }

    public function update(Request $request, int $staff): JsonResponse
    {
        $model = Staff::findOrFail($staff);
        $model->update($this->validateStaff($request));
        $this->logger->log('staff.update', "Updated staff {$model->name}", $model);

        return response()->json(['message' => 'Staff updated.']);
    }

    public function destroy(int $staff): JsonResponse
    {
        $model = Staff::findOrFail($staff);
        $this->logger->log('staff.delete', "Deleted staff {$model->name}", $model);
        $model->delete();

        return response()->json(['message' => 'Staff removed.']);
    }

    public function show(int $staff): JsonResponse
    {
        $model = Staff::findOrFail($staff);
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $attendance = StaffAttendance::where('staff_id', $model->id)
            ->whereBetween('date', [$monthStart, $monthEnd])->orderBy('date')->get();
        $counts = ['present' => 0, 'absent' => 0, 'half_day' => 0, 'leave' => 0];
        foreach ($attendance as $a) {
            $counts[$a->status] = ($counts[$a->status] ?? 0) + 1;
        }

        return response()->json([
            'staff' => [
                'id' => $model->id, 'name' => $model->name, 'designation' => $model->designation,
                'mobile' => $model->mobile, 'monthly_salary' => (float) $model->monthly_salary,
                'join_date' => $model->join_date?->toDateString(), 'address' => $model->address,
                'is_active' => (bool) $model->is_active, 'notes' => $model->notes,
            ],
            'attendance_summary' => $counts,
            'attendance' => $attendance->map(fn ($a) => ['date' => $a->date->toDateString(), 'status' => $a->status]),
            'salary_payments' => StaffSalaryPayment::where('staff_id', $model->id)->orderByDesc('paid_on')->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'salary_month' => $p->salary_month?->format('M Y'),
                    'amount' => (float) $p->amount,
                    'paid_on' => $p->paid_on?->toDateString(),
                    'mode' => $p->mode,
                ]),
        ]);
    }

    /** Daily attendance sheet — all active staff with their status for a date. */
    public function attendanceSheet(Request $request): JsonResponse
    {
        $date = $request->filled('date') ? Carbon::parse($request->date('date'))->toDateString() : now()->toDateString();
        $marks = StaffAttendance::whereDate('date', $date)->get()->keyBy('staff_id');

        $rows = Staff::active()->orderBy('name')->get()->map(fn ($s) => [
            'staff_id' => $s->id,
            'name' => $s->name,
            'designation' => $s->designation,
            'status' => $marks[$s->id]->status ?? 'present',
            'marked' => $marks->has($s->id),
        ]);

        return response()->json(['date' => $date, 'rows' => $rows]);
    }

    /** Bulk save attendance for a date. */
    public function saveAttendance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'marks' => ['required', 'array'],
            'marks.*.staff_id' => ['required', Rule::exists('staff', 'id')->where('hostel_id', Tenant::id())],
            'marks.*.status' => ['required', Rule::in(['present', 'absent', 'half_day', 'leave'])],
        ]);

        $date = Carbon::parse($data['date'])->toDateString();
        foreach ($data['marks'] as $m) {
            StaffAttendance::updateOrCreate(
                ['staff_id' => $m['staff_id'], 'date' => $date],
                ['hostel_id' => Tenant::id(), 'status' => $m['status']],
            );
        }
        $this->logger->log('staff.attendance', 'Marked attendance for '.$date);

        return response()->json(['message' => 'Attendance saved.', 'count' => count($data['marks'])]);
    }

    public function paySalary(Request $request, int $staff): JsonResponse
    {
        $model = Staff::findOrFail($staff);
        $data = $request->validate([
            'salary_month' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'mode' => ['required', 'string', 'max:40'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $pay = StaffSalaryPayment::create([
            'hostel_id' => Tenant::id(),
            'staff_id' => $model->id,
            'salary_month' => Carbon::parse($data['salary_month'].'-01')->startOfMonth(),
            'amount' => $data['amount'],
            'paid_on' => $data['paid_on'],
            'mode' => $data['mode'],
            'reference_number' => $data['reference_number'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        $this->logger->log('staff.salary', "Paid salary to {$model->name}", $pay);

        return response()->json(['message' => 'Salary recorded.', 'id' => $pay->id], 201);
    }

    public function deleteSalary(int $staff, int $payment): JsonResponse
    {
        $pay = StaffSalaryPayment::where('staff_id', $staff)->findOrFail($payment);
        $pay->delete();

        return response()->json(['message' => 'Salary entry removed.']);
    }

    protected function validateStaff(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'designation' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', 'digits:10'],
            'monthly_salary' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'join_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
