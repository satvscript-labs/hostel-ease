<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\Subscription;
use App\Services\ActivityLogger;
use App\Services\Billing\AccountBillingService;
use App\Services\BranchBillingService;
use App\Services\RazorpayService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Illuminate\Http\RedirectResponse;

/**
 * Branch Manager & Subscription Controller.
 * Handles the "My Branches" hub, adding new branches, and per-branch subscriptions.
 */
class BranchManagerController extends Controller
{
    public function __construct(
        protected BranchBillingService $billing,
        protected AccountBillingService $accountBilling,
        protected RazorpayService $razorpay,
        protected ActivityLogger $logger,
        protected \App\Services\NotificationService $notifications,
    ) {
    }



    /**
     * Rename a branch (W9 — owners previously couldn't rename their own
     * branches anywhere). Deliberately name-and-address ONLY: status, owner,
     * billing and subscription fields belong to Super Admin, and this route
     * must never grow into a side door for them.
     */
    public function rename(Request $request, Hostel $hostel): RedirectResponse
    {
        // Only the ACCOUNT OWNER of this branch may rename it — not co-admins,
        // not staff, and never another account's owner.
        abort_unless($hostel->owner_id === $request->user()->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);

        $old = $hostel->name;
        $hostel->update($data);

        $this->logger->log('branch.rename', "Branch renamed — '{$old}' → '{$hostel->name}'", $hostel);

        return redirect()->route('admin.settings.index', ['tab' => 'branches'])
            ->with('success', 'Branch details updated.');
    }

    /**
     * The owner ASKS for a branch to be removed (D11). They cannot cancel it
     * themselves — removal changes what they are billed, and for a hands-on
     * business the request is the retention conversation. Nothing billing-related
     * changes here: the branch stays counted, charged and working until the Super
     * Admin confirms.
     *
     * Deliberately NOT behind the owner_self_serve lock: asking is not a billing
     * operation, and an owner must always be able to start the conversation.
     */
    public function requestRemoval(Request $request, Hostel $hostel): RedirectResponse
    {
        // The ACCOUNT OWNER only — not co-admins (who share the hostel_admin role),
        // not staff. Same rule as rename(): a 404 rather than a 403, so the
        // existence of another account's branch is never confirmed.
        abort_unless($hostel->owner_id === $request->user()->id, 404);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        if ($hostel->isCancelled()) {
            return back()->with('info', "{$hostel->name} is already scheduled for removal.");
        }

        if (! $this->accountBilling->requestRemoval($hostel, $data['reason'])) {
            return back()->with('info', 'We already have your request for this branch — our team will be in touch.');
        }

        $this->logger->log('branch.removal_requested', "Removal requested for {$hostel->name} — {$data['reason']}", $hostel);

        // Super Admin feed (hostel_id = null) so it lands on the operator's worklist.
        $this->notifications->push(
            null,
            'branch_removal_request',
            'branch_removal_request:'.$hostel->id,
            'Branch removal requested — '.$hostel->name,
            ($request->user()->name ?? 'The owner')." asked to remove {$hostel->name}. Reason: {$data['reason']}",
            'warning',
        );

        return back()->with('success', 'Request received — our team will contact you before anything changes. Nothing has been cancelled yet.');
    }

    /** The owner changes their mind. Leaves no billing trace. */
    public function withdrawRemoval(Request $request, Hostel $hostel): RedirectResponse
    {
        abort_unless($hostel->owner_id === $request->user()->id, 404);

        if (! $this->accountBilling->clearRemovalRequest($hostel)) {
            return back()->with('info', 'There is no open removal request for that branch.');
        }

        $this->logger->log('branch.removal_withdrawn', "Removal request withdrawn for {$hostel->name}", $hostel);
        $this->notifications->clear(null, 'branch_removal_request', 'branch_removal_request:'.$hostel->id);

        return back()->with('success', 'Request withdrawn — nothing changes.');
    }

    public function store(Request $request): RedirectResponse
    {
        // Production lock (P4 item 15): branch creation has billing consequences
        // (trial clock, account quantity, volume tiers) — supervised for now.
        if (! config('hostelease.owner_self_serve')) {
            return redirect()->route('admin.settings.index')->with('active_tab', 'branches')
                ->with('error', 'Adding branches is handled by HostelEase support right now — please contact us and we\'ll set it up for you.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
        ]);

        $owner = $request->user();

        // The invariant-keeping path (P4 item 14): sets owner_id, pivot access,
        // and a primary branch — the old inline Hostel::create() left all three
        // out. Then start the free-trial clock through the account spine.
        $branch = app(\App\Services\HostelService::class)->createBranchForOwner($owner, $data + ['plan' => 'trial']);
        $this->accountBilling->recordBranchRenewal($branch, 'trial', [
            'payment_status' => 'paid', 'payment_method' => null, 'remarks' => 'Owner self-serve branch (trial)',
        ]);

        $this->logger->log('branch.created', "New branch created: {$branch->name}");

        return redirect()->route('admin.settings.index')->with('active_tab', 'branches')->with('success', 'Branch created successfully! Your 14-day free trial has started.');
    }

