<?php

namespace App\Services\Billing;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\SubscriptionOrder;
use App\Services\NotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * THE one place an online payment becomes a paid order.
 *
 * S2 wrote these rules inside PaymentLinkService and said, of the webhook and the
 * manual check sharing them: "if these two ever diverge, one of them is wrong and
 * nobody will know which." S3 adds a second online channel — owner checkout — so the
 * rules move here and BOTH channels call them. They cannot diverge now, because
 * there is only one copy.
 *
 * It owns the money decisions and nothing else:
 *   · the same payment again           → a clean no-op
 *   · a different payment, already paid → grant nothing, alert a human
 *   · a payment on a voided charge      → grant nothing, alert a human
 *   · a short capture                   → grant nothing, alert a human
 *   · otherwise                         → acceptOrder(), the one grant path since S1
 *
 * It never decides WHICH order a payment belongs to — the channel resolves that, and
 * hands over an order it has already scoped and authorised.
 */
class PaymentSettlement
{
    /** The payment was applied: the order is now paid and coverage granted. */
    public const APPLIED = 'applied';

    /** This exact payment had already been applied. Nothing changed. */
    public const ALREADY = 'already';

    /** The payment could not be applied. A Super Admin alert has been raised. */
    public const REFUSED = 'refused';

    public function __construct(
        protected AccountBillingService $billing,
        protected NotificationService $notifications,
    ) {}

    /**
     * @param  int  $paidPaise  what Razorpay says was captured — the gateway's figure, never the client's
     * @param  string  $channel  for the remarks and audit: 'payment link' | 'online checkout'
     * @param  string  $source  how we heard: 'webhook' | 'callback' | 'manual check'
     * @return string one of self::APPLIED, self::ALREADY, self::REFUSED
     */
    public function settle(SubscriptionOrder $order, string $paymentId, int $paidPaise, string $channel, string $source): string
    {
        // Read the CURRENT row. The callback and the webhook routinely race on the
        // same payment; acting on a stale instance would skip the "already paid"
        // branch and fall through to acceptOrder(), where only the unique index on
        // transaction_number stands between us and a double grant.
        $order->refresh();

        // ── Already settled ──
        if ($order->payment_status === PaymentStatus::Paid) {
            if ($order->transaction_number === $paymentId) {
                return self::ALREADY;
            }

            // A DIFFERENT payment against a charge already recorded as paid. Money
            // has been captured that grants nothing, so it must not be swallowed — a
            // human has to refund it or move it.
            $this->alertUnapplied(
                $order,
                $paymentId,
                $paidPaise,
                'the charge was already recorded as paid by '.($order->transaction_number ?: 'another payment'),
            );

            return self::REFUSED;
        }

        if ($order->payment_status === PaymentStatus::Voided) {
            // Includes a superseded owner checkout (design 14 §3): Razorpay orders
            // cannot be cancelled, so one can still be paid from a modal left open.
            $this->alertUnapplied($order, $paymentId, $paidPaise, 'the charge had been voided');

            return self::REFUSED;
        }

        // ── Amount ──
        // Neither channel accepts partial payments, so a short capture is an anomaly.
        // Granting a full term for part of the money is the one mistake here that
        // costs real revenue: grant nothing, ask a human.
        $expectedPaise = $order->amountPaise();

        if ($paidPaise > 0 && $paidPaise < $expectedPaise) {
            $this->alertUnapplied(
                $order,
                $paymentId,
                $paidPaise,
                'only '.hostelease_money($paidPaise / 100).' of '.hostelease_money($expectedPaise / 100).' was paid',
            );

            return self::REFUSED;
        }

        if ($paidPaise > $expectedPaise) {
            Log::warning('Online payment captured more than the charge', [
                'order_id' => $order->id,
                'channel' => $channel,
                'expected_paise' => $expectedPaise,
                'captured_paise' => $paidPaise,
                'payment' => $paymentId,
            ]);
        }

        try {
            $this->billing->acceptOrder($order, [
                'payment_method' => PaymentMethod::Online->value,
                'transaction_number' => $paymentId,
                'remarks' => trim(($order->remarks ? $order->remarks.' · ' : '')."Paid by {$channel} ({$source})"),
            ]);
        } catch (QueryException $e) {
            // A concurrent delivery already recorded this payment id. The UNIQUE index
            // on transaction_number is the real idempotency guard, and losing that race
            // means the work is already done.
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }

            return self::ALREADY;
        }

        return self::APPLIED;
    }

    /**
     * Money captured that we could not apply. Never silent, never a 500: a webhook
     * must return 200 or Razorpay retries forever and still never succeeds, so the
     * only correct response is to put it in front of a human.
     *
     * @param  string  $paymentId  doubles as the de-dupe key — pass something unique per incident
     */
    public function alertUnapplied(?SubscriptionOrder $order, string $paymentId, int $paidPaise, string $why, string $lead = ''): void
    {
        $who = $order?->account?->owner?->name ?? ($order ? 'account #'.$order->account_id : 'an unknown customer');

        Log::error('Online payment captured but not applied', [
            'order_id' => $order?->id,
            'payment' => $paymentId,
            'captured_paise' => $paidPaise,
            'reason' => $why,
        ]);

        $this->notifications->push(
            null,
            'payment_unapplied',
            'payment_unapplied:'.$paymentId,
            'Payment received but not applied — manual review',
            $lead.hostelease_money($paidPaise / 100).' was captured for '.$who
                .($order ? " ({$order->invoiceNumber()})" : '')
                ." but was not applied because {$why}. Review it and either refund the payment or record the charge from Account 360.",
            'danger',
        );
    }
}
