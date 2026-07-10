<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\Subscription;
use App\Services\ActivityLogger;
use App\Services\BranchBillingService;
use App\Services\RazorpayService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server Razorpay webhook. Confirms a paid order even if the user's
 * browser closed before the checkout callback returned — closing the gap
 * where money is captured but the subscription would otherwise not renew.
 *
 * Public (no auth): authenticity is proven by the webhook HMAC signature.
 * Idempotent: keyed on the payment id, so a duplicate delivery is a no-op.
 *
 * Billing is per-branch (mirrors App\Http\Controllers\Admin\BranchManagerController::verify()):
 * the order's notes carry the branch_id stamped at checkout creation.
 */
class WebhookController extends Controller
{
    public function __construct(
        protected RazorpayService $razorpay,
        protected BranchBillingService $billing,
        protected ActivityLogger $logger,
    ) {
    }

    public function razorpay(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        $signature = (string) $request->header('X-Razorpay-Signature', '');

        if (! $this->razorpay->verifyWebhook($raw, $signature)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $payload = json_decode($raw, true) ?: [];
        $event = $payload['event'] ?? '';

        // order.paid carries the order entity (with the notes we set at creation)
        // plus the payment entity — everything we need to renew the branch.
        if ($event === 'order.paid') {
            $order = $payload['payload']['order']['entity'] ?? [];
            $payment = $payload['payload']['payment']['entity'] ?? [];
            $notes = $order['notes'] ?? [];

            $branchId = $notes['branch_id'] ?? null;
            $period = $notes['period'] ?? null;
            $paymentId = $payment['id'] ?? null;
            $orderId = $order['id'] ?? null;

            if ($branchId && in_array($period, ['yearly', 'monthly'], true) && $paymentId) {
                // Idempotent: skip if the checkout callback (or an earlier delivery)
                // already recorded this payment.
                if (! Subscription::where('transaction_number', $paymentId)->exists()) {
                    $branch = Hostel::find($branchId);

                    if ($branch) {
                        // Bind tenant so the audit log records the right hostel.
                        Tenant::set($branch->id);
                        try {
                            $quote = $this->billing->quote($branch, $period);
                            $subscription = $this->billing->renewBranch($branch, $period, [
                                'amount' => $quote['amount'],
                                'payment_status' => 'paid',
                                'payment_method' => 'online',
                                'transaction_number' => $paymentId,
                                'remarks' => 'Razorpay webhook · order '.$orderId,
                            ]);
                            $this->logger->log(
                                'subscription.paid',
                                "Webhook {$period} renewal — ".hostelease_money($quote['amount']),
                                $subscription,
                            );
                        } finally {
                            Tenant::clear();
                        }
                    } else {
                        Log::warning('Razorpay webhook: branch not found', ['branch_id' => $branchId, 'order' => $orderId]);
                    }
                }
            } else {
                Log::warning('Razorpay webhook: missing notes/payment id', ['order' => $orderId, 'event' => $event]);
            }
        }

        // Always 200 for a valid signature so Razorpay stops retrying.
        return response()->json(['status' => 'ok']);
    }
}
