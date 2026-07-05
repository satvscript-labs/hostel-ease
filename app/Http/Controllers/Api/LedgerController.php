<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;

class LedgerController extends Controller
{
    public function __construct(protected LedgerService $ledger)
    {
    }

    public function index(): JsonResponse
    {
        $students = Student::with(['semesterFees', 'monthlyRents', 'acBillShares', 'payments'])
            ->orderBy('name')->get();

        $rows = $students->map(function ($s) {
            $t = $this->ledger->totalsFor($s);

            return [
                'student_id' => $s->id,
                'student' => $s->name,
                'mobile' => $s->mobile,
                'billed' => $t['billed'],
                'paid' => $t['paid'],
                'outstanding' => $t['outstanding'],
            ];
        });

        return response()->json([
            'rows' => $rows,
            'totals' => [
                'billed' => (float) $rows->sum('billed'),
                'paid' => (float) $rows->sum('paid'),
                'outstanding' => (float) $rows->sum('outstanding'),
            ],
        ]);
    }

    public function show(int $student): JsonResponse
    {
        $model = Student::with(['semesterFees', 'monthlyRents', 'acBillShares', 'payments'])->findOrFail($student);
        $totals = $this->ledger->totalsFor($model);
        $obligations = $this->ledger->obligations($model)->map(fn ($o) => [
            'date' => $o['date'] instanceof \Illuminate\Support\Carbon ? $o['date']->toDateString() : (string) $o['date'],
            'particular' => $o['particular'],
            'amount' => $o['amount'],
            'paid' => $o['paid'],
            'balance' => $o['balance'],
            'status' => $o['status'],
        ]);

        return response()->json([
            'student' => ['id' => $model->id, 'name' => $model->name, 'mobile' => $model->mobile],
            'totals' => $totals,
            'obligations' => $obligations,
            'payments' => $model->payments()->orderByDesc('paid_on')->get()->map(fn ($p) => [
                'receipt_number' => $p->receipt_number,
                'amount' => (float) $p->amount,
                'mode' => $p->mode,
                'paid_on' => $p->paid_on?->toDateString(),
            ]),
        ]);
    }
}
