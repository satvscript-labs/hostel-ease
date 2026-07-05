<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\ActivityLogger;
use App\Services\RazorpayService;
use App\Services\SubscriptionBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Hostel-admin self-service billing: shows the account-wide renewal quote and
 * drives Razorpay Standard Checkout. One payment renews every branch together.
 */
class BillingController extends Controller
{
    public function __construct(
        protected SubscriptionBillingService $billing,
        protected RazorpayService $razorpay,
        protected ActivityLogger $logger,
    ) {
    }

    public function show(Request $request): View
    {
        $owner = $request->user();

        return view('admin.billing.index', [
            'owner' => $owner,
            'yearly' => $this->billing->quote($owner, 'yearly'),
            'monthly' => $this->billing->quote($owner, 'monthly'),
            'currentEnd' => $this->billing->currentEnd($owner),
            'razorpayEnabled' => $this->razorpay->isConfigured(),
        ]);
    }

    /** Create a Razorpay order for the selected period. Amount is server-computed. */
    public function createOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', Rule::in(['yearly', 'monthly'])],
        ]);

        if (! $this->razorpay->isConfigured()) {
            return response()->json(['message' => 'Online payment is not available right now.'], 503);
        }

        $owner = $request->user();
        $quote = $this->billing->quote($owner, $data['period']);

        if ($quote['amount_paise'] < 100) {
            return response()->json(['message' => 'There is nothing payable on this account.'], 422);
        }

        try {
            $order = $this->razorpay->createOrder(
                $quote['amount_paise'],
                'hsms_'.$owner->id.'_'.now()->timestamp,
                [
                    'owner_id' => (string) $owner->id,
                    'period' => $data['period'],
                    'branches' => (string) $quote['branches'],
                    'payable' => (string) $quote['payable'],
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
            'description' => ucfirst($data['period']).' subscription · '.$quote['payable'].' of '.$quote['branches'].' branch(es)',
            'prefill' => [
                'name' => $owner->name,
                'email' => $owner->email,
                'contact' => $owner->mobile,
            ],
        ]);
    }

    /** Verify the checkout signature and, only on success, renew the account. */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
            'period' => ['required', Rule::in(['yearly', 'monthly'])],
        ]);

        if (! $this->razorpay->verifySignature($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'])) {
            return response()->json(['message' => 'Payment verification failed. You have not been charged for a renewal.'], 400);
        }

        $owner = $request->user();

        // Idempotent: a repeated callback for the same payment must not double-renew.
        if (! Subscription::where('transaction_number', $data['razorpay_payment_id'])->exists()) {
            $quote = $this->billing->quote($owner, $data['period']);

            $subscription = $this->billing->renewOwner($owner, $data['period'], [
                'amount' => $quote['amount'],
                'payment_status' => 'paid',
                'payment_method' => 'online',
                'transaction_number' => $data['razorpay_payment_id'],
                'remarks' => 'Razorpay order '.$data['razorpay_order_id'].' · '.$quote['payable'].'/'.$quote['branches'].' branch(es)',
            ]);

            $this->logger->log(
                'subscription.paid',
                "Online {$data['period']} renewal — ".hsms_money($quote['amount'])." · {$quote['payable']}/{$quote['branches']} branch(es)",
                $subscription,
            );
        }

        return response()->json([
            'message' => 'Payment successful — your subscription is renewed.',
            'redirect' => route('dashboard'),
        ]);
    }
}
