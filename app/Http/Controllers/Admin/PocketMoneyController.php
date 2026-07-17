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

    public function index(Request $request): View
    {
        $search = $request->input('search');
        $filter = $request->input('filter'); // '' | negative | departed

        $balances = PocketMoneyTransaction::query()
            ->selectRaw("student_id, SUM(CASE WHEN type='deposit' THEN amount ELSE -amount END) as bal")
            ->groupBy('student_id')->pluck('bal', 'student_id')
            ->map(fn ($b) => round((float) $b, 2));

        // W6.4 (owner decision): a student who LEFT with money still in
        // custody stays on this list, flagged — the old page listed active
        // students only, so a departed student's balance vanished from view
        // while the footer total still counted it (total ≠ sum of rows).
        $holderIds = $balances->filter(fn ($b) => abs($b) >= 0.01)->keys();

        $students = Student::with('activeAssignment.bed.room')
            ->where(fn ($q) => $q->where('status', 'active')->orWhereIn('id', $holderIds))
            ->when($search, fn ($q) => $q->where(fn ($qq) => $qq->where('name', 'like', "%{$search}%")
                ->orWhere('mobile', 'like', "%{$search}%")))
            ->orderBy('name')
            ->get()
            ->each(fn ($s) => $s->pocket_balance = $balances[$s->id] ?? 0.0);

        if ($filter === 'negative') {
            $students = $students->filter(fn ($s) => $s->pocket_balance < 0)->values();
        } elseif ($filter === 'departed') {
            $students = $students->filter(fn ($s) => $s->status !== 'active')->values();
        }

        // Whole-book stats — never shrunk by the search/filter.
        $totals = [
            'custody' => round((float) $balances->sum(), 2),
            'wallets' => $balances->filter(fn ($b) => abs($b) >= 0.01)->count(),
            'negative' => $balances->filter(fn ($b) => $b < 0)->count(),
        ];

        // Paginate the collection by hand (balance + departed-holder logic
        // doesn't translate to one SQL query worth maintaining at this scale).
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 24;
        $studentsPage = new \Illuminate\Pagination\LengthAwarePaginator(
            $students->forPage($page, $perPage)->values(),
            $students->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('admin.pocket_money.index', ['students' => $studentsPage, 'totals' => $totals,
            'search' => $search, 'filter' => $filter]);
    }

    public function show(Request $request, Student $student): View
    {
        $balance = PocketMoneyTransaction::balanceFor($student->id);
        $transactions = PocketMoneyTransaction::with('creator')
            ->where('student_id', $student->id)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        $stats = [
            'deposited' => (float) PocketMoneyTransaction::where('student_id', $student->id)->where('type', 'deposit')->sum('amount'),
            'withdrawn' => (float) PocketMoneyTransaction::where('student_id', $student->id)->where('type', 'withdraw')->sum('amount'),
        ];

        return view('admin.pocket_money.show', compact('student', 'balance', 'transactions', 'stats'));
    }

    public function store(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['deposit', 'withdraw'])],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        // Lending is allowed — negative balances are permitted; the sheet
        // warns before the user confirms.
        $tx = PocketMoneyTransaction::create($data + [
            'hostel_id' => Tenant::id(),
            'student_id' => $student->id,
            'created_by' => $request->user()->id,
        ]);

        $this->logger->log('pocket.'.$data['type'], ucfirst($data['type'])." of ".hostelease_money($data['amount'])
            ." — {$student->name} (balance ".hostelease_money(PocketMoneyTransaction::balanceFor($student->id)).')', $tx);

        return back()->with('success', ucfirst($data['type']).' recorded.');
    }

    public function destroy(Student $student, PocketMoneyTransaction $transaction): RedirectResponse
    {
        abort_unless($transaction->student_id === $student->id, 404);

        // Soft-deleted + logged (W6.4): removing a custody entry is an audited
        // action now, not a silent vanish. The view gates this behind the
        // canonical confirm dialog.
        $this->logger->log('pocket.delete', "Removed {$transaction->type} of "
            .hostelease_money($transaction->amount)." — {$student->name}"
            .($transaction->note ? " ({$transaction->note})" : ''), $transaction);

        $transaction->delete();

        return back()->with('success', 'Transaction removed.');
    }
}
