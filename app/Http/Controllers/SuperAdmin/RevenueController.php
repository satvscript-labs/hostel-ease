<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Billing\RevenueMetrics;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The Super Admin Revenue page (S5 · _artifact/saas_billing_autopay/28_S5_DESIGN.md §6).
 * Every figure is RevenueMetrics' — this controller only arranges them for the page.
 */
class RevenueController extends Controller
{
    public function index(RevenueMetrics $metrics): View
    {
        $today = Carbon::now()->startOfDay();
        $thisMonth = $today->copy()->startOfMonth();
        $lastMonth = $thisMonth->copy()->subMonthNoOverflow();

        $mrr = $metrics->mrr();
        $tiles = [
            'mrr' => $mrr,
            'arr' => round($mrr * 12, 2),
            'paying' => $metrics->payingCustomers(),
            'arpa' => $metrics->arpa(),
            'customers' => $metrics->customersCovered(),
            'cash_this' => $metrics->cash($thisMonth, $thisMonth->copy()->addMonthNoOverflow()),
            'cash_last' => $metrics->cash($lastMonth, $thisMonth),
            'deferred' => $metrics->deferred(),
            'receivables' => $metrics->receivables(),
        ];

        // Twelve months: cash, earned, MRR on the month's last day, churn.
        $months = collect($metrics->months(12))->map(function (array $m) use ($metrics, $today) {
            // The same instant "How MRR moved" closes each month on — the 1st of the next
            // (this month: today) — so the chart, the table and the movement agree to
            // the rupee (S5 audit B6).
            $asOf = $m['to']->greaterThan($today) ? $today : $m['to'];

            return [
                'key' => $m['key'],
                'label' => $m['label'],
                'cash' => $metrics->cash($m['from'], $m['to']),
                'earned' => $metrics->earned($m['from'], $m['to']->greaterThan($today) ? $today->copy()->addDay() : $m['to']),
                'mrr' => $metrics->mrr($asOf),
                'churn' => $metrics->churnRate($m['from'], $m['to']),
                'current' => $m['to']->greaterThan($today),
            ];
        })->all();

        // MRR movement over the last six complete months, plus this month so far.
        $movement = collect($metrics->months(7))->map(fn (array $m) => [
            'label' => $m['label'],
            'current' => $m['to']->greaterThan($today),
        ] + $metrics->movement($m['from'], $m['to']->greaterThan($today) ? $today : $m['to']))->all();

        $cohorts = array_slice($metrics->trialCohorts(), 0, 12, true);

        $lost = $metrics->lostCustomers()->take(10);
        $who = $metrics->accounts($lost->pluck('account')->all());
        $lost = $lost->map(fn (array $r) => $r + ($who[$r['account']] ?? ['name' => 'Account #'.$r['account'], 'public_id' => null]));

        return view('superadmin.revenue.index', compact('tiles', 'months', 'movement', 'cohorts', 'lost'));
    }
}
