<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CollectPaymentRequest;
use App\Models\MonthlyRent;
use App\Services\ActivityLogger;
use App\Services\MonthlyRentService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class MonthlyRentController extends Controller
{
    public function __construct(
        protected PaymentService $payments,
        protected MonthlyRentService $rents,
        protected ActivityLogger $logger,
    ) {
    }

    public function index(Request $request): View
    {
        // Default to the current month.
        $month = $request->filled('month')
            ? Carbon::parse($request->input('month').'-01')
            : now()->startOfMonth();

        $rows = MonthlyRent::with('student')
            ->whereDate('rent_month', $month->toDateString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('id')
            ->get();

        $summary = [
            'amount' => (float) $rows->sum('amount'),
            'paid' => (float) $rows->sum('paid_amount'),
            'due' => (float) $rows->sum('balance'),
        ];

        return view('admin.monthly_rents.index', compact('rows', 'summary', 'month'));
    }

    public function generate(Request $request): RedirectResponse
    {
        $month = Carbon::parse($request->input('month', now()->format('Y-m')).'-01');
        $created = $this->rents->generateForMonth($month);

        $this->logger->log('rent.generate', "Generated {$created} rent rows for {$month->format('M Y')}");

        return redirect()->route('admin.monthly-rents.index', ['month' => $month->format('Y-m')])
            ->with('success', "{$created} rent row(s) generated for {$month->format('M Y')}.");
    }

    public function collect(CollectPaymentRequest $request, MonthlyRent $monthly_rent): RedirectResponse
    {
        $payment = $this->payments->record(
            array_merge($request->validated(), ['student_id' => $monthly_rent->student_id]),
            $monthly_rent,
        );

        return redirect()->route('admin.payments.show', $payment)
            ->with('success', "Rent payment recorded for {$monthly_rent->rent_month->format('M Y')}.");
    }

    public function destroy(MonthlyRent $monthly_rent): RedirectResponse
    {
        if ((float) $monthly_rent->paid_amount > 0) {
            return back()->with('error', 'This rent has payments against it — reverse the receipt(s) first.');
        }

        $monthly_rent->delete();

        return back()->with('success', 'Rent row removed.');
    }
}
