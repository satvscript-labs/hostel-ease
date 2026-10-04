<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\BillingPeriod;
use App\Enums\CollectionMethod;
use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Services\ActivityLogger;
use App\Services\Billing\AccountBillingService;
use App\Services\Billing\PaymentLinkService;
use App\Services\HostelService;
use App\Services\NotificationService;
use App\Support\Refusal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        protected HostelService $hostels,
        protected NotificationService $notifications,
        protected PaymentLinkService $paymentLinks,
    ) {}

    /**
     * Shared tail for the three charge actions (renew / add-branch / align).
     *
     * S2 gives each of them two ways to collect, and they must stay ONE code path
     * up to this point: the same quote, the same validation, the same order. If
     * "record it offline" and "send a payment link" ever became separate routes
     * they could drift apart and price the same charge differently, which is the
     * whole failure mode the one-engine design exists to prevent.
     *
     *  · offline — the order is created PAID, exactly as it was before S2.
     *  · link    — the order is created PENDING and a Razorpay link is attached
     *              inside the same transaction, so a gateway failure leaves no
     *              orphan proforma behind (design §6 BP4).
     *
     * @param  \Closure(array): SubscriptionOrder  $charge  receives the payment array
     */
    protected function collectOrRecord(string $collect, array $payment, \Closure $charge): array
    {
        if ($collect !== 'link') {
            return [$charge($payment + ['payment_status' => 'paid']), false];
        }

        // A link has no payment instrument yet, so carrying the modal's method and
        // reference across would stamp a pending charge "Cash" and leave a
        // misleading method on the order right up until Razorpay fills in the real
        // one. Drop them; the webhook sets method = online when the money lands.
        unset($payment['payment_method'], $payment['transaction_number']);

        $order = $this->paymentLinks->collect(function () use ($charge, $payment) {
            $order = $charge($payment + [
                'payment_status' => 'pending',
                // Stamped up front so the order reads honestly even in the window
                // before the link id comes back from Razorpay.
                'collection' => CollectionMethod::Link->value,
            ]);

            // align() legitimately returns null when there is nothing behind the
            // anchor. Refuse rather than hand a null to the link service — and the
            // transaction rolls back, so nothing is half-created.
            if (! $order) {
                throw new \RuntimeException('There is nothing to charge for right now, so no payment link was created.');
            }

            return $order;
        });

        return [$order, true];
    }

    /** The flash message for a freshly issued link — the URL is the deliverable. */
    protected function linkIssued(SubscriptionOrder $order, string $what): RedirectResponse
    {
        return back()
            ->with('success', "{$what} — payment link for ".hostelease_money($order->amount).' is ready to send.')
            ->with('payment_link', [
                'url' => $order->payment_link_url,
                'amount' => (float) $order->amount,
                'invoice' => $order->invoiceNumber(),
                'order_id' => $order->id,
                'expires' => $order->payment_link_expires_at?->format('d M Y'),
            ]);
    }

    /** Customers list — one row per account. */
    public function index(Request $request): View
    {
        // Renewals worklist filter (item 15/§4): "due within N days" — accounts
        // approaching OR past their renewal date (excludes manually suspended).
        $dueDays = $request->filled('due') ? max(1, (int) $request->integer('due')) : null;

        // Eager-load owner + branch pivot for the count; no per-row queries (NFR-4).
        $accounts = SubscriptionAccount::query()
            ->with(['owner:id,name,mobile', 'owner.hostels:id'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($dueDays, fn ($q) => $q->where('status', '!=', 'suspended')
                ->whereNotNull('current_period_end')
                ->where('current_period_end', '<=', now()->addDays($dueDays)->endOfDay()))
            // A due worklist sorts purely by soonest/overdue first; the default
            // list leads with grace/expired then by date.
            ->when($dueDays, fn ($q) => $q->orderBy('current_period_end'))
            ->when(! $dueDays, fn ($q) => $q
                ->orderByRaw("CASE status WHEN 'grace' THEN 0 WHEN 'expired' THEN 1 WHEN 'trial' THEN 2 ELSE 3 END")
                ->orderBy('current_period_end'))
            ->paginate(20)
            ->withQueryString();

        // Lifetime value for the whole page in one grouped query.
        $ltvs = SubscriptionOrder::whereIn('account_id', collect($accounts->items())->pluck('id'))
            ->where('payment_status', 'paid')
            ->selectRaw('account_id, SUM(amount) as total')
            ->groupBy('account_id')
            ->pluck('total', 'account_id');

        $accounts->through(function (SubscriptionAccount $account) use ($ltvs) {
            $account->branch_count = $account->owner?->hostels->count() ?? 0;
            $account->ltv = (float) ($ltvs[$account->id] ?? 0);
            $account->days_until = $account->daysUntilAnchor();

            return $account;
        });

        $dueSoon = fn (int $days) => SubscriptionAccount::where('status', '!=', 'suspended')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now()->addDays($days)->endOfDay())
            ->count();

        // Receivables (S1 item 14) — real now that a pending charge mints an order
        // (S0 · F3). `outstanding()` excludes the ₹0 kinds, which are never owed.
        $outstanding = SubscriptionOrder::outstanding()->get(['amount', 'created_at']);
        $aged = fn (?int $from, ?int $to) => (float) $outstanding
            ->filter(function ($o) use ($from, $to) {
                $days = (int) $o->created_at->diffInDays(now());

                return ($from === null || $days >= $from) && ($to === null || $days <= $to);
            })
            ->sum('amount');

        $summary = [
            'accounts' => SubscriptionAccount::count(),
            'active' => SubscriptionAccount::where('status', 'active')->count(),
            'due_30' => $dueSoon(30),
            'revenue' => (float) SubscriptionOrder::paid()->sum('amount'),
            'receivable' => (float) $outstanding->sum('amount'),
            'receivable_count' => $outstanding->count(),
            'receivable_aged' => [
                'fresh' => $aged(null, 7),
                'mid' => $aged(8, 30),
                'old' => $aged(31, null),
            ],
            // Open removal requests, so an owner's ask is never quietly missed (D11).
            'removal_requests' => Hostel::whereNotNull('cancellation_requested_at')->whereNull('cancelled_at')->count(),
            // Payment links sent and not yet paid (S2). A different question from
            // "awaiting payment": a charge can be owed with no link at all, and a
            // link can be dead while the charge is still owed. This is the one that
            // says "the customer has been asked and has not acted".
            'live_links' => SubscriptionOrder::withLiveLink()->count(),
            'live_links_value' => (float) SubscriptionOrder::withLiveLink()->sum('amount'),
        ];

        return view('superadmin.accounts.index', compact('accounts', 'summary', 'dueDays'));
    }

    /**
     * A subscription invoice PDF for one order — the platform billing the
     * customer for their branches. Paid orders render a Tax Invoice; unpaid
     * ones a Proforma (the status drives the doctype, not a separate route).
     */
    public function invoice(SubscriptionAccount $account, SubscriptionOrder $order)
    {
        // The order must belong to THIS account — the URL binds both, so a
        // mismatched pair is a 404, not someone else's invoice.
        abort_unless($order->account_id === $account->id, 404);

        $order->load(['lines.branch', 'account.owner']);

        $pdf = Pdf::loadView('superadmin.orders.invoice_pdf', [
            'order' => $order,
            'account' => $account,
            'company' => config('hostelease.company'),
        ]);

        $this->logger->log('order.invoice', "Downloaded invoice {$order->invoiceNumber()} for "
            .($account->owner?->name ?? 'account #'.$account->id), $order);

        return $pdf->download($order->invoiceNumber().'.pdf');
    }

    /** Account 360. */
    public function show(SubscriptionAccount $account): View
    {
        $account->load('owner');

        // EVERY branch, cancelled ones included (D11): Account 360 is the operator's
        // whole view of the estate, and a cancelled branch still needs its state,
        // its run-out date and a Restore action. Quotes below use the BILLABLE set,
        // which is what includedBranches() returns.
        $branches = $this->billing->allBranches($account);
        $billable = $this->billing->includedBranches($account);
        $orders = $account->orders()->with('lines.branch')->latest()->paginate(10);
        $discounts = $account->discounts()->with('branch')->latest()->get();

        // What removing each live branch would do to the bill — the tier-loss warning
        // in particular (D11 case 11), shown before the operator confirms.
        // Removing ANY live branch has the same effect on the bill — quantity drops by
        // one, and the tier maths follows from that — so the impact is computed ONCE
        // and shared, rather than per branch. It used to run per branch (plus a
        // pending-orders query each), which cost ~6 queries per branch: 96 on a
        // 10-branch customer's Account 360 (NFR-4).
        $sharedImpact = $billable->isNotEmpty()
            ? $this->billing->removalImpact($account, $billable->first())
            : null;

        // D11 case 19: an unpaid charge against a branch being removed is a decision
        // the operator has to make, not something to discover later. One grouped
        // query for the whole page.
        $pendingByBranch = $this->billing->pendingOrderCountsByBranch($branches);

        $removalImpact = [];
        foreach ($branches as $b) {
            if ($b->isCancelled() || ! $sharedImpact) {
                continue;
            }

            $removalImpact[$b->id] = $sharedImpact + [
                'covered_to' => $b->subscription_end?->format('d M Y') ?? 'no coverage',
                'pending' => $pendingByBranch[$b->id] ?? 0,
            ];
        }
        $accountClosing = $billable->isEmpty() && $branches->isNotEmpty();

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

        // Add-hostel-to-owner quote (a brand-new branch): prorate to the anchor at
        // the account's own cadence when the cycle is live (discount-aware), else a
        // full paid term. The period is dictated by the account (co-termination),
        // not a free choice, so the summary always matches what addBranch() charges.
        $paidPeriod = ($account->period && $account->period->isPaid()) ? $account->period->value : 'yearly';
        $addHostelQuote = $this->addHostelQuoteArray($account, $paidPeriod);
        $ownerEmail = $account->owner?->email;

        // S2 — online collection. When Razorpay is not configured the link options
        // are hidden rather than shown-and-broken: offering a button that always
        // errors is worse than not offering it, and offline recording is a complete
        // path on its own (06 §4 — it is also the cheapest one for large amounts).
        $linksEnabled = $this->paymentLinks->isEnabled();
        $liveLinks = $account->orders()->withLiveLink()->get(['id', 'amount', 'payment_link_url', 'payment_link_expires_at']);

        // The share panel's seed data, built here rather than in the view. The view
        // CANNOT use a `@php … @endphp` block — it already uses the inline
        // `@php(...)` form, and Blade pairs the first `@php` with the first
        // `@endphp`, which swallows everything between them into one raw PHP region.
        $shareSeed = session('payment_link') ?: ['url' => '', 'amount' => 0, 'invoice' => '', 'expires' => null];
        $shareTo = [
            'mobile' => preg_replace('/\D/', '', (string) $account->owner?->mobile),
            'email' => $ownerEmail,
            'company' => config('hostelease.company.name', 'HostelEase'),
        ];

        return view('superadmin.accounts.show', compact('account', 'branches', 'billable', 'orders', 'discounts', 'renewQuotes', 'displayPeriod', 'addQuotes', 'alignQuote', 'alignBehind', 'compBranches', 'compBranchIds', 'addHostelQuote', 'paidPeriod', 'ownerEmail', 'removalImpact', 'accountClosing', 'linksEnabled', 'liveLinks', 'shareSeed', 'shareTo'));
    }

    /** Quote adding a brand-new branch to the owner, for the Add-hostel modal summary. */
    protected function addHostelQuoteArray(SubscriptionAccount $account, string $period): array
    {
        $anchor = $account->current_period_end;

        if ($anchor && $anchor->isFuture()) {
            $q = $this->billing->quoteAddBranch($account, null, $period);

            return [
                'mode' => 'prorate',
                'days' => $q['days_remaining'],
                'unit' => (float) $q['unit'],
                'prorated' => round((float) $q['prorated'], 2),
                'volume' => round((float) $q['breakdown']['volume_amount'], 2),
                'manual' => round((float) $q['breakdown']['manual_amount'], 2),
                'auto' => round((float) $q['breakdown']['final'], 2),
                'anchor' => $anchor->format('d M Y'),
            ];
        }

        // No live cycle to co-terminate with — a plain full paid term from today.
        $unit = $this->billing->unitPrice($account, BillingPeriod::from($period));

        return ['mode' => 'full', 'days' => 0, 'unit' => $unit, 'prorated' => $unit, 'volume' => 0.0, 'manual' => 0.0, 'auto' => $unit, 'anchor' => null];
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
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'collect' => ['nullable', Rule::in(['offline', 'link'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'upi', 'cheque', 'rtgs', 'online', 'comp'])],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $branch = Hostel::findOrFail($data['branch_id']);
        abort_unless(in_array($branch->id, $account->owner?->accessibleHostelIds() ?? [], true), 403);

        try {
            [$order, $viaLink] = $this->collectOrRecord(
                $data['collect'] ?? 'offline',
                [
                    'amount' => $data['amount'] ?? null,
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'remarks' => $data['remarks'] ?? 'Added branch (prorated)',
                ],
                fn (array $payment) => $this->billing->addBranch($account, $branch, $payment),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', Refusal::message($e));
        }

        if ($viaLink) {
            return $this->linkIssued($order, "{$branch->name} quoted for the renewal cycle");
        }

        $this->logger->log('subscription.paid', "Added branch {$branch->name} (prorated) — ".hostelease_money($order->amount), $order);

        return back()->with('success', "{$branch->name} added to the renewal cycle.");
    }

    /**
     * Create a brand-new branch directly under this owner (no re-entered
     * owner details, no new login) and charge its first term through the
     * account path so discounts apply (P4 item 3.1).
     */
    public function addHostel(Request $request, SubscriptionAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'gst_number' => ['nullable', 'string', 'max:50'],
            'plan' => ['required', Rule::in(['yearly', 'monthly', 'trial'])],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'payment_method' => ['nullable', Rule::in(['cash', 'upi', 'cheque', 'rtgs', 'online', 'comp'])],
        ]);

        $owner = $account->owner;
        abort_unless($owner, 404);

        $hostel = DB::transaction(function () use ($account, $owner, $data) {
            $hostel = $this->hostels->createBranchForOwner($owner, $data);

            if ($data['plan'] === 'trial') {
                // 14-day free trial — its own clock, no co-termination, no charge.
                $this->billing->recordBranchRenewal($hostel, 'trial', [
                    'payment_status' => 'paid', 'payment_method' => null, 'remarks' => 'Added branch (trial)',
                ]);
            } else {
                // Prorate + co-terminate onto the anchor (discount-aware), or a
                // full paid term when there is no live cycle.
                $this->billing->addBranch($account, $hostel, [
                    'amount' => $data['amount'] ?? null,
                    'payment_status' => 'paid',
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'remarks' => 'Added hostel to account (prorated)',
                ]);
            }

            return $hostel;
        });

        $this->logger->log('hostel.provision', "Added branch {$hostel->name} to {$owner->name}", $hostel);

        return back()->with('success', "{$hostel->name} added under {$owner->name}.");
    }

    /** Consolidated renewal — every branch to one new anchor. */
    public function renew(Request $request, SubscriptionAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', Rule::in(['yearly', 'monthly'])],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'collect' => ['nullable', Rule::in(['offline', 'link'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'upi', 'cheque', 'rtgs', 'online', 'comp'])],
            'transaction_number' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            [$order, $viaLink] = $this->collectOrRecord(
                $data['collect'] ?? 'offline',
                [
                    'amount' => $data['amount'] ?? null,
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'transaction_number' => $data['transaction_number'] ?? null,
                    'remarks' => $data['remarks'] ?? 'Consolidated renewal',
                ],
                fn (array $payment) => $this->billing->renewAccount($account, $data['period'], $payment),
            );
        } catch (\RuntimeException $e) {
            // Two reasons to land here, both the operator's to resolve:
            //  · nothing billable (D11 case 9) — every branch cancelled. Refusing
            //    beats writing a ₹0 order that would make a closed account look
            //    renewed, forever.
            //  · a payment-link guard refused, or Razorpay did. The order was rolled
            //    back with the transaction, so there is nothing to clean up.
            return back()->with('error', Refusal::message($e));
        }

        if ($viaLink) {
            return $this->linkIssued($order, "Renewal quoted for {$order->quantity} branch(es)");
        }

        $this->logger->log('subscription.paid', "Renewed all {$order->quantity} branch(es) — ".hostelease_money($order->amount), $order);

        return back()->with('success', "Renewed {$order->quantity} branch(es) to ".optional($account->fresh()->current_period_end)->format('d M Y').'.');
    }

    /** Align staggered branches up to the anchor with a prorated top-up. */
    public function align(Request $request, SubscriptionAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'collect' => ['nullable', Rule::in(['offline', 'link'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'upi', 'cheque', 'rtgs', 'online', 'comp'])],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        // align() returns null when there is nothing behind the anchor. On the link
        // path that null would reach PaymentLinkService with no order to attach to,
        // so the "nothing to do" case is caught here, before a transaction opens.
        if ($this->billing->quoteAlign($account)['count'] === 0) {
            return back()->with('info', 'Nothing to align — all branches already reach the renewal date.');
        }

        try {
            [$order, $viaLink] = $this->collectOrRecord(
                $data['collect'] ?? 'offline',
                [
                    'amount' => $data['amount'] ?? null,
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'remarks' => $data['remarks'] ?? 'Align to anchor',
                ],
                fn (array $payment) => $this->billing->align($account, $payment),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', Refusal::message($e));
        }

        if (! $order) {
            return back()->with('info', 'Nothing to align — all branches already reach the renewal date.');
        }

        if ($viaLink) {
            return $this->linkIssued($order, "Alignment quoted for {$order->quantity} branch(es)");
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

    // -----------------------------------------------------------------
    // Orders — accept a pending charge, or write one off (S1 / D8)
    // -----------------------------------------------------------------

    /** The money arrived: flip a pending order to paid, which grants its coverage. */
    public function acceptOrder(Request $request, SubscriptionAccount $account, SubscriptionOrder $order): RedirectResponse
    {
        abort_unless($order->account_id === $account->id, 404);

        $data = $request->validate([
            'payment_method' => ['nullable', Rule::in(['cash', 'upi', 'cheque', 'rtgs', 'online'])],
            'transaction_number' => ['nullable', 'string', 'max:100'],
        ]);

        if ($order->payment_status->value === 'paid') {
            return back()->with('info', 'That payment was already accepted.');
        }

        // ── KILL THE LINK FIRST (S2 · design §6 BP2) ──
        // The charge is being settled some other way, so a live link is now a
        // loaded gun: the customer who paid by bank transfer taps the week-old link
        // in their inbox and pays again. Cancelling never throws — if Razorpay
        // refuses, it reads the real status back instead, so the operator's action
        // cannot fail because of upstream state they do not control.
        $hadLink = $order->hasLiveLink();
        $this->paymentLinks->cancelIfLive($order, 'charge accepted offline');

        $this->billing->acceptOrder($order, [
            'payment_method' => $data['payment_method'] ?? 'cash',
            'transaction_number' => $data['transaction_number'] ?? null,
        ]);

        $this->logger->log('subscription.paid', "Accepted payment {$order->invoiceNumber()} — ".hostelease_money($order->amount), $order);

        return back()->with('success', $hadLink
            ? 'Payment accepted — coverage updated, and the payment link was cancelled so it cannot be paid twice.'
            : 'Payment accepted — coverage updated.');
    }

    // -----------------------------------------------------------------
    // Payment links (S2) — operator-initiated online collection
    //
    // Each action takes its target as a POSTED INTEGER resolved against this
    // account's own orders, never a URL segment assembled in the browser
    // (development_standards.md §1.1 rule 3, and the S1 defect that rule exists
    // to prevent — 08_S1_VERIFICATION.md §2).
    // -----------------------------------------------------------------

    /** Issue a link for an existing pending charge, or re-issue after one lapsed. */
    public function issueLink(Request $request, SubscriptionAccount $account): RedirectResponse
    {
        $order = $this->orderOnAccount($account, (int) $request->validate([
            'order_id' => ['required', 'integer'],
        ])['order_id']);

        try {
            $order = $this->paymentLinks->issue($order);
        } catch (\RuntimeException $e) {
            return back()->with('error', Refusal::message($e));
        }

        return $this->linkIssued($order, $order->payment_link_attempts > 1 ? 'Link re-issued' : 'Link issued');
    }

    /** Kill a live link so it can never take money again. */
    public function cancelLink(Request $request, SubscriptionAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $order = $this->orderOnAccount($account, (int) $data['order_id']);

        if (! $order->payment_link_id) {
            return back()->with('info', 'There is no payment link on that charge.');
        }

        $cancelled = $this->paymentLinks->cancel($order, $data['reason'] ?? '');

        return back()->with(
            $cancelled ? 'success' : 'error',
            $cancelled
                ? 'Payment link cancelled — it can no longer be paid. The charge is still owed.'
                : 'Razorpay would not cancel that link; its status has been refreshed above. If it now reads Paid, use "Check with Razorpay" to apply the payment.',
        );
    }

    /** Ask Razorpay to re-send a live link — the free nudge. */
    public function resendLink(Request $request, SubscriptionAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer'],
            'medium' => ['nullable', Rule::in(['sms', 'email'])],
        ]);

        $order = $this->orderOnAccount($account, (int) $data['order_id']);
        $medium = $data['medium'] ?? 'sms';

        if ($medium === 'email' && ! $account->owner?->email) {
            return back()->with('error', 'This owner has no email on file, so Razorpay has nowhere to send it. Re-send by SMS, or share the link yourself.');
        }

        try {
            $this->paymentLinks->resend($order, $medium);
        } catch (\RuntimeException $e) {
            return back()->with('error', Refusal::message($e));
        }

        return back()->with('success', 'Razorpay has re-sent the payment link by '.strtoupper($medium).'.');
    }

    /**
     * Read the link's true state from Razorpay and apply a payment we missed.
     *
     * This is the safety valve for the phase's most likely production failure: an
     * unsubscribed webhook event or a mismatched webhook secret fails SILENTLY, so
     * a customer pays and nothing happens here. One button turns that from an
     * invisible money bug into a five-second fix (design §6 BP6).
     */
    public function checkLink(Request $request, SubscriptionAccount $account): RedirectResponse
    {
        $order = $this->orderOnAccount($account, (int) $request->validate([
            'order_id' => ['required', 'integer'],
        ])['order_id']);

        try {
            $result = $this->paymentLinks->checkWithRazorpay($order);
        } catch (\RuntimeException $e) {
            return back()->with('error', Refusal::message($e));
        }

        return back()->with($result['applied'] ? 'success' : 'info', $result['message']);
    }

    /**
     * Resolve a posted order id against THIS account's orders. Scoped by the
     * query rather than by trusting the id, so a crafted one reaches a 404 instead
     * of someone else's charge.
     */
    private function orderOnAccount(SubscriptionAccount $account, int $orderId): SubscriptionOrder
    {
        return $account->orders()->whereKey($orderId)->firstOr(fn () => abort(404));
    }

    /**
     * Write an order off. Never a hard delete: the row and its invoice number stay,
     * which is what an auditor expects. Voiding a PAID order withdraws the coverage
     * it granted, so the reason is mandatory.
     */
    public function voidOrder(Request $request, SubscriptionAccount $account): RedirectResponse
    {
        $data = $request->validate([
            // A posted DB reference, so an integer (standards §1.1 rule 3). Scoped to
            // this account by the query, not by trusting the id.
            'order_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $order = $account->orders()->whereKey($data['order_id'])->firstOr(fn () => abort(404));

        $wasPaid = $order->payment_status->value === 'paid';

        // A voided charge must not remain payable. Same reasoning as acceptOrder():
        // a live link on a written-off order would take money for something that no
        // longer exists here (design §6 BP2).
        $hadLink = $order->hasLiveLink();
        $this->paymentLinks->cancelIfLive($order, 'charge voided: '.$data['reason']);

        $this->billing->voidOrder($order, $data['reason']);

        $this->logger->log('subscription.update', "Voided order {$order->invoiceNumber()} — {$data['reason']}", $order);

        $suffix = $hadLink ? ' Its payment link was cancelled too.' : '';

        return back()->with('success', ($wasPaid
            ? 'Order voided — the coverage it granted has been withdrawn.'
            : 'Order voided.').$suffix);
    }

    // -----------------------------------------------------------------
    // Branch removal (D11) — the operator decides
    // -----------------------------------------------------------------

    /**
     * Confirm removal of a branch (BR-12). It keeps the coverage it paid for and
     * stops being billed from the next cycle. No refund, no credit (BRD D6).
     */
    public function cancelBranch(Request $request, SubscriptionAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $hostel = $this->branchOnAccount($account, (int) $data['branch_id']);

        $impact = $this->billing->removalImpact($account, $hostel);

        if (! $this->billing->cancelBranch($hostel, $data['reason'])) {
            return back()->with('info', "{$hostel->name} is already cancelled.");
        }

        $this->logger->log('subscription.update', "Cancelled branch {$hostel->name} — {$data['reason']}", $hostel);

        // An account with nothing left to bill is a customer leaving. Make that
        // impossible to miss rather than something noticed at the next renewal.
        if ($impact['closes_account']) {
            $this->notifications->push(
                null,
                'account_closing',
                'account_closing:'.$account->id,
                'Account closing — '.($account->owner?->name ?? 'account #'.$account->id),
                'Every branch on this account is now cancelled. Coverage runs to '
                    .(optional($hostel->subscription_end)->format('d M Y') ?? 'its end date').'.',
                'danger',
            );
        }

        // Tell the owner where they stand, in their own feed.
        $this->notifications->push(
            $hostel->id,
            'branch_removal',
            'branch_removal:'.$hostel->id,
            'Branch removal confirmed',
            "{$hostel->name} will not be billed at your next renewal. It stays active until "
                .(optional($hostel->subscription_end)->format('d M Y') ?? 'its coverage ends').'.',
            'warning',
        );

        $closes = $impact['closes_account'] ? ' This was the last billable branch — the account is now closing.' : '';

        return back()->with('success',
            "{$hostel->name} cancelled. It stays active until "
            .(optional($hostel->subscription_end)->format('d M Y') ?? 'its coverage ends')
            .' and is excluded from the next renewal.'.$closes);
    }

    /**
     * A branch that genuinely belongs to this account, or a 404.
     *
     * Never trusts the posted id: it is matched against the branches the account's
     * owner actually holds, so a crafted id reaches nothing. A 404 rather than a 403
     * so the existence of another customer's branch is never confirmed.
     */
    private function branchOnAccount(SubscriptionAccount $account, int $branchId): Hostel
    {
        abort_unless(in_array($branchId, $account->owner?->accessibleHostelIds() ?? [], true), 404);

        return Hostel::findOr($branchId, fn () => abort(404));
    }

    /** Put a cancelled branch back into the billable set. */
    public function restoreBranch(SubscriptionAccount $account, Hostel $hostel): RedirectResponse
    {
        abort_unless(in_array($hostel->id, $account->owner?->accessibleHostelIds() ?? [], true), 404);

        $result = $this->billing->restoreBranch($hostel);

        if (! $result['restored']) {
            return back()->with('info', "{$hostel->name} is not cancelled.");
        }

        $this->logger->log('subscription.update', "Restored branch {$hostel->name} to the billing cycle", $hostel);
        $this->notifications->clear(null, 'account_closing', 'account_closing:'.$account->id);
        $this->notifications->clear($hostel->id, 'branch_removal', 'branch_removal:'.$hostel->id);

        // Restoring does not grant coverage — it only puts the branch back in the
        // quantity. If its coverage already lapsed the operator needs to charge it.
        return back()->with($result['coverageLapsed'] ? 'warning' : 'success',
            $result['coverageLapsed']
                ? "{$hostel->name} is back in the billing cycle, but its coverage has already lapsed — use \"Add to cycle\" to charge a prorated top-up and reactivate it."
                : "{$hostel->name} is back in the billing cycle.");
    }

    /** Decline an owner's removal request — and tell them, so the ask doesn't just vanish. */
    public function declineRemoval(Request $request, SubscriptionAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $hostel = $this->branchOnAccount($account, (int) $data['branch_id']);

        if (! $this->billing->clearRemovalRequest($hostel)) {
            return back()->with('info', 'There is no open removal request for that branch.');
        }

        $this->logger->log('subscription.update',
            "Declined removal request for {$hostel->name}".($data['note'] ? " — {$data['note']}" : ''), $hostel);

        $this->notifications->clear(null, 'branch_removal_request', 'branch_removal_request:'.$hostel->id);
        $this->notifications->push(
            $hostel->id,
            'branch_removal',
            'branch_removal_declined:'.$hostel->id,
            'Removal request closed',
            "Your request to remove {$hostel->name} has been closed by HostelEase support."
                .($data['note'] ? " Note: {$data['note']}" : ' Please get in touch if you still need it removed.'),
            'info',
        );

        return back()->with('success', 'Removal request closed and the owner notified.');
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
