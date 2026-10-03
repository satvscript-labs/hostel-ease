<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Services\ActivityLogger;
use App\Services\Billing\AccountBillingService;
use App\Services\BranchBillingService;
use App\Services\NotificationService;
use App\Services\RazorpayService;
use App\Support\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server Razorpay webhook. Confirms a paid order even if the user's
 * browser closed before the checkout callback returned — closing the gap
 * where money is captured but the subscription would otherwise not renew.
 *
 * Public (no auth): authenticity is proven by the webhook HMAC signature.
 * Idempotent: keyed on the payment id (DB unique index), so a duplicate
 * delivery — or a race with the browser callback — is a clean no-op.
 *
 * Billing is per-branch (mirrors App\Http\Controllers\Admin\BranchManagerController::verify()):
 * the order's notes carry the branch_id stamped at checkout creation.
 */
class WebhookController extends Controller
{
    public function __construct(
        protected RazorpayService $razorpay,
        protected BranchBillingService $billing,
        protected AccountBillingService $accountBilling,
        protected ActivityLogger $logger,
        protected NotificationService $notifications,
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

        match ($event) {
            'order.paid' => $this->handleOrderPaid($payload),
            'payment.failed' => $this->handlePaymentFailed($payload),
            'refund.created', 'refund.processed' => $this->handleRefund($payload),
            default => null,
        };

        // Always 200 for a valid signature so Razorpay stops retrying.
        return response()->json(['status' => 'ok']);
    }

    /**
     * order.paid carries the order entity (with the notes we set at creation)
     * plus the payment entity. `notes.type` routes to the right billing action;
     * legacy per-branch orders have no type and carry `notes.branch_id`.
     */
    protected function handleOrderPaid(array $payload): void
    {
        $order = $payload['payload']['order']['entity'] ?? [];
        $payment = $payload['payload']['payment']['entity'] ?? [];
        $notes = $order['notes'] ?? [];
        $paymentId = $payment['id'] ?? null;
        $orderId = $order['id'] ?? null;
        $capturedPaise = (int) ($payment['amount'] ?? 0);
        $type = $notes['type'] ?? 'renew_branch';

        if (! $paymentId) {
            Log::warning('Razorpay webhook: missing payment id', ['order' => $orderId, 'event' => 'order.paid']);

            return;
        }

        match ($type) {
            'renew_account', 'add_branch' => $this->applyAccountOrder($type, $notes, $paymentId, $orderId, $capturedPaise),
            default => $this->applyBranchOrder($notes, $paymentId, $orderId, $capturedPaise),
        };
    }

    /** Legacy/per-branch order (notes.branch_id) — one branch's coverage. */
    protected function applyBranchOrder(array $notes, string $paymentId, ?string $orderId, int $capturedPaise): void
    {
        $branchId = $notes['branch_id'] ?? null;
        $period = $notes['period'] ?? null;

        if (! $branchId || ! in_array($period, ['yearly', 'monthly'], true)) {
            Log::warning('Razorpay webhook: missing branch notes', ['order' => $orderId, 'event' => 'order.paid']);

            return;
        }

        // Idempotency fast-path; the unique index on subscription_orders.transaction_number
        // is the real guard (below). S1: this used to check the legacy `subscriptions`
        // table, which is no longer written — leaving it there would have made every
        // retried delivery look new and granted coverage twice.
        if (SubscriptionOrder::where('transaction_number', $paymentId)->exists()) {
            return;
        }

        $branch = Hostel::find($branchId);
        if (! $branch) {
            Log::warning('Razorpay webhook: branch not found', ['branch_id' => $branchId, 'order' => $orderId]);

            return;
        }

        // Bind tenant so the audit log records the right hostel.
        Tenant::set($branch->id);
        try {
            $quote = $this->billing->quote($branch, $period);

            // The payment entity is authoritative for the amount; log if it diverges from the quote.
            if ($capturedPaise > 0 && $capturedPaise !== $quote['amount_paise']) {
                Log::warning('Razorpay webhook: captured amount differs from quote', [
                    'payment' => $paymentId, 'expected_paise' => $quote['amount_paise'], 'captured_paise' => $capturedPaise,
                ]);
            }
            $amount = $capturedPaise > 0 ? $capturedPaise / 100 : $quote['amount'];

            $order = $this->accountBilling->recordBranchRenewal($branch, $period, [
                'amount' => $amount,
                'payment_status' => 'paid',
                'payment_method' => 'online',
                'transaction_number' => $paymentId,
                'razorpay_order_id' => $orderId,
                'remarks' => 'Razorpay webhook · order '.$orderId,
            ]);

            $this->logger->log(
                'subscription.paid',
                "Webhook {$period} renewal — ".hostelease_money($amount),
                $order,
            );
        } catch (QueryException $e) {
            // A concurrent delivery (the browser callback) already recorded this payment id.
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
        } catch (\RuntimeException $e) {
            // The branch has no resolvable owner account, so there is nothing to bill
            // it against (S1). Money HAS been captured, so this must not throw: a 500
            // makes Razorpay retry forever and still never succeed. Record it loudly
            // and let a human reconcile it.
            Log::error('Razorpay webhook: captured payment could not be applied', [
                'payment' => $paymentId, 'order' => $orderId, 'branch_id' => $branchId, 'error' => $e->getMessage(),
            ]);

            $this->notifications->push(
                null,
                'payment_unapplied',
                'payment_unapplied:'.$paymentId,
                'Payment received but not applied — manual review',
                hostelease_money($capturedPaise / 100)." was captured for branch #{$branchId} but could not be"
                    .' recorded: '.$e->getMessage().' Resolve the branch owner, then record the charge from Account 360.',
                'danger',
            );
        } finally {
            Tenant::clear();
        }
    }

