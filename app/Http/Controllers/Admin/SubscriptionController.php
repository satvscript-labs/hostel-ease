<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\SubscriptionOrder;
use App\Services\ActivityLogger;
use App\Services\Billing\AccountBillingService;
use App\Services\RazorpayService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Owner self-serve consolidated billing (Phase 6). The account-level, one-payment
 * counterpart to the operator's Account 360 — renew every branch on one date in a
 * single Razorpay payment, or add a branch mid-cycle (prorated).
 *
 * Lives outside the subscription.active gate (see routes/web.php) so an expired
 * owner can still reach it to pay.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        protected AccountBillingService $accountBilling,
        protected RazorpayService $razorpay,
        protected ActivityLogger $logger,
    ) {
    }

    /** The owner's Subscription page — status, branches, renew-all quote, history. */
    public function index(Request $request): View
    {
        $owner = $request->user();
        $account = $this->accountBilling->accountFor($owner);

        // Keep the account's anchor/status in sync with its branches on load.
        $this->accountBilling->refreshAccountAnchor($account);
        $account->refresh();

        $branches = $this->accountBilling->includedBranches($account);
        $orders = $account->orders()->latest()->limit(10)->get();

        // JS-friendly quotes for both terms so the renew modal shows an accurate,
        // discount-aware breakdown as the owner toggles Yearly/Monthly.
        $quotes = [
            'yearly' => $this->quoteArray($account, 'yearly'),
            'monthly' => $this->quoteArray($account, 'monthly'),
        ];
        $displayPeriod = $account->period?->isPaid() ? $account->period->value : 'yearly';

        $addQuote = $this->accountBilling->quoteAddBranch($account);

        return view('admin.subscription.index', [
            'account' => $account,
            'branches' => $branches,
            'orders' => $orders,
            'quotes' => $quotes,
            'displayPeriod' => $displayPeriod,
            'addBranch' => [
                'prorated' => (float) $addQuote['breakdown']['final'],
                'days' => (int) $addQuote['days_remaining'],
                'anchor' => $account->current_period_end?->format('d M Y'),
            ],
            'razorpayEnabled' => $this->razorpay->isConfigured(),
            // Production lock (P4 item 15): while false, owners see everything
            // but every mutating billing op is supervised via the Super Admin.
            'selfServe' => (bool) config('hostelease.owner_self_serve'),
        ]);
    }

    /** Flatten a renewal quote into a JS/Blade-friendly shape. */
    protected function quoteArray(\App\Models\SubscriptionAccount $account, string $period): array
    {
        $q = $this->accountBilling->quoteRenewal($account, $period);

        return [
            'quantity' => $q['quantity'],
            'unit' => (float) $q['unit'],
            'subtotal' => (float) $q['subtotal'],
            'discount' => (float) $q['breakdown']['discount_total'],
            'final' => (float) $q['breakdown']['final'],
            'new_anchor' => $q['new_anchor']->format('d M Y'),
        ];
    }

    /** Create a Razorpay order for a consolidated account renewal (all branches). */
    public function renewOrder(Request $request): JsonResponse
    {
        if (! config('hostelease.owner_self_serve')) {
            return response()->json(['message' => 'Online renewals are handled by HostelEase support right now — please contact us to renew.'], 503);
        }

        $data = $request->validate(['period' => ['required', Rule::in(['yearly', 'monthly'])]]);

        if (! $this->razorpay->isConfigured()) {
            return response()->json(['message' => 'Online payment is not available right now.'], 503);
        }

        $owner = $request->user();
        $account = $this->accountBilling->accountFor($owner);
        $quote = $this->accountBilling->quoteRenewal($account, $data['period']);

        $paise = (int) round($quote['breakdown']['final'] * 100);
        if ($paise < 100) {
            return response()->json(['message' => 'There is nothing payable on your account.'], 422);
        }

        try {
            $order = $this->razorpay->createOrder(
                $paise,
                'he_acct_'.$account->id.'_'.now()->timestamp,
                ['account_id' => (string) $account->id, 'type' => 'renew_account', 'period' => $data['period']],
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() === 401 ? 401 : 500);
        }

        return response()->json([
            'key' => $this->razorpay->keyId(),
            'order_id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'type' => 'renew_account',
            'period' => $data['period'],
            'name' => config('app.name'),
            'description' => ucfirst($data['period']).' renewal · '.$quote['quantity'].' branch(es)',
            'prefill' => ['name' => $owner->name, 'email' => $owner->email, 'contact' => $owner->mobile],
        ]);
    }

    /**
     * Create a branch and (optionally) a Razorpay order to co-terminate it onto
     * the account's renewal date with a prorated charge. If the account has no
     * live anchor, this is priced as a fresh single-branch term.
     *
     * The branch is created first (on a trial window) so that if the owner
     * abandons the payment they still have a working trial branch — never a dead end.
     */
    public function addBranchOrder(Request $request): JsonResponse
    {
        if (! config('hostelease.owner_self_serve')) {
            return response()->json(['message' => 'Adding branches is handled by HostelEase support right now — please contact us and we\'ll set it up for you.'], 503);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);

        if (! $this->razorpay->isConfigured()) {
            return response()->json(['message' => 'Online payment is not available right now.'], 503);
        }

        $owner = $request->user();
        $account = $this->accountBilling->accountFor($owner);

        // Invariant-keeping creation (P4 item 14): owner_id + pivot + primary
        // branch — the old inline Hostel::create() set none of them. Trial plan
        // keeps its 14-day window until the payment lands.
        $branch = app(\App\Services\HostelService::class)->createBranchForOwner($owner, $data + ['plan' => 'trial']);
        $this->logger->log('branch.created', "New branch created: {$branch->name}");

        $quote = $this->accountBilling->quoteAddBranch($account);
        $paise = (int) round($quote['breakdown']['final'] * 100);

        // Nothing meaningful to charge (e.g. no live anchor) — leave the branch on trial.
        if ($paise < 100) {
            return response()->json(['trial_only' => true, 'redirect' => route('admin.subscription.index'), 'message' => 'Branch created on a free trial.']);
        }

        try {
            $order = $this->razorpay->createOrder(
                $paise,
                'he_add_'.$branch->id.'_'.now()->timestamp,
                ['account_id' => (string) $account->id, 'branch_id' => (string) $branch->id, 'type' => 'add_branch', 'period' => $account->period?->isPaid() ? $account->period->value : 'yearly'],
            );
        } catch (RuntimeException $e) {
            // Branch already exists on trial; surface the failure but don't lose it.
            return response()->json(['message' => 'Branch created on a trial, but online payment could not start: '.$e->getMessage(), 'redirect' => route('admin.subscription.index')], 200);
        }

        return response()->json([
            'key' => $this->razorpay->keyId(),
            'order_id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'type' => 'add_branch',
            'branch_id' => $branch->id,
            'period' => $account->period?->isPaid() ? $account->period->value : 'yearly',
            'name' => config('app.name'),
            'description' => 'Add branch · '.$branch->name.' (prorated)',
            'prefill' => ['name' => $owner->name, 'email' => $owner->email, 'contact' => $owner->mobile],
        ]);
    }

    /**
     * Verify a completed Razorpay payment and apply it. Authoritative confirmation
     * is the webhook; this is the browser-callback UX path. Idempotent + amount-verified.
     */
    public function verify(Request $request): JsonResponse
    {
        if (! config('hostelease.owner_self_serve')) {
            return response()->json(['message' => 'Online renewals are handled by HostelEase support right now.'], 503);
        }

        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
            'type' => ['required', Rule::in(['renew_account', 'add_branch'])],
            'period' => ['required', Rule::in(['yearly', 'monthly'])],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $owner = $request->user();
        $account = $this->accountBilling->accountFor($owner);

        if (! $this->razorpay->verifySignature($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'])) {
            return response()->json(['message' => 'Payment verification failed. You have not been charged.'], 400);
        }

        // Idempotency fast-path; the unique index on subscription_orders.transaction_number is the real guard.
        if (SubscriptionOrder::where('transaction_number', $data['razorpay_payment_id'])->exists()) {
            return response()->json(['message' => 'Payment already confirmed.', 'redirect' => route('admin.subscription.index')]);
        }

        // Resolve the expected amount for this charge type.
        if ($data['type'] === 'add_branch') {
            $branch = $data['branch_id'] ? Hostel::find($data['branch_id']) : null;
            if (! $branch || ! $owner->canAccessHostel($branch->id)) {
                return response()->json(['message' => 'Unauthorized branch.'], 403);
            }
            $expectedPaise = (int) round($this->accountBilling->quoteAddBranch($account)['breakdown']['final'] * 100);
        } else {
            $branch = null;
            $expectedPaise = (int) round($this->accountBilling->quoteRenewal($account, $data['period'])['breakdown']['final'] * 100);
        }

        // Server-side amount verification (never trust the client for the captured amount).
        $amount = $expectedPaise / 100;
        try {
            $payment = $this->razorpay->fetchPayment($data['razorpay_payment_id']);
            if ($payment['order_id'] && $payment['order_id'] !== $data['razorpay_order_id']) {
                Log::warning('Subscription verify: payment/order mismatch', ['payment' => $payment['id'], 'expected_order' => $data['razorpay_order_id']]);

                return response()->json(['message' => 'Payment verification failed.'], 400);
            }
            if ($payment['amount'] !== $expectedPaise) {
                Log::warning('Subscription verify: captured amount differs from quote', ['payment' => $payment['id'], 'expected_paise' => $expectedPaise, 'captured_paise' => $payment['amount']]);
            }
            $amount = $payment['amount'] / 100;
        } catch (RuntimeException $e) {
            Log::warning('Subscription verify: payment fetch failed, using quote amount', ['error' => $e->getMessage()]);
        }

        $payload = [
            'amount' => $amount,
            'payment_status' => 'paid',
            'payment_method' => 'online',
            'transaction_number' => $data['razorpay_payment_id'],
            'razorpay_order_id' => $data['razorpay_order_id'],
            'remarks' => 'Razorpay (self-serve)',
        ];

        try {
            if ($data['type'] === 'add_branch') {
                $order = $this->accountBilling->addBranch($account, $branch, $payload);
                $this->logger->log('subscription.paid', "Self-serve add branch {$branch->name} — ".hostelease_money($amount), $order);
            } else {
                $order = $this->accountBilling->renewAccount($account, $data['period'], $payload);
                $this->logger->log('subscription.paid', "Self-serve {$data['period']} renewal ({$order->quantity} branches) — ".hostelease_money($amount), $order);
            }
        } catch (QueryException $e) {
            // Concurrent webhook delivery already recorded this payment id — clean no-op.
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
        }

        return response()->json(['message' => 'Payment successful — your subscription is updated.', 'redirect' => route('admin.subscription.index')]);
    }
}
