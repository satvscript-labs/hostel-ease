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
        $orders = $account->orders()->with('lines')->latest()->paginate(10);
        $discounts = $account->discounts()->with('branch')->latest()->get();

        $period = $account->period?->value ?? 'yearly';
        $renewQuote = $this->billing->quoteRenewal($account, $period);

        // A per-branch add-to-cycle quote (each behind branch tops up a different
        // gap to the anchor, so the modal must show that branch's own number).
        $anchor = $account->current_period_end;
        $addQuotes = [];
        foreach ($branches as $b) {
            $behind = $anchor && $anchor->isFuture() && (! $b->subscription_end || $b->subscription_end->lt($anchor));
            if (! $behind) {
                continue;
            }
            $q = $this->billing->quoteAddBranch($account, $b);
            $addQuotes[$b->id] = ['days' => $q['days_remaining'], 'amount' => round((float) $q['breakdown']['final'], 2)];
        }
        $alignBehind = $branches->filter(fn ($b) => ! $b->subscription_end || ($account->current_period_end && $b->subscription_end->lt($account->current_period_end)))->count();

        return view('superadmin.accounts.show', compact('account', 'branches', 'orders', 'discounts', 'renewQuote', 'addQuotes', 'alignBehind'));
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
    public function align(SubscriptionAccount $account): RedirectResponse
    {
        $order = $this->billing->align($account, ['payment_status' => 'paid', 'payment_method' => 'cash', 'remarks' => 'Align to anchor']);

        if (! $order) {
            return back()->with('info', 'Nothing to align — all branches already reach the renewal date.');
        }

        $this->logger->log('subscription.create', "Aligned {$order->quantity} branch(es) — ".hostelease_money($order->amount), $order);

        return back()->with('success', "Aligned {$order->quantity} branch(es) to the renewal date.");
    }

    /** Complimentary (₹0) grant across the account. */
    public function comp(Request $request, SubscriptionAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', Rule::in(['yearly', 'monthly'])],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $order = $this->billing->comp($account, $data['period'], $data['reason']);
        $this->logger->log('subscription.paid', "Comp granted ({$data['period']}) — {$data['reason']}", $order);

        return back()->with('success', 'Complimentary coverage granted.');
    }

    /** Set or clear a bespoke per-account unit price. */
    public function override(Request $request, SubscriptionAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'unit_price_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->billing->setUnitPriceOverride($account, $data['unit_price_override'] ?? null);
        $this->logger->log('subscription.update', 'Set custom unit price '.($data['unit_price_override'] !== null ? hostelease_money($data['unit_price_override']) : 'cleared'), $account);

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