    public function createOrder(Request $request): JsonResponse
    {
        if (! config('hostelease.owner_self_serve')) {
            return response()->json(['message' => 'Online renewals are handled by HostelEase support right now — please contact us to renew.'], 503);
        }

        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'period' => ['required', Rule::in(['yearly', 'monthly'])],
        ]);

        $owner = $request->user();
        
        if (! $owner->canAccessHostel((int) $data['branch_id'])) {
            return response()->json(['message' => 'Unauthorized branch access.'], 403);
        }

        $branch = Hostel::findOrFail($data['branch_id']);

        if (! $this->razorpay->isConfigured()) {
            return response()->json(['message' => 'Online payment is not available right now.'], 503);
        }

        $quote = $this->billing->quote($branch, $data['period']);

        if ($quote['amount_paise'] < 100) {
            return response()->json(['message' => 'There is nothing payable for this branch.'], 422);
        }

        try {
            $order = $this->razorpay->createOrder(
                $quote['amount_paise'],
                'hostelease_'.$branch->id.'_'.now()->timestamp,
                [
                    'branch_id' => (string) $branch->id,
                    'owner_id' => (string) $owner->id,
                    'period' => $data['period'],
                ],
            );
        } catch (RuntimeException $e) {
            $status = $e->getCode() === 401 ? 401 : 500;
            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json([
            'key' => $this->razorpay->keyId(),
            'order_id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'period' => $data['period'],
            'name' => config('app.name'),
            'description' => ucfirst($data['period']).' subscription for '.$branch->name,
            'prefill' => [
                'name' => $owner->name,
                'email' => $owner->email,
                'contact' => $owner->mobile,
            ],
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        if (! config('hostelease.owner_self_serve')) {
            return response()->json(['message' => 'Online renewals are handled by HostelEase support right now.'], 503);
        }

        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
            'period' => ['required', Rule::in(['yearly', 'monthly'])],
            'branch_id' => ['required', 'integer'],
        ]);

        $owner = $request->user();

        if (! $owner->canAccessHostel((int) $data['branch_id'])) {
            return response()->json(['message' => 'Unauthorized branch access.'], 403);
        }

        $branch = Hostel::findOrFail($data['branch_id']);

        if (! $this->razorpay->verifySignature($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'])) {
            return response()->json(['message' => 'Payment verification failed. You have not been charged.'], 400);
        }

        // Idempotency fast-path; the DB unique index on transaction_number is the real guard (below).
        // S1: the ledger is subscription_orders. Checking the legacy table here would
        // have made every retried callback look like a new payment.
        if (\App\Models\SubscriptionOrder::where('transaction_number', $data['razorpay_payment_id'])->exists()) {
            return response()->json([
                'message' => 'Payment already confirmed — branch subscription is active.',
                'redirect' => route('admin.settings.index').'?tab=branches',
            ]);
        }

        $quote = $this->billing->quote($branch, $data['period']);

        // Server-side amount verification: never trust the client for the captured amount.
        // Signature already proved authenticity; if the fetch fails we fall back to the quote.
        $amount = $quote['amount'];
        try {
            $payment = $this->razorpay->fetchPayment($data['razorpay_payment_id']);

            if ($payment['order_id'] && $payment['order_id'] !== $data['razorpay_order_id']) {
                Log::warning('Razorpay verify: payment/order mismatch', [
                    'payment' => $payment['id'], 'expected_order' => $data['razorpay_order_id'], 'payment_order' => $payment['order_id'],
                ]);

                return response()->json(['message' => 'Payment verification failed.'], 400);
            }

            if ($payment['amount'] !== $quote['amount_paise']) {
                Log::warning('Razorpay verify: captured amount differs from quote', [
                    'payment' => $payment['id'], 'expected_paise' => $quote['amount_paise'], 'captured_paise' => $payment['amount'],
                ]);
            }

            // Record what was actually captured.
            $amount = $payment['amount'] / 100;
        } catch (RuntimeException $e) {
            Log::warning('Razorpay verify: payment fetch failed, using quote amount', ['error' => $e->getMessage()]);
        }

        try {
            $order = $this->accountBilling->recordBranchRenewal($branch, $data['period'], [
                'amount' => $amount,
                'payment_status' => 'paid',
                'payment_method' => 'online',
                'transaction_number' => $data['razorpay_payment_id'],
                'razorpay_order_id' => $data['razorpay_order_id'],
                'remarks' => 'Razorpay · Branch: '.$branch->name,
            ]);

            $this->logger->log(
                'subscription.paid',
                "Online {$data['period']} renewal for {$branch->name} — ".hostelease_money($amount),
                $order,
            );
        } catch (QueryException $e) {
            // A concurrent delivery (the server-side webhook) already recorded this payment id.
            // The unique index turned the race into a clean no-op instead of a double-grant.
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
        }

        return response()->json([
            'message' => 'Payment successful — branch subscription activated.',
            'redirect' => route('admin.settings.index') . '?tab=branches',
        ]);
    }
}