    /**
     * Account-level order (notes.type = renew_account | add_branch) — the
     * self-serve consolidated payments (Phase 6). Authoritative confirmation for
     * when the owner's browser closed before the callback posted.
     */
    protected function applyAccountOrder(string $type, array $notes, string $paymentId, ?string $orderId, int $capturedPaise): void
    {
        $accountId = $notes['account_id'] ?? null;
        $period = $notes['period'] ?? null;

        if (! $accountId || ! in_array($period, ['yearly', 'monthly'], true)) {
            Log::warning('Razorpay webhook: missing account notes', ['order' => $orderId, 'type' => $type]);

            return;
        }

        // Idempotency fast-path; the unique index on subscription_orders.transaction_number is the real guard.
        if (SubscriptionOrder::where('transaction_number', $paymentId)->exists()) {
            return;
        }

        $account = SubscriptionAccount::find($accountId);
        if (! $account) {
            Log::warning('Razorpay webhook: account not found', ['account_id' => $accountId, 'order' => $orderId]);

            return;
        }

        // Bind tenant to one of the account's branches for audit context.
        $tenantBranch = $this->accountBilling->includedBranches($account)->first();
        if ($tenantBranch) {
            Tenant::set($tenantBranch->id);
        }

        try {
            $payment = [
                'amount' => $capturedPaise > 0 ? $capturedPaise / 100 : null,
                'payment_status' => 'paid',
                'payment_method' => 'online',
                'transaction_number' => $paymentId,
                'razorpay_order_id' => $orderId,
                'remarks' => 'Razorpay webhook · order '.$orderId,
            ];

            if ($type === 'add_branch') {
                $branch = ($notes['branch_id'] ?? null) ? Hostel::find($notes['branch_id']) : null;
                if (! $branch) {
                    Log::warning('Razorpay webhook: add_branch branch not found', ['order' => $orderId]);

                    return;
                }
                $result = $this->accountBilling->addBranch($account, $branch, $payment);
                $this->logger->log('subscription.paid', "Webhook add branch {$branch->name} — ".hostelease_money($result->amount), $result);
            } else {
                $result = $this->accountBilling->renewAccount($account, $period, $payment);
                $this->logger->log('subscription.paid', "Webhook account {$period} renewal ({$result->quantity} branches) — ".hostelease_money($result->amount), $result);
            }
        } catch (QueryException $e) {
            // Concurrent browser-callback delivery already recorded this payment id.
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
        } finally {
            Tenant::clear();
        }
    }

    /**
     * A payment failed — no coverage was ever granted (only order.paid grants),
     * so there is nothing to revoke. Record it for visibility.
     */
    protected function handlePaymentFailed(array $payload): void
    {
        $payment = $payload['payload']['payment']['entity'] ?? [];

        Log::warning('Razorpay webhook: payment.failed', [
            'payment' => $payment['id'] ?? null,
            'order' => $payment['order_id'] ?? null,
            'reason' => $payment['error_description'] ?? ($payment['error_reason'] ?? null),
        ]);
    }

    /**
     * A refund was issued on Razorpay's side. We do NOT auto-revoke coverage —
     * that risks cutting off a customer who has since re-paid, and the operator
     * is manual-first. Instead we record it and raise a Super Admin alert to
     * resolve by hand. (Automated revocation lands with the account model.)
     */
    protected function handleRefund(array $payload): void
    {
        $refund = $payload['payload']['refund']['entity'] ?? [];
        $paymentId = $refund['payment_id'] ?? null;
        $amount = (int) ($refund['amount'] ?? 0);

        // S1: resolve against the order ledger. The refunded payment id is on the
        // order that recorded it, whichever path took it (link, checkout, autopay).
        $order = $paymentId
            ? SubscriptionOrder::with('account.owner')->where('transaction_number', $paymentId)->first()
            : null;

        Log::warning('Razorpay webhook: refund received', [
            'refund' => $refund['id'] ?? null,
            'payment' => $paymentId,
            'order' => $order?->id,
            'amount_paise' => $amount,
        ]);

        // Super Admin feed (hostel_id = null) so it surfaces for manual handling.
        // Still deliberately NOT auto-revoking coverage: that risks cutting off a
        // customer who has since re-paid. Voiding the order (Account 360 → Orders)
        // is the audited way to withdraw it.
        $this->notifications->push(
            null,
            'refund_review',
            'refund:'.($refund['id'] ?? $paymentId ?? uniqid()),
            'Refund received — manual review',
            'A refund of '.hostelease_money($amount / 100).' was issued'
                .($order ? ' for '.($order->account?->owner?->name ?? 'account #'.$order->account_id)." ({$order->invoiceNumber()})" : '')
                .'. Review and void the order if the coverage should be withdrawn.',
            'danger',
        );
    }
}
