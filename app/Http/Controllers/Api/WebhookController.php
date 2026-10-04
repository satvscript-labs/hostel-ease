<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionOrderLine;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\PaymentLinkService;
use App\Services\NotificationService;
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
 * Idempotent: keyed on the payment id (DB unique index), so a duplicate
 * delivery — or a race with the browser callback — is a clean no-op.
 *
 * Every payment this receives settles a PENDING ORDER that already exists — an
 * owner checkout (`order.paid`, notes.type = checkout) or an operator payment link
 * (`payment_link.*`). Both go through PaymentSettlement, so the two channels apply
 * money by exactly one set of rules. Nothing here prices anything.
 */
class WebhookController extends Controller
{
    public function __construct(
        protected RazorpayService $razorpay,
        protected NotificationService $notifications,
        protected PaymentLinkService $paymentLinks,
        protected CheckoutService $checkout,
    ) {}

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
            // S2 — operator-issued payment links.
            'payment_link.paid' => $this->handlePaymentLinkPaid($payload),
            'payment_link.partially_paid' => $this->handlePaymentLinkPartiallyPaid($payload),
            'payment_link.expired' => $this->handlePaymentLinkClosed($payload, 'expired'),
            'payment_link.cancelled' => $this->handlePaymentLinkClosed($payload, 'cancelled'),
            default => null,
        };

        // Always 200 for a valid signature so Razorpay stops retrying.
        return response()->json(['status' => 'ok']);
    }

    /**
     * order.paid — a Razorpay order was paid in full.
     *
     * Since S3 the only orders this system creates are OWNER CHECKOUT orders, each
     * opened for a pending order that already exists (`notes.type = checkout`, with
     * `he_order_id`). Those settle through CheckoutService — the same
     * PaymentSettlement payment links use.
     *
     * The Phase 6 arms that used to live here ("renew_account", "add_branch", and a
     * notes-less default that renewed one branch at list price) are RETIRED. They
     * re-quoted at payment time and built the order from whatever the account looked
     * like then, so a branch cancelled mid-checkout left a customer who paid for two
     * branches with one renewed (14_S3_DESIGN.md §1 P2). Production never created an
     * order they would match — every endpoint that did checked owner_self_serve, which
     * .env.production has never set — so retiring them strands nothing. Anything
     * legacy-shaped that arrives anyway goes to a human rather than being applied.
     */
    protected function handleOrderPaid(array $payload): void
    {
        $order = $payload['payload']['order']['entity'] ?? [];
        $payment = $payload['payload']['payment']['entity'] ?? [];
        $notes = $order['notes'] ?? [];
        $paymentId = $payment['id'] ?? null;
        $orderId = $order['id'] ?? null;
        $capturedPaise = (int) ($payment['amount'] ?? 0);

        if (! $paymentId) {
            Log::warning('Razorpay webhook: missing payment id', ['order' => $orderId, 'event' => 'order.paid']);

            return;
        }

        match ($notes['type'] ?? null) {
            'checkout' => $this->handleCheckoutPaid($order, $payment),

            // ── A PAYMENT LINK'S OWN ORDER (S2 · design 10 §6 BP1) ──
            // Every payment link has a Razorpay order behind it, and Razorpay may
            // propagate the link's notes onto it — so this event can arrive for a
            // payment `payment_link.paid` also reports. The link arm owns it.
            'payment_link' => null,

            // Retired Phase 6 shapes, or an order created outside this system (from
            // the Razorpay dashboard, say). Money was captured, so this must neither
            // 500 nor vanish: alert, and let a human decide where it belongs.
            default => $this->alertUnrecognisedOrder($orderId, $paymentId, $capturedPaise, $notes),
        };
    }

    /** An owner checkout was paid. Bind the tenant for the audit line, then settle. */
    protected function handleCheckoutPaid(array $order, array $payment): void
    {
        $noteId = $order['notes']['he_order_id'] ?? null;
        $branchId = ($noteId && ctype_digit((string) $noteId))
            ? SubscriptionOrderLine::where('order_id', (int) $noteId)->value('branch_id')
            : null;

        if ($branchId) {
            Tenant::set((int) $branchId);
        }

        try {
            $this->checkout->settleFromWebhook($order, $payment);
        } finally {
            Tenant::clear();
        }
    }

    /** A paid Razorpay order this system does not recognise. */
    protected function alertUnrecognisedOrder(?string $orderId, string $paymentId, int $capturedPaise, array $notes): void
    {
        Log::error('Razorpay webhook: order.paid for an order this system did not create', [
            'order' => $orderId, 'payment' => $paymentId, 'captured_paise' => $capturedPaise, 'notes' => $notes,
        ]);

        $this->notifications->push(
            null,
            'payment_unapplied',
            'payment_unapplied:'.$paymentId,
            'Payment received for an unrecognised order — manual review',
            hostelease_money($capturedPaise / 100)." was paid on Razorpay order {$orderId}, which this system"
                .' did not create (or created with a format it no longer uses), so nothing was applied.'
                .' Find the customer in the Razorpay dashboard, then record the payment from Account 360 — or refund it.',
            'danger',
        );
    }

    // -----------------------------------------------------------------
    // Payment links (S2)
    // -----------------------------------------------------------------

    /**
     * A customer paid a link the operator sent them.
     *
     * The payload carries three entities — the link, its Razorpay order, and the
     * payment. We resolve OUR order from the link, then hand it to the single
     * shared apply path, which ends in AccountBillingService::acceptOrder(). No
     * coverage logic lives here; this arm's whole job is resolution and honesty
     * about what it could not do.
     */
    protected function handlePaymentLinkPaid(array $payload): void
    {
        $link = $payload['payload']['payment_link']['entity'] ?? [];
        $payment = $payload['payload']['payment']['entity'] ?? [];

        $paymentId = $payment['id'] ?? null;
        // `amount_paid` on the link is the authority on how much actually landed;
        // the payment entity is the fallback when a payload omits it.
        $paidPaise = max((int) ($link['amount_paid'] ?? 0), (int) ($payment['amount'] ?? 0));

        $order = $this->paymentLinks->resolveOrder($link);

        if (! $paymentId) {
            // Razorpay says this link is PAID but sent us no payment entity. Money
            // has moved and we cannot key an idempotent apply on anything, so this
            // must not be applied — but it also must not be a log line nobody reads,
            // which is what it was. Raise it, with the link id a human can look up,
            // and point them at the one-click recovery.
            Log::error('Razorpay webhook: payment_link.paid carried no payment id', [
                'payment_link_id' => $link['id'] ?? null,
                'order_id' => $order?->id,
            ]);

            $this->notifications->push(
                null,
                'payment_unapplied',
                'payment_link_no_payment_id:'.($link['id'] ?? uniqid()),
                'A payment link was paid but could not be applied — manual review',
                'Razorpay reports payment link '.($link['id'] ?? 'unknown').' as paid'
                    .($order ? ' for '.($order->account?->owner?->name ?? 'account #'.$order->account_id)." ({$order->invoiceNumber()})" : '')
                    .', but sent no payment reference, so it was not applied. Open that charge on'
                    .' Account 360 and use "Check with Razorpay" to pull the payment through.',
                'danger',
            );

            return;
        }

        if (! $order) {
            // Money captured with nothing here to apply it to. This must NOT throw:
            // a 500 makes Razorpay retry forever and still never succeed. Alert a
            // human and return 200.
            $this->paymentLinks->alertUnresolved($link, $paymentId, $paidPaise);

            return;
        }

        // Bind the tenant so the audit entry lands against a real branch — the
        // order's own first line, which is more precise than guessing from the
        // account's branch list.
        $branchId = $order->lines()->value('branch_id');
        if ($branchId) {
            Tenant::set((int) $branchId);
        }

        try {
            $this->paymentLinks->applyPaid($order, $paymentId, $paidPaise, 'webhook');
        } finally {
            Tenant::clear();
        }
    }

    /**
     * A link was partly paid. We create links with partial payments DISABLED, so
     * this is an anomaly: it grants nothing and raises an alert, because granting a
     * full term for part of the money is the one mistake here that costs revenue.
     */
    protected function handlePaymentLinkPartiallyPaid(array $payload): void
    {
        $link = $payload['payload']['payment_link']['entity'] ?? [];
        $order = $this->paymentLinks->resolveOrder($link);

        if (! $order) {
            Log::warning('Razorpay webhook: payment_link.partially_paid for an unknown link', [
                'payment_link_id' => $link['id'] ?? null,
            ]);

            return;
        }

        $this->paymentLinks->markPartiallyPaid($order, (int) ($link['amount_paid'] ?? 0));
    }

    /**
     * A link lapsed or was cancelled. The CHARGE is still owed — only the link
     * died — so the order stays `pending` and stays in receivables. All that
     * changes is that the operator can now re-issue it.
     */
    protected function handlePaymentLinkClosed(array $payload, string $how): void
    {
        $link = $payload['payload']['payment_link']['entity'] ?? [];
        $order = $this->paymentLinks->resolveOrder($link);

        if (! $order) {
            Log::warning("Razorpay webhook: payment_link.{$how} for an unknown link", [
                'payment_link_id' => $link['id'] ?? null,
            ]);

            return;
        }

        // A link that is already paid here cannot be re-opened by a late expiry
        // notice — Razorpay can deliver these out of order.
        if ($order->payment_link_status?->value === 'paid') {
            return;
        }

        $branchId = $order->lines()->value('branch_id');
        if ($branchId) {
            Tenant::set((int) $branchId);
        }

        try {
            $how === 'expired'
                ? $this->paymentLinks->markExpired($order)
                : $this->paymentLinks->markCancelled($order);
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
