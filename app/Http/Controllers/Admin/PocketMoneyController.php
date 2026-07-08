<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PocketMoneyTransaction;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PocketMoneyController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function index(): View
    {
        $balances = PocketMoneyTransaction::query()
            ->selectRaw("student_id, SUM(CASE WHEN type='deposit' THEN amount ELSE -amount END) as bal")
            ->groupBy('student_id')->pluck('bal', 'student_id');

        $students = Student::with('activeAssignment.bed.room')->active()->orderBy('name')->get()->map(function ($s) use ($balances) {
            $s->pocket_balance = round((float) ($balances[$s->id] ?? 0), 2);

            return $s;
        });

        $total = round((float) $balances->sum(), 2);

        return view('admin.pocket_money.index', compact('students', 'total'));
    }

    public function show(Student $student): View
    {
        $balance = PocketMoneyTransaction::balanceFor($student->id);
        $transactions = PocketMoneyTransaction::where('student_id', $student->id)->orderByDesc('created_at')->get();

        return view('admin.pocket_money.show', compact('student', 'balance', 'transactions'));
    }

    public function store(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['deposit', 'withdraw'])],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        // Lending is now allowed — negative balances are permitted.
        // The frontend shows a warning prompt before the user confirms.

        PocketMoneyTransaction::create($data + [
            'hostel_id' => Tenant::id(),
            'student_id' => $student->id,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', ucfirst($data['type']).' recorded.');
    }

    public function destroy(Student $student, PocketMoneyTransaction $transaction): RedirectResponse
    {
        abort_unless($transaction->student_id === $student->id, 404);
        $transaction->delete();

        return back()->with('success', 'Transaction removed.');
    }
}
