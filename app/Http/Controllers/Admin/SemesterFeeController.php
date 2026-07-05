<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CollectPaymentRequest;
use App\Models\SemesterFee;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SemesterFeeController extends Controller
{
    public function __construct(
        protected PaymentService $payments,
        protected ActivityLogger $logger,
    ) {
    }

    public function index(Request $request): View
    {
        $fees = SemesterFee::with('student')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('semester'), fn ($q) => $q->where('semester', $request->integer('semester')))
            ->orderByDesc('id')
            ->get();

        $summary = [
            'total' => (float) $fees->sum('total_fee'),
            'paid' => (float) $fees->sum('paid_amount'),
            'due' => (float) $fees->sum('balance'),
        ];

        // College students available for adding a fee record.
        $students = Student::active()->where('occupation_type', 'student')->orderBy('name')->get(['id', 'name', 'mobile']);

        return view('admin.semester_fees.index', compact('fees', 'summary', 'students'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', Rule::exists('students', 'id')->where('hostel_id', \App\Support\Tenant::id())],
            'semester' => ['required', 'integer', Rule::in(config('hostelease.semesters'))],
            'total_fee' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'due_date' => ['nullable', 'date'],
        ]);

        if (SemesterFee::where('student_id', $data['student_id'])->where('period_type', 'semester')->where('semester', $data['semester'])->exists()) {
            return back()->with('error', 'A fee record for this student & semester already exists.');
        }

        $fee = new SemesterFee($data);
        $fee->paid_amount = 0;
        $fee->recalculate();
        $fee->save();

        $this->logger->log('semester_fee.create', "Semester {$fee->semester} fee for student #{$fee->student_id}", $fee);

        return back()->with('success', 'Semester fee added.');
    }

    public function update(Request $request, SemesterFee $semester_fee): RedirectResponse
    {
        $data = $request->validate([
            'total_fee' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'due_date' => ['nullable', 'date'],
        ]);

        $semester_fee->fill($data);
        $semester_fee->recalculate();
        $semester_fee->save();

        return back()->with('success', 'Semester fee updated.');
    }

    public function collect(CollectPaymentRequest $request, SemesterFee $semester_fee): RedirectResponse
    {
        $payment = $this->payments->record(
            array_merge($request->validated(), ['student_id' => $semester_fee->student_id]),
            $semester_fee,
        );

        return redirect()->route('admin.payments.show', $payment)
            ->with('success', "Payment recorded against semester {$semester_fee->semester}.");
    }

    public function destroy(SemesterFee $semester_fee): RedirectResponse
    {
        if ((float) $semester_fee->paid_amount > 0) {
            return back()->with('error', 'This fee has payments against it — reverse the receipt(s) first.');
        }

        $semester_fee->delete();

        return back()->with('success', 'Semester fee removed.');
    }
}

