<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Services\ActivityLogger;
use App\Services\Billing\AccountBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The Super Admin "Customers / Accounts" control terminal (Phase 4).
 * Owner-centric: one account per owner, driven by the Phase 3 AccountBillingService.
 */
class AccountController extends Controller
{
    public function __construct(
        protected AccountBillingService $billing,
        protected ActivityLogger $logger,
    ) {
    }

    /** Customers list — one row per account. */
    public function index(Request $request): View
    {
        // Eager-load owner + branch pivot for the count; no per-row queries (NFR-4).
        $accounts = SubscriptionAccount::query()
            ->with(['owner:id,name,mobile', 'owner.hostels:id'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByRaw("CASE status WHEN 'grace' THEN 0 WHEN 'expired' THEN 1 WHEN 'trial' THEN 2 ELSE 3 END")
            ->orderBy('current_period_end')
            ->paginate(20);

        // Lifetime value for the whole page in one grouped query.
        $ltvs = SubscriptionOrder::whereIn('account_id', collect($accounts->items())->pluck('id'))
            ->where('payment_status', 'paid')
            ->selectRaw('account_id, SUM(amount) as total')
            ->groupBy('account_id')
            ->pluck('total', 'account_id');

        $accounts->through(function (SubscriptionAccount $account) use ($ltvs) {
            $account->branch_count = $account->owner?->hostels->count() ?? 0;
            $account->ltv = (float) ($ltvs[$account->id] ?? 0);

            return $account;
        });

        $summary = [
            'accounts' => SubscriptionAccount::count(),
            'active' => SubscriptionAccount::where('status', 'active')->count(),
            'due' => SubscriptionAccount::whereIn('status', ['grace', 'expired'])->count(),
            'revenue' => (float) SubscriptionOrder::paid()->sum('amount'),
        ];

        return view('superadmin.accounts.index', compact('accounts', 'summary'));
    }

    /** Account 360. */
    public function show(SubscriptionAccount $account): View
    {
        $account->load('owner');
        $branches = $this->billing->includedBranches($account);
        $orders = $account->orders()->with('lines.branch')->latest()->paginate(10);
        $discounts = $account->discounts()->with('branch')->latest()->get();

        // Discount-aware renewal quotes for both terms, so the Renew modal shows
        // the true post-discount total live as the operator toggles Yearly/Monthly.
        $renewQuotes = [
            'yearly' => $this->renewQuoteArray($account, 'yearly'),
            'monthly' => $this->renewQuoteArray($account, 'monthly'),
        ];
        // A 'trial' period account previews at a paid rate (never ₹0) — default the
        // modal to a paid term.
        $displayPeriod = $account->period?->isPaid() ? $account->period->value : 'yearly';

        // A per-branch add-to-cycle quote (each behind branch tops up a different
        // gap to the anchor, so the modal must show that branch's own numbers).
        $anchor = $account->current_period_end;
        $anchorLabel = $anchor?->format('d M Y');
        $addQuotes = [];
        foreach ($branches as $b) {
            $behind = $anchor && $anchor->isFuture() && (! $b->subscription_end || $b->subscription_end->lt($anchor));
            if (! $behind) {
                continue;
            }
            $q = $this->billing->quoteAddBranch($account, $b);
            $addQuotes[$b->id] = [
                'days' => $q['days_remaining'],
                'unit' => (float) $q['unit'],
                'prorated' => round((float) $q['prorated'], 2),
                'volume' => round((float) $q['breakdown']['volume_amount'], 2),
                'manual' => round((float) $q['breakdown']['manual_amount'], 2),
                'auto' => round((float) $q['breakdown']['final'], 2),
                'anchor' => $anchorLabel,
            ];
        }

        // Align quote — per-branch prorated top-ups, for the Align modal preview.
        $alignRaw = $this->billing->quoteAlign($account);
        $alignQuote = [
            'count' => $alignRaw['count'],
            'subtotal' => $alignRaw['subtotal'],
            'anchor' => $anchorLabel,
            'lines' => collect($alignRaw['lines'])->map(fn ($l) => [
                'name' => $l['branch']->name,
                'days' => $l['days'],
                'amount' => round((float) $l['amount'], 2),
            ])->values()->all(),
        ];
        $alignBehind = $alignRaw['count'];

        // Branch data for the Comp modal (checkbox tiles + live gift preview).
        $compBranches = $branches->map(fn ($b) => [
            'id' => $b->id,
            'name' => $b->name,
            'end' => optional($b->subscription_end)->toDateString(),
            'endLabel' => optional($b->subscription_end)->format('d M Y') ?? 'No coverage',
        ])->values()->all();
        $compBranchIds = $branches->pluck('id')->all();

        return view('superadmin.accounts.show', compact('account', 'branches', 'orders', 'discounts', 'renewQuotes', 'displayPeriod', 'addQuotes', 'alignQuote', 'alignBehind', 'compBranches', 'compBranchIds'));
    }

    /** Flatten a renewal quote into the JS-friendly, discount-itemised shape the modal summary reads. */
    protected function renewQuoteArray(SubscriptionAccount $account, string $period): array
    {
        $q = $this->billing->quoteRenewal($account, $period);

        return [
            'quantity' => $q['quantity'],
            'unit' => (float) $q['unit'],
            'subtotal' => round((float) $q['subtotal'], 2),
            'volume' => round((float) $q['breakdown']['volume_amount'], 2),
            'manual' => round((float) $q['breakdown']['manual_amount'], 2),
            'auto' => round((float) $q['breakdown']['final'], 2),
            'new_anchor' => $q['new_anchor']->format('d M Y'),
        ];
    }

    /** Add a single branch to the current cycle with a prorated top-up (co-terminated). */
    public function addBranch(Request $request, SubscriptionAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', Rule::in(['cash', 'upi', 'cheque', 'rtgs', 'online', 'comp'])],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $branch = Hostel::findOrFail($data['branch_id']);
        abort_unless(in_array($branch->id, $account->owner?->accessibleHostelIds() ?? [], true), 403);

        $order = $this->billing->addBranch($account, $branch, [
            'amount' => $data['amount'] ?? null,
            'payment_status' => 'paid',
            'payment_method' => $data['payment_method'] ?? 'cash',
            'remarks' => $data['remarks'] ?? 'Added branch (prorated)',
        ]);

        $this->logger->log('subscription.paid', "Added branch {$branch->name} (prorated) — ".hostelease_money($order->amount), $order);

        return back()->with('success', "{$branch->name} added to the renewal cycle.");
    }

    /** Consolidated renewal — every branch to one new anchor. */
    public function renew(Request $request, SubscriptionAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', Rule::in(['yearly', 'monthly'])],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', Rule::in(['cash', 'upi', 'cheque', 'rtgs', 'online', 'comp'])],
            'transaction_number' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $order = $this->billing->renewAccount($account, $data['period'], [
            'amount' => $data['amount'] ?? null,
            'payment_status' => 'paid',
            'payment_method' => $data['payment_method'] ?? 'cash',
            'transaction_number' => $data['transaction_number'] ?? null,
            'remarks' => $data['remarks'] ?? 'Consolidated renewal',
        ]);

        $this->logger->log('subscription.paid', "Renewed all {$order->quantity} branch(es) — ".hostelease_money($order->amount), $order);

        return back()->with('success', "Renewed {$order->quantity} branch(es) to ".optional($account->fresh()->current_period_end)->format('d M Y').'.');
    }

    /** Align staggered branches up to the anchor with a prorated top-up. */
    public function align(Request $request, SubscriptionAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', Rule::in(['cash', 'upi', 'cheque', 'rtgs', 'online', 'comp'])],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $order = $this->billing->align($account, [
            'amount' => $data['amount'] ?? null,
            'payment_status' => 'paid',
            'payment_method' => $data['payment_method'] ?? 'cash',
            'remarks' => $data['remarks'] ?? 'Align to anchor',
        ]);

