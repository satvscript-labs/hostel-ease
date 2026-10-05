<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Services\ActivityLogger;
use App\Services\Billing\AccountBillingService;
use App\Services\Billing\CheckoutService;
use App\Services\HostelService;
use App\Support\Refusal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * The owner's billing page — the second front door onto the same billing core the
 * operator's Account 360 uses (S3 · design 14).
 *
 * Rebuilt in S3. The Phase 6 version charged a quote and then rebuilt the order from
 * a fresh quote when the money arrived, trusted the browser to say what a payment
 * had bought, and refused to confirm money already taken whenever the kill switch was
 * off. All of that is gone: charges are pending orders written when the price is
 * shown, and payments settle through the same PaymentSettlement as payment links.
 *
 * WHO MAY DO WHAT (design §4):
 *   · anyone on the account sees the page;
 *   · only the ACCOUNT OWNER starts a charge — gated on the owner FK, never the role,
 *     because a co-admin IS a hostel_admin;
 *   · starting a charge needs SubscriptionAccount::selfServeEnabled() — the
 *     platform kill switch (`owner_self_serve`) ON *and* this customer self-serve
 *     rather than managed by HostelEase (BillingMode, set on Account 360);
 *   · confirming a payment needs none of that: it settles money already taken,
 *     so a checkout in flight when the account is switched to managed still lands.
 *
 * Lives outside the subscription.active gate (routes/web.php) so an expired owner can
 * still reach it to pay.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        protected AccountBillingService $billing,
        protected CheckoutService $checkout,
        protected ActivityLogger $logger,
    ) {}

    /** The owner's billing page. Reads only — no write on a GET (S1 · F10). */
    public function index(Request $request): View
    {
        // The viewer is not necessarily the owner — a co-admin shares the role.
        // accountForViewer() resolves the branches' real owner instead of minting a
        // phantom account for whoever opened the page (CoAdminBillingTest).
        $viewer = $request->user();
        $account = $this->billing->accountForViewer($viewer);
        $viewerOwnsAccount = $account->owner_id === $viewer->id;

        $branches = $this->billing->allBranches($account);
        $billable = $this->billing->includedBranches($account);

        // Both terms, priced by the SAME function Account 360 uses — so the owner
        // sees their own negotiated price and discounts, and the two surfaces cannot
        // disagree (design §7).
        $quotes = [
            'yearly' => $this->quoteArray($account, 'yearly'),
            'monthly' => $this->quoteArray($account, 'monthly'),
        ];
        $displayPeriod = $account->period?->isPaid() ? $account->period->value : 'yearly';

        // Charges already open on the account — whoever opened them. The operator's
        // payment links are payable here even with the kill switch off: a link is
        // the operator's instrument, not self-serve (design §4).
        // lines.branch eager-loaded: each row asks wouldExtendCoverage(), and that
        // must not become a query per row.
        $due = $account->orders()->outstanding()->with('lines.branch')->latest('id')->get();

        // The hero's "Pay" points at a renewal that would still BUY something. An
        // overtaken one is listed below as already covered, never offered (S3 audit).
        $openRenewal = $due->first(fn (SubscriptionOrder $o) => $o->kind?->value === 'renewal' && $o->wouldExtendCoverage());

        // Self-serve for THIS customer: platform switch on AND not managed by us.
        // When false the page reads "Managed by HostelEase" — the same words whether
        // the platform switch is off or this account is managed, on purpose: an owner
        // has no reason to know which, and every reason not to ask for the other.
        $selfServe = $account->selfServeEnabled();
        $canManage = $viewerOwnsAccount && $selfServe && $this->checkout->isEnabled();

        $addable = $canManage ? $this->addableBranches($account, $branches) : [];

        // "Bring all up to date" — offered when two or more branches are behind, and
        // not while an align is already billed (that one is in Payment due).
        $alignDue = collect($due)->contains(fn (SubscriptionOrder $o) => $o->kind?->value === 'align' && $o->wouldExtendCoverage());
        $alignOffer = $canManage && count($addable) >= 2 && ! $alignDue ? $this->alignOffer($account) : null;

        return view('admin.subscription.index', [
            'account' => $account,
            'branches' => $branches,
            'billableCount' => $billable->count(),
            'history' => $this->history($account),
            'alignOffer' => $alignOffer,
            'newBranch' => $canManage ? $this->newBranchQuote($account) : null,
            'quotes' => $quotes,
            'displayPeriod' => $displayPeriod,
            'yearlySaving' => $this->yearlySaving($quotes),
            'due' => $due->map(fn (SubscriptionOrder $o) => $this->dueRow($o))->values()->all(),
            'openRenewal' => $openRenewal ? $this->dueRow($openRenewal) : null,
            'addable' => $addable,
            // "Add & pay" only means something against a live PAID cycle; on a trial
            // account a new branch simply joins the plan at the first renewal.
            'canAddPaid' => $canManage
                && $account->period?->isPaid()
                && $account->current_period_end?->isFuture(),
            'selfServe' => $selfServe,
            'trialJoinable' => $this->billing->trialJoinable($account),
            'razorpayEnabled' => $this->checkout->isEnabled(),
            'canManage' => $canManage,
            'viewerOwnsAccount' => $viewerOwnsAccount,
        ]);
    }

    /**
     * Start paying for something. Returns either a payment link to open (when the
     * operator has already sent one for this charge), or Razorpay Checkout options
     * for a pending order that now exists for it.
     *
     * The browser posts a charge SHAPE, never an amount — the brief from the S2 audit.
     */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'charge' => ['required', Rule::in(['renewal', 'add_branch', 'align', 'order'])],
            'period' => ['required_if:charge,renewal', 'nullable', Rule::in(['yearly', 'monthly'])],
            // Posted DB references, so integers (standards §1.1 rule 3), resolved
            // inside this account below — never trusted as they arrive.
            'branch_id' => ['required_if:charge,add_branch', 'nullable', 'integer'],
            'order_id' => ['required_if:charge,order', 'nullable', 'integer'],
        ]);

        [$account, $refusal] = $this->managedAccount($request);
        if ($refusal) {
            return $refusal;
        }

        // Resolve posted ids OUTSIDE the try. abort(404) throws NotFoundHttpException,
        // which extends \RuntimeException — inside the try below, the catch would
        // swallow an authorization refusal and answer 422 with an empty message.
        $branch = $data['charge'] === 'add_branch' ? $this->branchOnAccount($account, (int) $data['branch_id']) : null;
        $order = $data['charge'] === 'order' ? $this->orderOnAccount($account, (int) $data['order_id']) : null;

        try {
            $result = match ($data['charge']) {
                'renewal' => $this->checkout->startRenewal($account, $data['period']),
                'add_branch' => $this->checkout->startAddBranch($account, $branch),
                'align' => $this->checkout->startAlign($account),
                'order' => $this->checkout->startForOrder($account, $order),
            };
        } catch (RuntimeException $e) {
            return response()->json(['message' => Refusal::message($e)], 422);
        }

        return response()->json($this->present($result));
    }

    /**
     * Add a new branch. It ALWAYS starts on a 14-day trial, so an abandoned or failed
     * payment still leaves a working branch — never a dead end (05 §3). With
     * `pay_now`, checkout for bringing it onto the plan is opened straight after.
     *
     * Replaces two Phase 6 doors (Settings' trial-only form, and this page's
     * create-and-charge endpoint) that gave the same action different outcomes.
     */
    public function addBranch(Request $request, HostelService $hostels): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'pay_now' => ['nullable', 'boolean'],
        ]);

        [$account, $refusal] = $this->managedAccount($request);
        if ($refusal) {
            return $refusal;
        }

        // Created for the ACCOUNT OWNER, never for whoever is signed in. The Phase 6
        // code used $request->user(): a co-admin's branch came out owned by the
        // co-admin, which then minted a phantom account for them (design §1 P4).
        // managedAccount() already restricts this to the owner — this makes the
        // ownership correct even if that gate ever loosens.
        //
        // NO TRIAL (owner decision, 2026-10-04): the free trial belongs to the
        // ACCOUNT, once — to its first branch. A branch added later starts with no
        // coverage and is activated by paying for it: prorated onto the renewal date
        // on a live paid plan, or as part of the plan when the owner subscribes or
        // renews. recordBranchRenewal() refuses a second trial anyway; this path
        // simply never asks for one.
        // WHILE THE TRIAL RUNS a new branch joins it (owner decision, 2026-10-05):
        // it works free until the trial ends and is billed with everything else when
        // the owner subscribes. Outside a trial it starts with no coverage and is
        // activated by paying for it.
        $joinsTrial = $this->billing->trialJoinable($account);

        $branch = DB::transaction(function () use ($hostels, $account, $data, $joinsTrial) {
            $branch = $hostels->createBranchForOwner($account->owner, [
                'name' => $data['name'],
                'city' => $data['city'] ?? null,
            ]);

            if ($joinsTrial) {
                $this->billing->recordBranchRenewal($branch, 'trial', ['payment_status' => 'paid']);
            }

            return $branch;
        });

        $this->logger->log('branch.created', $joinsTrial
            ? "Owner added branch {$branch->name} (joined the free trial)"
            : "Owner added branch {$branch->name} (inactive until paid)", $branch);

        if ($joinsTrial) {
            return response()->json([
                'mode' => 'created',
                'message' => "{$branch->name} has been added to your free trial — it works until ".$account->current_period_end->format('d M Y').'. Every branch is billed together when you subscribe.',
                'redirect' => route('admin.subscription.index'),
            ]);
        }

        $onPaidPlan = $account->period?->isPaid() && $account->current_period_end?->isFuture();

        $created = $onPaidPlan
            ? "{$branch->name} has been added. It becomes active as soon as it is paid for."
            : "{$branch->name} has been added. It becomes active when you subscribe — it is included in your plan from your first payment.";

        if (! ($data['pay_now'] ?? false) || ! $onPaidPlan) {
            return response()->json(['mode' => 'created', 'message' => $created, 'redirect' => route('admin.subscription.index')]);
        }

        // Committed above, so whatever happens here the branch survives — and can be
        // paid for later from its "Add to plan" button.
        try {
            $result = $this->checkout->startAddBranch($account->fresh(), $branch->fresh());
        } catch (RuntimeException $e) {
            return response()->json([
                'mode' => 'created',
                'message' => $created.' '.Refusal::message($e),
                'redirect' => route('admin.subscription.index'),
            ]);
        }

        return response()->json($this->present($result) + ['created' => $created]);
    }

    /**
     * The Razorpay Checkout callback. ONLY Razorpay's three ids are accepted: which
     * charge this paid for is looked up from the Razorpay order id inside this
     * account, and the amount is read back from Razorpay. The Phase 6 version took
     * `type`, `period` and `branch_id` from the browser and let them choose how the
     * payment was priced (design §1 P7).
     *
     * NOT behind the kill switch and not owner-only: it never starts a charge, it
     * settles one already paid. Refusing it told paying customers their payment had
     * failed (design §1 P5).
     */
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string', 'max:64'],
            'razorpay_payment_id' => ['required', 'string', 'max:64'],
            'razorpay_signature' => ['required', 'string', 'max:255'],
        ]);

        $account = $this->billing->accountForViewer($request->user());

        try {
            $result = $this->checkout->confirm(
                $account,
                $data['razorpay_order_id'],
                $data['razorpay_payment_id'],
                $data['razorpay_signature'],
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => Refusal::message($e)], 422);
        }

        return response()->json($result + ['redirect' => route('admin.subscription.index')]);
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /**
     * The account, if this viewer may start a charge on it — else the refusal.
     *
     * @return array{0: SubscriptionAccount, 1: ?JsonResponse}
     */
    protected function managedAccount(Request $request): array
    {
        $viewer = $request->user();
        $account = $this->billing->accountForViewer($viewer);

        if (! config('hostelease.owner_self_serve')) {
            return [$account, response()->json([
                'message' => 'Online billing is handled by HostelEase support right now — please contact us and we will set it up for you.',
            ], 503)];
        }

        // This customer's billing is handled by HostelEase (Account 360). The page
        // hides every button that leads here; this is the server saying the same to a
        // stale tab or a crafted request.
        if ($account->isManaged()) {
            return [$account, response()->json([
                'message' => 'Billing on your account is handled by the HostelEase team — please contact us and we will set it up for you.',
            ], 403)];
        }

        if ($account->owner_id !== $viewer->id) {
            return [$account, response()->json([
                'message' => 'Only the account owner can make payments or add branches. Please ask them to do it from their login.',
            ], 403)];
        }

        // An operator hold covers EVERYTHING this page can start - adding a branch
        // included, which used to slip through because only the payment path checked
        // (S3 audit). CheckoutService refuses too; this stops it before a branch is
        // created on an account the operator has frozen.
        if ($account->status === \App\Enums\AccountStatus::Suspended) {
            return [$account, response()->json([
                'message' => 'Your account is on hold, so changes and online payments are paused. Please contact us and we will sort it out with you.',
            ], 423)];
        }

        return [$account, null];
    }

    /** A branch this account holds — a crafted id from elsewhere is a 404. */
    protected function branchOnAccount(SubscriptionAccount $account, int $branchId): Hostel
    {
        abort_unless(in_array($branchId, $account->owner?->accessibleHostelIds() ?? [], true), 404);

        return Hostel::findOrFail($branchId);
    }

    protected function orderOnAccount(SubscriptionAccount $account, int $orderId): SubscriptionOrder
    {
        return $account->orders()->whereKey($orderId)->firstOr(fn () => abort(404));
    }

    /** What the browser needs, and nothing it should not see. */
    protected function present(array $result): array
    {
        return match ($result['mode']) {
            'link' => [
                'mode' => 'link',
                'url' => $result['url'],
                'message' => 'We have already sent you a secure payment link for this — opening it now.',
            ],
            'paid', 'held' => [
                'mode' => $result['mode'],
                'message' => $result['message'],
                'redirect' => route('admin.subscription.index'),
            ],
            default => [
                'mode' => 'checkout',
                'razorpay' => $result['razorpay'],
            ],
        };
    }

    /**
     * A renewal quote flattened for the page. WHITELISTED fields only: the discount
     * engine's breakdown also carries the negotiated discount's id, and the reason
     * behind a negotiated price is internal — it never leaves the server (design §7).
     */
    protected function quoteArray(SubscriptionAccount $account, string $period): array
    {
        $q = $this->billing->quoteRenewal($account, $period);
        $b = $q['breakdown'];

        return [
            'quantity' => (int) $q['quantity'],
            'unit' => (float) $q['unit'],
            'subtotal' => round((float) $q['subtotal'], 2),
            'volume' => round((float) ($b['volume_amount'] ?? 0), 2),
            'manual' => round((float) ($b['manual_amount'] ?? 0), 2),
            'discount' => round((float) ($b['discount_total'] ?? 0), 2),
            // What the owner pays: the term plus any top-ups below.
            'final' => round((float) $q['total'], 2),
            'topups' => collect($q['topups'])->map(fn (array $t) => [
                'name' => $t['branch']->name,
                'days' => $t['days'],
                'amount' => round((float) $t['amount'], 2),
            ])->values()->all(),
            'complimentary' => collect($q['complimentary'])->map(fn (array $c) => [
                'name' => $c['branch']->name,
                'amount' => round((float) $c['amount'], 2),
            ])->values()->all(),
            'current_anchor' => $q['current_anchor']?->format('d M Y'),
            'new_anchor' => $q['new_anchor']->format('d M Y'),
            // Which branches this renews, and which are left out because they are
            // closing — so the owner never has to guess what they are paying for.
            'included' => $this->billing->includedBranches($account)->pluck('name')->values()->all(),
            'closing' => $this->billing->allBranches($account)->filter(fn (Hostel $b) => $b->isCancelled())->pluck('name')->values()->all(),
        ];
    }

    /**
     * The real saving of yearly over twelve monthly payments, from THIS account's
     * prices. Replaces a hard-coded "Save 16%" that was wrong for anyone on a custom
     * rate (design §1 P9). Null when there is no saving to claim.
     */
    protected function yearlySaving(array $quotes): ?int
    {
        $yearly = $quotes['yearly']['unit'];
        $twelveMonths = $quotes['monthly']['unit'] * 12;

        if ($twelveMonths <= 0 || $yearly >= $twelveMonths) {
            return null;
        }

        return (int) floor((1 - $yearly / $twelveMonths) * 100);
    }

    protected function dueRow(SubscriptionOrder $o): array
    {
        // The link URL comes from Razorpay's API, not from us, and lands in an href
        // on a customer's page. Only an https URL is ever rendered — a hostile or
        // malformed value (`javascript:` …) is dropped, and the row falls back to
        // checkout or "contact us" rather than to a dangerous link.
        // Would paying it still buy anything? An overtaken charge is shown as
        // already covered, with no way to pay it - not its link, not checkout.
        $state = $o->coverageState();
        $payable = $state === 'extends';

        $linkUrl = $payable && $o->hasLiveLink() && str_starts_with((string) $o->payment_link_url, 'https://')
            ? $o->payment_link_url
            : null;

        return [
            'payable' => $payable,
            // Part of it was paid separately since: not "already covered" — the rest
            // is still owed, at a new amount.
            'stale' => $state === 'stale',
            // The owner's OWN checkout attempt (no link, not the operator's): they may
            // abandon it and choose the other term — startRenewal supersedes it. An
            // operator's charge is theirs to change, so it never offers this.
            'own' => $o->kind?->value === 'renewal' && $o->collection === \App\Enums\CollectionMethod::Checkout && ! $o->hasLiveLink(),
            'id' => $o->id,
            'label' => $o->kind?->label() ?? 'Charge',
            'invoice' => $o->invoiceNumber(),
            'amount' => (float) $o->amount,
            'period' => $o->period?->label(),
            'quantity' => $o->quantity,
            'raised' => $o->created_at?->format('d M Y'),
            'link_url' => $linkUrl,
            'link_expires' => $linkUrl ? $o->payment_link_expires_at?->format('d M Y') : null,
            // The breakdown shown before paying: what each line buys. Display only —
            // what is charged is the order's own amount, read on the server.
            'kind' => $o->kind?->value,
            'subtotal' => (float) $o->subtotal,
            'discount' => (float) $o->discount_total,
            'lines' => $o->lines->map(fn ($l) => [
                'name' => $l->branch?->name ?? __('Branch'),
                'from' => $l->start_date?->format('d M Y'),
                'to' => $l->end_date?->format('d M Y'),
                'amount' => (float) $l->amount,
                'free' => (bool) $l->complimentary,
            ])->values()->all(),
        ];
    }

    /** What "Bring all up to date" costs — Align's own quote, line by line. */
    protected function alignOffer(SubscriptionAccount $account): ?array
    {
        $q = $this->billing->quoteAlign($account);
        if ($q['count'] < 2) {
            return null;
        }

        return [
            'count' => $q['count'],
            'total' => round((float) $q['subtotal'], 2),
            'anchor' => $q['anchor']?->format('d M Y'),
            'lines' => collect($q['lines'])->map(fn (array $l) => [
                'name' => $l['branch']->name,
                'days' => (int) $l['days'],
                'amount' => round((float) $l['amount'], 2),
            ])->values()->all(),
        ];
    }

    /**
     * What adding a branch would mean, shown BEFORE it is added:
     *   trial     — it joins the running trial, free until it ends;
     *   prorate   — on a live paid plan: this much now, to the renewal date;
     *   subscribe — no live paid plan: it joins the plan at the next payment.
     */
    protected function newBranchQuote(SubscriptionAccount $account): array
    {
        if ($this->billing->trialJoinable($account)) {
            return ['mode' => 'trial', 'until' => $account->current_period_end->format('d M Y')];
        }

        if ($account->period?->isPaid() && $account->current_period_end?->isFuture()) {
            $q = $this->billing->quoteAddBranch($account, null);

            return [
                'mode' => 'prorate',
                'days' => (int) $q['days_remaining'],
                'unit' => round((float) $q['unit'], 2),
                'prorated' => round((float) $q['prorated'], 2),
                'volume' => round((float) ($q['breakdown']['volume_amount'] ?? 0), 2),
                'manual' => round((float) ($q['breakdown']['manual_amount'] ?? 0), 2),
                'final' => round((float) $q['breakdown']['final'], 2),
                'anchor' => $q['anchor']?->format('d M Y'),
                'term' => $account->period->label(),
            ];
        }

        return [
            'mode' => 'subscribe',
            'yearly' => round($this->billing->unitPrice($account, \App\Enums\BillingPeriod::Yearly), 2),
            'monthly' => round($this->billing->unitPrice($account, \App\Enums\BillingPeriod::Monthly), 2),
        ];
    }

    /**
     * Payments & receipts. Money paid, plus the free grants as "Free" — never the
     * internal ₹0 adjustments the ledger keeps for itself.
     */
    protected function history(SubscriptionAccount $account): array
    {
        return $account->orders()
            ->where('payment_status', PaymentStatus::Paid->value)
            ->where(fn ($q) => $q->whereNull('kind')->orWhere('kind', '!=', \App\Enums\OrderKind::Adjustment->value))
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(fn (SubscriptionOrder $o) => [
                'label' => $o->kind?->label() ?? __('Payment'),
                'amount' => (float) $o->amount,
                'free' => (float) $o->amount <= 0,
                'branches' => $o->quantity,
                'period' => $o->period?->isPaid() ? $o->period->label() : null,
                'date' => $o->created_at?->format('d M Y'),
                'invoice' => $o->invoiceNumber(),
                'receipt' => (float) $o->amount > 0 ? route('admin.subscription.receipt', $o) : null,
            ])
            ->all();
    }

    /**
     * Download the receipt for a payment on THIS account. Paid, money-bearing orders
     * only — a crafted id from another account, a pending charge or a ₹0 grant is a 404.
     */
    public function receipt(Request $request, SubscriptionOrder $order)
    {
        $account = $this->billing->accountForViewer($request->user());

        abort_unless(
            $order->account_id === $account->id
                && $order->payment_status === PaymentStatus::Paid
                && (float) $order->amount > 0,
            404,
        );

        $order->load(['lines.branch', 'account.owner']);

        $this->logger->log('order.invoice', 'Owner downloaded receipt '.$order->invoiceNumber(), $order);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('superadmin.orders.invoice_pdf', [
            'order' => $order,
            'account' => $account,
            'company' => config('hostelease.company'),
        ])->download($order->invoiceNumber().'.pdf');
    }

    /**
     * Branches the owner can bring onto the renewal date now — behind a live, PAID
     * cycle — with what that costs, from the same quote the operator sees.
     */
    protected function addableBranches(SubscriptionAccount $account, $branches): array
    {
        $anchor = $account->current_period_end;
        if (! $account->period?->isPaid() || ! $anchor || ! $anchor->isFuture()) {
            return [];
        }

        return $branches
            ->filter(fn (Hostel $b) => ! $b->isCancelled() && (! $b->subscription_end || $b->subscription_end->lt($anchor)))
            ->mapWithKeys(function (Hostel $b) use ($account) {
                $q = $this->billing->quoteAddBranch($account, $b);

                return [$b->id => [
                    'name' => $b->name,
                    'amount' => round((float) $q['breakdown']['final'], 2),
                    'days' => (int) $q['days_remaining'],
                    'unit' => round((float) $q['unit'], 2),
                    'prorated' => round((float) $q['prorated'], 2),
                    'volume' => round((float) ($q['breakdown']['volume_amount'] ?? 0), 2),
                    'manual' => round((float) ($q['breakdown']['manual_amount'] ?? 0), 2),
                    'anchor' => $q['anchor']?->format('d M Y'),
                ]];
            })
            ->filter(fn (array $q) => $q['amount'] >= 1)
            ->all();
    }
}
