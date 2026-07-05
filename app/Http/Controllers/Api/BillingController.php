<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\ActivityLogger;
use App\Services\RazorpayService;
use App\Services\SubscriptionBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Mobile (Flutter) account billing — mirrors the web BillingController.
 * One payment renews every branch the owner holds.
 */
class BillingController extends Controller
{
    public function __construct(
        protected SubscriptionBillingService $billing,
        protected RazorpayService $razorpay,
        protected ActivityLogger $logger,
    ) {
    }

    /** Quote + current status for the billing screen. */
    public function show(Request $request): JsonResponse
    {
        $owner = $request->user();
        $end = $this->billing->currentEnd($owner);
        $yearly = $this->billing->quote($owner, 'yearly');
        $monthly = $this->billing->quote($owner, 'monthly');

        return response()->json([
            'online_enabled' => $this->razorpay->isConfigured(),
            'branches' => $yearly['branches'],
            'free' => $yearly['free'],
            'payable' => $yearly['payable'],
            'active' => $end && ! $end->isPast(),
            'valid_until' => $end?->format('Y-m-d'),
            'plans' => [
                'yearly' => ['amount' => $yearly['amount'], 'unit' => $yearly['unit'], 'ends' => $yearly['end']->format('Y-m-d')],
                'monthly' => ['amount' => $monthly['amount'], 'unit' => $monthly['unit'], 'ends' => $monthly['end']->format('Y-m-d')],
            ],
        ]);
    }

    public function createOrder(Request $request): JsonResponse
    {
        $data = $request->validate(['period' => ['required', Rule::in(['yearly', 'monthly'])]]);

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
                ['owner_id' => (string) $owner->id, 'period' => $data['period'], 'branches' => (string) $quote['branches']],
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() === 401 ? 401 : 500);
        }

        return response()->json([
            'key' => $this->razorpay->keyId(),
            'order_id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'period' => $data['period'],
            'name' => config('app.name'),
            'description' => ucfirst($data['period']).' subscription · '.$quote['payable'].' of '.$quote['branches'].' branch(es)',
            'prefill' => ['name' => $owner->name, 'email' => $owner->email, 'contact' => $owner->mobile],
        ]);
    }

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

        if (! Subscription::where('transaction_number', $data['razorpay_payment_id'])->exists()) {
            $quote = $this->billing->quote($owner, $data['period']);
            $subscription = $this->billing->renewOwner($owner, $data['period'], [
                'amount' => $quote['amount'],
                'payment_status' => 'paid',
                'payment_method' => 'online',
                'transaction_number' => $data['razorpay_payment_id'],
                'remarks' => 'Razorpay (app) order '.$data['razorpay_order_id'].' · '.$quote['payable'].'/'.$quote['branches'].' branch(es)',
            ]);

            $this->logger->log(
                'subscription.paid',
                "App {$data['period']} renewal — ".hsms_money($quote['amount']),
                $subscription,
            );
        }

        return response()->json(['message' => 'Payment successful — your subscription is renewed.']);
    }
}