        if (! $order) {
            return back()->with('info', 'Nothing to align — all branches already reach the renewal date.');
        }

        $this->logger->log('subscription.create', "Aligned {$order->quantity} branch(es) — ".hostelease_money($order->amount), $order);

        return back()->with('success', "Aligned {$order->quantity} branch(es) to the renewal date.");
    }

    /** Complimentary (₹0) grant — N terms to selected branches. */
    public function comp(Request $request, SubscriptionAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', Rule::in(['yearly', 'monthly'])],
            'multiplier' => ['required', 'integer', 'min:1', 'max:60'],
            'branches' => ['required', 'array', 'min:1'],
            'branches.*' => ['integer'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        // Only branches this owner actually holds may be comped.
        $branchIds = array_values(array_intersect(
            array_map('intval', $data['branches']),
            $account->owner?->accessibleHostelIds() ?? [],
        ));
        abort_unless(count($branchIds) > 0, 422);

        $order = $this->billing->comp($account, $data['period'], (int) $data['multiplier'], $branchIds, $data['reason']);
        $this->logger->log('subscription.paid', "Comp granted ({$data['multiplier']}× {$data['period']}, {$order->quantity} branch(es)) — {$data['reason']}", $order);

        return back()->with('success', 'Complimentary coverage granted.');
    }

    /** Set or clear a bespoke per-account unit price. */
    public function override(Request $request, SubscriptionAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'unit_price_override_yearly' => ['nullable', 'numeric', 'min:0'],
            'unit_price_override_monthly' => ['nullable', 'numeric', 'min:0'],
        ]);

        $yearly = ($data['unit_price_override_yearly'] ?? null) !== null ? (float) $data['unit_price_override_yearly'] : null;
        $monthly = ($data['unit_price_override_monthly'] ?? null) !== null ? (float) $data['unit_price_override_monthly'] : null;

        $this->billing->setUnitPriceOverride($account, $yearly, $monthly);
        $this->logger->log('subscription.update', 'Set custom unit price — yearly: '.($yearly !== null ? hostelease_money($yearly) : 'list').', monthly: '.($monthly !== null ? hostelease_money($monthly) : 'list'), $account);

        return back()->with('success', 'Custom price updated.');
    }

    public function storeDiscount(Request $request, SubscriptionAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'recurrence' => ['required', Rule::in(['one_time', 'one_renewal', 'every_renewal'])],
            'type' => ['required', Rule::in(['percentage', 'fixed'])],
            'value' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $discount = Discount::create(array_merge($data, [
            'account_id' => $account->id,
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]));

        $this->logger->log('subscription.update', "Added {$data['recurrence']} discount — {$data['reason']}", $discount);

        return back()->with('success', 'Discount added.');
    }

    public function revokeDiscount(SubscriptionAccount $account, Discount $discount): RedirectResponse
    {
        abort_unless($discount->account_id === $account->id, 404);

        $discount->update(['status' => 'revoked']);
        $this->logger->log('subscription.update', "Revoked discount #{$discount->id}", $discount);

        return back()->with('success', 'Discount revoked.');
    }

    /** Manual override: suspend the account and every included branch (BR-18). */
    public function suspend(Request $request, SubscriptionAccount $account): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $this->billing->suspend($account, $data['reason']);
        $this->logger->log('subscription.update', "Suspended account — {$data['reason']}", $account);

        return back()->with('success', 'Account suspended — all branches blocked.');
    }

    /** Lift a manual suspension; status/access is recomputed from the account's anchor. */
    public function reactivate(SubscriptionAccount $account): RedirectResponse
    {
        $this->billing->reactivate($account);
        $this->logger->log('subscription.update', 'Reactivated account', $account);

        return back()->with('success', 'Account reactivated.');
    }
}
