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

        return view('admin.subscription.index', [
            'account' => $account,
            'branches' => $branches,
            'billableCount' => $billable->count(),
            'orders' => $account->orders()->where('payment_status', PaymentStatus::Paid->value)->latest('id')->limit(10)->get(),
            'quotes' => $quotes,
            'displayPeriod' => $displayPeriod,
            'yearlySaving' => $this->yearlySaving($quotes),
            'due' => $due->map(fn (SubscriptionOrder $o) => $this->dueRow($o))->values()->all(),
            'openRenewal' => $openRenewal ? $this->dueRow($openRenewal) : null,
            'addable' => $canManage ? $this->addableBranches($account, $branches) : [],
            // "Add & pay" only means something against a live PAID cycle; on a trial
            // account a new branch simply joins the plan at the first renewal.
            'canAddPaid' => $canManage
                && $account->period?->isPaid()
                && $account->current_period_end?->isFuture(),
            'selfServe' => $selfServe,
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
            'charge' => ['required', Rule::in(['renewal', 'add_branch', 'order'])],
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
        $branch = DB::transaction(fn () => $hostels->createBranchForOwner($account->owner, [
            'name' => $data['name'],
            'city' => $data['city'] ?? null,
        ]));

        $this->logger->log('branch.created', "Owner added branch {$branch->name} (inactive until paid)", $branch);

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
            'final' => round((float) $b['final'], 2),
            'new_anchor' => $q['new_anchor']->format('d M Y'),
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
        $payable = $o->wouldExtendCoverage();

        $linkUrl = $payable && $o->hasLiveLink() && str_starts_with((string) $o->payment_link_url, 'https://')
            ? $o->payment_link_url
            : null;

        return [
            'payable' => $payable,
            'id' => $o->id,
            'label' => $o->kind?->label() ?? 'Charge',
            'invoice' => $o->invoiceNumber(),
            'amount' => (float) $o->amount,
            'period' => $o->period?->label(),
            'quantity' => $o->quantity,
            'raised' => $o->created_at?->format('d M Y'),
            'link_url' => $linkUrl,
            'link_expires' => $linkUrl ? $o->payment_link_expires_at?->format('d M Y') : null,
        ];
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
                    'amount' => round((float) $q['breakdown']['final'], 2),
                    'days' => (int) $q['days_remaining'],
                ]];
            })
            ->filter(fn (array $q) => $q['amount'] >= 1)
            ->all();
    }
}
