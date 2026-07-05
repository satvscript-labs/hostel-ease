<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PocketMoneyTransaction;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PocketMoneyController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function index(Request $request): JsonResponse
    {
        // Net balance per student in one grouped query.
        $balances = PocketMoneyTransaction::query()
            ->selectRaw("student_id, SUM(CASE WHEN type='deposit' THEN amount ELSE -amount END) as bal")
            ->groupBy('student_id')->pluck('bal', 'student_id');

        $students = Student::active()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->orderBy('name')->get(['id', 'name', 'mobile'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'mobile' => $s->mobile,
                'balance' => round((float) ($balances[$s->id] ?? 0), 2),
            ]);

        return response()->json([
            'students' => $students,
            'summary' => ['total_held' => round((float) $balances->sum(), 2)],
        ]);
    }

    public function show(int $student): JsonResponse
    {
        $model = Student::findOrFail($student);
        $tx = PocketMoneyTransaction::where('student_id', $model->id)->orderByDesc('created_at')->get();

        return response()->json([
            'student' => ['id' => $model->id, 'name' => $model->name, 'mobile' => $model->mobile],
            'balance' => PocketMoneyTransaction::balanceFor($model->id),
            'transactions' => $tx->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => (float) $t->amount,
                'note' => $t->note,
                'date' => $t->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request, int $student): JsonResponse
    {
        $model = Student::findOrFail($student);
        $data = $request->validate([
            'type' => ['required', Rule::in(['deposit', 'withdraw'])],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['type'] === 'withdraw') {
            $balance = PocketMoneyTransaction::balanceFor($model->id);
            if ($data['amount'] > $balance) {
                throw ValidationException::withMessages([
                    'amount' => ['Insufficient balance. Available: '.hostelease_money($balance)],
                ]);
            }
        }

        $tx = PocketMoneyTransaction::create([
            'hostel_id' => Tenant::id(),
            'student_id' => $model->id,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'note' => $data['note'] ?? null,
            'created_by' => $request->user()->id,
        ]);
        $this->logger->log('pocket_money.'.$data['type'], "{$data['type']} {$data['amount']} for {$model->name}", $tx);

        return response()->json([
            'message' => ucfirst($data['type']).' recorded.',
            'balance' => PocketMoneyTransaction::balanceFor($model->id),
        ], 201);
    }

    public function destroy(int $student, int $transaction): JsonResponse
    {
        $tx = PocketMoneyTransaction::where('student_id', $student)->findOrFail($transaction);
        $tx->delete();

        return response()->json([
            'message' => 'Transaction removed.',
            'balance' => PocketMoneyTransaction::balanceFor($student),
        ]);
    }
}

