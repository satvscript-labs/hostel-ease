<?php

namespace App\Services\Billing;

use App\Enums\AccountStatus;
use App\Enums\BillingPeriod;
use App\Enums\CollectionMethod;
use App\Enums\OrderKind;
use App\Enums\PaymentStatus;
use App\Mail\AutopayMail;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionMandate;
use App\Models\SubscriptionOrder;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use App\Services\RazorpayService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * UPI Autopay (S6 · _artifact/saas_billing_autopay/31_S6_DESIGN.md).
 *
 * Razorpay holds only the mandate. Every debit is an ordinary pending renewal order of
 * ours, priced by quoteRenewal() and settled by PaymentSettlement — the same rules as
 * every other channel. This class decides WHEN a debit may be raised and keeps the
 * mandate's state; it never prices anything.
 *
 * The rule that matters most (L1): while a debit is in flight — raised, not yet paid or
 * failed, which on UPI takes a day or more — nothing else may take money for that
 * renewal. SubscriptionOrder::isAutopayInFlight() is what every other path asks.
 */
class AutopayService
{
    public function __construct(
        protected RazorpayService $razorpay,
        protected AccountBillingService $billing,
        protected PaymentSettlement $settlement,
        protected PaymentLinkService $paymentLinks,
        protected NotificationService $notifications,
        protected ActivityLogger $logger,
    ) {}

    /** The switch (L11) and Razorpay configured. */
    public function enabled(): bool
    {
        return (bool) config('hostelease.autopay.enabled') && $this->razorpay->isConfigured();
    }

    public function ceiling(): float
    {
        return (float) config('hostelease.autopay.max_amount', 10000);
    }

    /** The account's current, non-terminal mandate — at most one. */
    public function mandateFor(SubscriptionAccount $account): ?SubscriptionMandate
    {
        return SubscriptionMandate::where('account_id', $account->id)
            ->whereNotIn('status', SubscriptionMandate::TERMINAL)
            ->latest('id')
            ->first();
    }

    /**
     * The debit currently in flight for this account, if any (L1). From the moment the
     * attempt is recorded — before the bank is even asked — until it is paid or voided.
     */
    public function inFlight(SubscriptionAccount $account): ?SubscriptionOrder
    {
        return $account->orders()
            ->where('collection', CollectionMethod::Autopay->value)
            ->where('payment_status', PaymentStatus::Pending->value)
            ->latest('id')
            ->first();
    }

    /** The pending mandate this order was raised to authorise, if any. */
    public function pendingSetupFor(SubscriptionOrder $order): ?SubscriptionMandate
    {
        return SubscriptionMandate::where('setup_order_id', $order->id)
            ->where('status', SubscriptionMandate::PENDING)
            ->whereNotNull('customer_id')
            ->latest('id')
            ->first();
    }

    // ── switching it on (D-S6-2: with a renewal payment) ────────────────────

    /**
     * Why this owner cannot switch autopay on with this renewal — or null if they can.
     */
    public function setupRefusal(SubscriptionAccount $account, ?User $viewer, string $period, float $total): ?string
    {
        $mandate = $this->mandateFor($account);

        return match (true) {
            ! $this->enabled() => __('Autopay is not available right now.'),
            ! $viewer || $account->owner_id !== $viewer->id => __('Only the account owner can turn on autopay.'),
            ! $account->selfServeEnabled() => __('Autopay is not available on this account.'),
            $period !== BillingPeriod::Monthly->value => __('Autopay works on monthly plans.'),
            $total <= 0 => __('This renewal is free — turn on autopay at your next paid renewal.'),
            $total > $this->ceiling() => __('Your monthly total of :amount is above the :limit autopay limit.', ['amount' => hostelease_money($total), 'limit' => hostelease_money($this->ceiling())]),
            ! filled($account->owner?->email) => __('Add your email in Settings → Profile first — your bank sends autopay notices there.'),
            $mandate && in_array($mandate->status, [SubscriptionMandate::ACTIVE, SubscriptionMandate::PAUSED, SubscriptionMandate::CANCELLING], true) => __('Autopay is already set up on this account.'),
            default => null,
        };
    }

    /**
     * Create (or reuse) the Razorpay customer and the pending mandate, and the
     * authorisation order for THIS renewal's amount. Called by CheckoutService inside
     * its transaction, for an order it has just created.
     *
     * @return array{razorpay_order_id:string, customer_id:string}
     */
    public function prepareSetup(SubscriptionOrder $order, User $owner): array
    {
        $account = $order->account;
        $mandate = $this->mandateFor($account);

        // An earlier set-up that was never paid is reused, not stacked.
        if ($mandate && $mandate->status !== SubscriptionMandate::PENDING) {
            throw new RuntimeException(__('Autopay is already set up on this account.'));
        }

        $customerId = $mandate?->customer_id ?: $this->razorpay->createCustomer(
            $owner->name, (string) $owner->email, (string) $owner->mobile, ['he_account_id' => (string) $account->id],
        )['id'];

        $rzp = $this->razorpay->createMandateOrder(
            $order->amountPaise(),
            (int) round($this->ceiling() * 100),
            $customerId,
            (string) $order->public_id,
            [
                'type' => 'checkout', 'he_autopay' => '1', 'he_order_id' => (string) $order->id,
                'he_order_ref' => (string) $order->public_id, 'he_account_id' => (string) $order->account_id,
                'he_kind' => $order->kind?->value, 'he_invoice' => $order->invoiceNumber(),
            ],
        );

        $attrs = [
            'customer_id' => $customerId, 'email' => $owner->email, 'contact' => $owner->mobile,
            'max_amount' => $this->ceiling(), 'status' => SubscriptionMandate::PENDING,
            'setup_order_id' => $order->id, 'created_by' => $owner->id, 'failure_reason' => null,
        ];
        $mandate ? $mandate->update($attrs) : SubscriptionMandate::create($attrs + ['account_id' => $account->id]);

        return ['razorpay_order_id' => $rzp['id'], 'customer_id' => $customerId];
    }

    /**
     * The set-up renewal was paid. Its payment carries the mandate's token; a UPI
     * Autopay registration and its first payment are one transaction, so a captured
     * set-up payment with a token means the mandate exists. token.confirmed confirms
     * it again, token.rejected undoes it.
     */
    public function onSetupPaid(SubscriptionOrder $order, ?string $tokenId, ?string $customerId): void
    {
        $mandate = SubscriptionMandate::where('setup_order_id', $order->id)->latest('id')->first();
        if (! $mandate || ! $tokenId) {
            return;
        }

        // The payment must belong to the customer the mandate was created for (L5/L9).
        if ($customerId && $mandate->customer_id && $customerId !== $mandate->customer_id) {
            Log::error('Autopay: set-up payment from a different customer', ['order' => $order->id, 'mandate' => $mandate->id]);

            return;
        }

        if ($mandate->status === SubscriptionMandate::PENDING) {
            $mandate->update(['token_id' => $tokenId, 'status' => SubscriptionMandate::ACTIVE, 'authorised_at' => now()]);
            $this->logger->log('subscription.autopay', 'Autopay switched on — UPI, up to '.hostelease_money($mandate->max_amount).' a month', $order->account);
        }
    }

    // ── mandate state, from Razorpay (token.* webhooks) ─────────────────────

    public function onTokenEvent(string $event, array $token): void
    {
        $tokenId = $token['id'] ?? null;
        $mandate = $tokenId ? SubscriptionMandate::where('token_id', $tokenId)->first() : null;

        if (! $mandate) {
            Log::info('Autopay: token event for a token we do not hold', ['event' => $event, 'token' => $tokenId]);

            return;
        }

        $reason = $token['recurring_details']['failure_reason'] ?? null;

        match ($event) {
            'token.confirmed' => in_array($mandate->status, [SubscriptionMandate::PENDING, SubscriptionMandate::PAUSED], true)
                ? $mandate->update(['status' => SubscriptionMandate::ACTIVE, 'authorised_at' => $mandate->authorised_at ?? now()])
                : null,
            'token.paused' => $mandate->status === SubscriptionMandate::ACTIVE
                ? $mandate->update(['status' => SubscriptionMandate::PAUSED])
                : null,
            'token.rejected' => $mandate->update(['status' => SubscriptionMandate::REJECTED, 'failure_reason' => $reason]),
            'token.cancelled' => $mandate->status !== SubscriptionMandate::CANCELLED
                ? $mandate->update([
                    'status' => SubscriptionMandate::CANCELLED,
                    'cancelled_at' => now(),
                    // We asked → whoever asked; otherwise the customer did it in their UPI app.
                    'cancelled_by' => $mandate->status === SubscriptionMandate::CANCELLING ? ($mandate->cancelled_by ?? 'owner') : 'customer',
                ])
                : null,
            default => null,
        };

        if (in_array($event, ['token.cancelled', 'token.paused', 'token.rejected'], true) && $mandate->account) {
            $this->logger->log('subscription.autopay', "Autopay {$mandate->label()} ({$event})", $mandate->account);
        }
    }

    /**
     * Turn autopay off. With a token, Razorpay is asked to cancel it at NPCI — the
     * mandate reads "Turning off…" until token.cancelled. A debit already in flight
     * cannot be recalled; it resolves normally.
     *
     * It ALWAYS works for the person asking. The mandate is charge-at-will: Razorpay
     * debits only when this system asks. So if Razorpay cannot cancel it right now,
     * it is switched off HERE (nothing more is ever raised) and the operator is asked
     * to remove the leftover mandate in the Razorpay dashboard.
     */
    public function cancel(SubscriptionMandate $mandate, string $by, ?User $actor = null): void
    {
        if ($mandate->isTerminal()) {
            return;
        }

        if ($mandate->token_id && $mandate->customer_id) {
            try {
                $this->razorpay->cancelToken($mandate->customer_id, $mandate->token_id);
                $mandate->update(['status' => SubscriptionMandate::CANCELLING, 'cancelled_by' => $by]);
            } catch (Throwable $e) {
                $mandate->update([
                    'status' => SubscriptionMandate::CANCELLED, 'cancelled_at' => now(), 'cancelled_by' => $by,
                    'failure_reason' => mb_substr('Razorpay did not cancel it: '.$e->getMessage(), 0, 250),
                ]);
                $this->notifications->push(null, 'autopay_cancel_failed', 'mandate:'.$mandate->id,
                    'Remove an autopay mandate in Razorpay — '.($mandate->account?->owner?->name ?? 'a customer'),
                    'Autopay was turned off here (nothing more will be debited), but Razorpay could not cancel token '.$mandate->token_id
                        .' just now ('.mb_substr($e->getMessage(), 0, 120).'). Cancel it in the Razorpay dashboard so it disappears from the UPI app of the customer.',
                    'warning');
            }
        } else {
            $mandate->update(['status' => SubscriptionMandate::CANCELLED, 'cancelled_at' => now(), 'cancelled_by' => $by]);
        }

        $this->logger->log('subscription.autopay', 'Autopay turned off by the '.$by.($actor ? " ({$actor->name})" : ''), $mandate->account);
    }

    // ── the daily debit ─────────────────────────────────────────────────────

    /**
     * Raise today's debit for one account, if one is due — or explain why not.
     *
     * @return string what happened: raised | free | skipped:<why> | waiting | exhausted | over_limit | failed
     */
    public function debitDue(SubscriptionAccount $account): string
    {
        // 1. Decide, and RECORD the attempt, under the account lock — committed before
        //    any money call, so a crash later can never lose it (S6 audit).
        $decision = $this->recordAttempt($account);
        if (is_string($decision)) {
            return $decision;
        }

        // 2. Then the bank, outside the lock.
        return $this->sendToBank($account, ...$decision);
    }

    /**
     * @return string|array{0: SubscriptionOrder, 1: SubscriptionMandate, 2: Carbon}
     */
    protected function recordAttempt(SubscriptionAccount $account): string|array
    {
        return DB::transaction(function () use ($account) {
            // One debit decision at a time per account (L2).
            SubscriptionAccount::query()->whereKey($account->getKey())->lockForUpdate()->first();
            $account->refresh();

            // A debit still in flight: wait (and look it up if it is taking too long) —
            // whatever has happened to the mandate since it was raised.
            if ($flying = $this->inFlight($account)) {
                $this->reconcile($flying);

                return 'waiting';
            }

            $mandate = $this->mandateFor($account);
            if (! $mandate || ! $mandate->isActive()) {
                return 'skipped:no active mandate';
            }

            // L7 — every time, never cached.
            $why = match (true) {
                ! $this->enabled() => 'autopay off',
                ! $account->selfServeEnabled() => 'not self-serve',
                $account->status === AccountStatus::Suspended => 'suspended',
                $account->period !== BillingPeriod::Monthly => 'not monthly',
                ! $account->current_period_end => 'no renewal date',
                $this->billing->includedBranches($account)->isEmpty() => 'no billable branches',
                default => null,
            };
            if ($why) {
                return 'skipped:'.$why;
            }

            $dueOn = $account->current_period_end->copy()->startOfDay();
            $days = (int) now()->startOfDay()->diffInDays($dueOn, false);
            $attempts = (array) config('hostelease.autopay.attempts', [3, 1, -1]);
            $tried = $account->orders()->where('collection', CollectionMethod::Autopay->value)->whereDate('autopay_due_on', $dueOn)->count();

            if ($tried >= count($attempts)) {
                $this->notifyFailedOnce($account, $mandate, $dueOn, 'exhausted');

                return 'exhausted';
            }
            if ($days > $attempts[$tried] || $days < -((int) config('hostelease.grace_days', 0))) {
                return 'skipped:not due';
            }

            // A renewal already raised. The OPERATOR's (proforma or link) is how this
            // one gets paid — autopay stays out, and covers() says so, so the reminders
            // ask for it. The OWNER's own checkout they walked away from is cleared,
            // the way their own retry clears it — otherwise one closed checkout window
            // would switch autopay off for that month without anyone knowing.
            if ($open = $this->openRenewal($account)) {
                if (! $this->isAbandonedCheckout($open)) {
                    return 'skipped:a renewal is already waiting';
                }
                $cleared = $this->clearAbandonedCheckout($open);
                if ($cleared !== 'cleared') {
                    return 'skipped:'.$cleared;
                }
            }

            $quote = $this->billing->quoteRenewal($account, BillingPeriod::Monthly->value);
            $total = (float) $quote['total'];

            if ((int) round($total * 100) === 0) {
                $this->billing->renewAccount($account, BillingPeriod::Monthly->value, [
                    'payment_status' => PaymentStatus::Paid->value, 'collection' => CollectionMethod::Offline->value,
                    'remarks' => 'Free renewal (autopay account — nothing to debit)',
                ]);

                return 'free';
            }

            // L3 — above the ceiling: never split, never debit.
            if ((int) round($total * 100) > $mandate->maxAmountPaise()) {
                $this->notifyFailedOnce($account, $mandate, $dueOn, 'over_limit', $total);

                return 'over_limit';
            }

            $order = $this->billing->renewAccount($account, BillingPeriod::Monthly->value, [
                'payment_status' => PaymentStatus::Pending->value,
                'collection' => CollectionMethod::Autopay->value,
                'remarks' => 'Autopay debit — attempt '.($tried + 1),
            ]);
            $order->forceFill(['autopay_due_on' => $dueOn->toDateString(), 'autopay_attempt' => $tried + 1])->save();

            return [$order, $mandate, $dueOn];
        });
    }

    /**
     * Ask the bank for a recorded attempt. Each result is stored the moment it
     * arrives. Only a definite refusal voids the attempt; an unknown outcome (a
     * timeout, a Razorpay 5xx) leaves it in flight for reconcile() to settle — never
     * a second debit raised beside one that may be running.
     */
    protected function sendToBank(SubscriptionAccount $account, SubscriptionOrder $order, SubscriptionMandate $mandate, Carbon $dueOn): string
    {
        $attempt = (int) $order->autopay_attempt;
        $notes = ['type' => 'autopay', 'he_order_id' => (string) $order->id, 'he_order_ref' => (string) $order->public_id, 'he_account_id' => (string) $account->id, 'he_attempt' => (string) $attempt];

        try {
            $rzpOrderId = $this->razorpay->createDebitOrder($order->amountPaise(), (string) $order->public_id, $notes)['id'];
        } catch (Throwable $e) {
            // No payment can exist without an order of ours, so nothing will be taken.
            return $this->attemptRefused($order, $mandate, $e);
        }
        $order->update(['razorpay_order_id' => $rzpOrderId]);

        try {
            $this->razorpay->createRecurringPayment(
                $rzpOrderId, $order->amountPaise(), $mandate->customer_id, $mandate->token_id,
                (string) ($mandate->email ?: $account->owner?->email), (string) ($mandate->contact ?: $account->owner?->mobile),
                'Monthly renewal · '.$order->quantity.' branch'.($order->quantity === 1 ? '' : 'es'), $notes,
            );
        } catch (Throwable $e) {
            $code = (int) $e->getCode();
            if ($code >= 400 && $code < 500) {
                return $this->attemptRefused($order, $mandate, $e);
            }
            // Unknown: it may be running. Leave it with the bank; reconcile() asks.
            Log::warning('Autopay: debit outcome unknown — left for reconcile', ['order' => $order->id, 'error' => $e->getMessage()]);
            $this->logger->log('subscription.autopay', 'Autopay debit sent — Razorpay did not answer clearly; checking it later (attempt '.$attempt.')', $order->fresh());

            return 'raised';
        }

        $this->logger->log('subscription.autopay', 'Autopay debit raised — '.hostelease_money($order->amount).' (attempt '.$attempt.')', $order->fresh());

        if ($account->owner?->email) {
            $this->mail($account->owner->email, new AutopayMail($account, 'debiting', (float) $order->amount, $dueOn));
        }

        return 'raised';
    }

    /** The bank definitely refused: the attempt is spent (a broken mandate is not hammered daily) and recorded. */
    protected function attemptRefused(SubscriptionOrder $order, SubscriptionMandate $mandate, Throwable $e): string
    {
        $this->billing->voidOrder($order, 'Autopay could not start the debit: '.mb_substr($e->getMessage(), 0, 150));
        $mandate->update(['failure_reason' => mb_substr($e->getMessage(), 0, 250)]);
        Log::warning('Autopay: debit could not be started', ['account' => $order->account_id, 'error' => $e->getMessage()]);

        $attempts = count((array) config('hostelease.autopay.attempts', [3, 1, -1]));
        if ((int) $order->autopay_attempt >= $attempts && $order->account) {
            $this->notifyFailedOnce($order->account, $mandate, Carbon::parse($order->autopay_due_on), 'exhausted', (float) $order->amount);
        }

        return 'failed';
    }

    // ── results ─────────────────────────────────────────────────────────────

    /** order.paid for an autopay debit: settle it like any other payment. */
    public function settleDebit(SubscriptionOrder $order, string $paymentId, int $paidPaise, string $source): string
    {
        $result = $this->settlement->settle($order, $paymentId, $paidPaise, 'autopay', $source);

        if ($result === PaymentSettlement::APPLIED) {
            $order->update(['collection' => CollectionMethod::Autopay->value]);
            $this->logger->log('subscription.paid', 'Autopay paid — '.$order->invoiceNumber().' · '.hostelease_money($order->amount), $order->fresh(), ['payment' => $paymentId]);
            if ($order->account) {
                $this->paymentLinks->cancelOvertakenLinks($order->account, $order->id);
            }
        }

        return $result;
    }

    /**
     * payment.failed for an autopay debit: the attempt is over; the job retries on
     * schedule. With $verify (the webhook), Razorpay is asked first whether the order
     * has a payment that DID go through, or one still running — voiding then would
     * land that money on a dead charge. If Razorpay cannot be reached, nothing is
     * voided: the daily reconcile settles it later (L10).
     */
    public function debitFailed(SubscriptionOrder $order, ?string $reason, bool $verify = true): void
    {
        $order->refresh();
        if ($order->payment_status !== PaymentStatus::Pending || $order->collection !== CollectionMethod::Autopay) {
            return;
        }

        if ($verify && $order->razorpay_order_id) {
            try {
                $payments = collect($this->razorpay->fetchOrderPayments($order->razorpay_order_id));
            } catch (Throwable $e) {
                Log::warning('Autopay: could not double-check a failed debit — left for reconcile', ['order' => $order->id, 'error' => $e->getMessage()]);

                return;
            }
            if ($captured = $payments->firstWhere('status', 'captured')) {
                $this->settleDebit($order, $captured['id'], (int) $captured['amount'], 'webhook (after a failure)');

                return;
            }
            if ($payments->contains(fn ($p) => in_array($p['status'] ?? null, ['created', 'authorized'], true))) {
                return;
            }
        }

        $this->billing->voidOrder($order, 'Autopay debit failed'.($reason ? ': '.mb_substr($reason, 0, 150) : ''));
        $mandate = $order->account ? $this->mandateFor($order->account) : null;
        $mandate?->update(['failure_reason' => $reason]);
        $this->logger->log('subscription.autopay', 'Autopay debit failed (attempt '.$order->autopay_attempt.')'.($reason ? " — {$reason}" : ''), $order);

        $attempts = count((array) config('hostelease.autopay.attempts', [3, 1, -1]));
        if ($mandate && (int) $order->autopay_attempt >= $attempts) {
            $this->notifyFailedOnce($order->account, $mandate, Carbon::parse($order->autopay_due_on), 'exhausted', (float) $order->amount);
        }
    }

    /**
     * A debit with no final status after reconcile_after_hours: ask Razorpay (L10).
     */
    public function reconcile(SubscriptionOrder $order): void
    {
        $after = (int) config('hostelease.autopay.reconcile_after_hours', 48);

        // Recorded, but the run died before Razorpay was asked: no order there means
        // no payment can exist. Closed after an hour (a run in progress is long done).
        if (! $order->razorpay_order_id) {
            if ($order->created_at->lessThan(now()->subHour())) {
                $this->debitFailed($order, 'never reached Razorpay', verify: false);
            }

            return;
        }

        if ($order->created_at->greaterThan(now()->subHours($after))) {
            return;
        }

        try {
            $payments = collect($this->razorpay->fetchOrderPayments($order->razorpay_order_id));
        } catch (Throwable $e) {
            Log::warning('Autopay: could not reconcile a debit', ['order' => $order->id, 'error' => $e->getMessage()]);

            return;
        }

        if ($captured = $payments->firstWhere('status', 'captured')) {
            $this->settleDebit($order, $captured['id'], (int) $captured['amount'], 'reconcile');
        } elseif ($payments->isNotEmpty() && ! $payments->contains(fn ($p) => in_array($p['status'], ['created', 'authorized'], true))) {
            $this->debitFailed($order, 'failed (found on reconcile)', verify: false);
        } elseif ($payments->isEmpty() && $order->created_at->lessThan(now()->subHours($after * 2))) {
            // Razorpay never even created the payment: the attempt is dead.
            $this->debitFailed($order, 'no payment was created', verify: false);
        }
    }

    /**
     * What autopay will do next for this account — for the owner page, the card, the
     * reminders and Account 360.
     *
     * @return array{mandate: ?SubscriptionMandate, in_flight: ?SubscriptionOrder, next_on: ?Carbon, over_limit: bool, idle: ?string}
     */
    public function state(SubscriptionAccount $account): array
    {
        $mandate = $this->mandateFor($account);
        $nextOn = null;
        $over = false;

        if ($mandate?->isActive() && $account->current_period_end && $account->period === BillingPeriod::Monthly
            && $this->enabled() && $account->selfServeEnabled()) {
            // The next attempt still to come for this renewal (after a failed one, the
            // next rung of the ladder — not "today").
            $attempts = (array) config('hostelease.autopay.attempts', [3, 1, -1]);
            $anchor = $account->current_period_end->copy()->startOfDay();
            $tried = $account->orders()->where('collection', CollectionMethod::Autopay->value)->whereDate('autopay_due_on', $anchor)->count();
            if (isset($attempts[$tried])) {
                $nextOn = $anchor->copy()->subDays((int) $attempts[$tried]);
                if ($nextOn->lessThan(now()->startOfDay())) {
                    $nextOn = now()->startOfDay();
                }
            }
            $over = (float) $this->billing->quoteRenewal($account, BillingPeriod::Monthly->value)['total'] > (float) $mandate->max_amount;
        }

        // An active mandate that will not debit right now — and why, in the owner's terms.
        $idle = match (true) {
            ! $mandate?->isActive() => null,
            $account->period !== BillingPeriod::Monthly => 'not_monthly',
            ! $this->enabled() || ! $account->selfServeEnabled() => 'paused_by_us',
            default => null,
        };

        return ['mandate' => $mandate, 'in_flight' => $this->inFlight($account), 'next_on' => $nextOn, 'over_limit' => $over, 'idle' => $idle];
    }

    /**
     * Will autopay take care of THIS renewal? 'paying' = a debit is with the bank;
     * 'on' = one will be raised on schedule; null = the owner must pay (no mandate,
     * paused, switched off, above the limit, or every attempt has already failed).
     * The reminders use it so an autopay customer is not asked to "renew now".
     */
    public function covers(SubscriptionAccount $account): ?string
    {
        if ($this->inFlight($account)) {
            return 'paying';
        }

        $mandate = $this->mandateFor($account);
        $anchor = $account->current_period_end?->copy()->startOfDay();

        if (! $mandate?->isActive() || ! $anchor || ! $this->enabled()
            || ! $account->selfServeEnabled() || $account->period !== BillingPeriod::Monthly
            || $account->status === AccountStatus::Suspended) {
            return null;
        }

        // Every attempt for this renewal already spent, or the failure already told.
        $tried = $account->orders()->where('collection', CollectionMethod::Autopay->value)->whereDate('autopay_due_on', $anchor)->count();
        if ($tried >= count((array) config('hostelease.autopay.attempts', [3, 1, -1]))
            || ($mandate->failure_notified_for && $mandate->failure_notified_for->isSameDay($anchor))) {
            return null;
        }

        // The operator has billed this renewal: that bill is what gets paid.
        $open = $this->openRenewal($account);
        if ($open && $open->collection !== CollectionMethod::Checkout) {
            return null;
        }

        $total = (float) $this->billing->quoteRenewal($account, BillingPeriod::Monthly->value)['total'];

        return $total > 0 && (int) round($total * 100) <= $mandate->maxAmountPaise() ? 'on' : null;
    }

    // ── internals ───────────────────────────────────────────────────────────

    /** The pending renewal that would still buy this cycle, whoever raised it. */
    protected function openRenewal(SubscriptionAccount $account): ?SubscriptionOrder
    {
        return $account->orders()->where('payment_status', PaymentStatus::Pending->value)
            ->where('kind', OrderKind::Renewal->value)->orderByDesc('id')->get()
            ->first(fn (SubscriptionOrder $o) => $o->wouldExtendCoverage(fresh: true));
    }

    /**
     * The owner's own checkout attempt, no link on it, untouched for an hour — a
     * window they closed, not one they are paying in right now.
     */
    protected function isAbandonedCheckout(SubscriptionOrder $order): bool
    {
        return $order->collection === CollectionMethod::Checkout
            && ! $order->hasLiveLink()
            && $order->updated_at?->lessThan(now()->subHour());
    }

    /**
     * Ask Razorpay what became of that attempt before voiding it (the same rule as
     * CheckoutService::supersede): paid → applied; still running → leave it;
     * nothing → voided so autopay can raise the debit.
     *
     * @return 'cleared'|'paid by an earlier checkout'|'an earlier checkout is still running'|'could not check an earlier checkout'
     */
    protected function clearAbandonedCheckout(SubscriptionOrder $order): string
    {
        if ($order->razorpay_order_id) {
            try {
                $payments = collect($this->razorpay->fetchOrderPayments($order->razorpay_order_id));
            } catch (Throwable $e) {
                return 'could not check an earlier checkout';
            }
            if ($payments->contains(fn ($p) => in_array($p['status'] ?? null, ['created', 'authorized'], true))) {
                return 'an earlier checkout is still running';
            }
            if ($captured = $payments->firstWhere('status', 'captured')) {
                $this->settlement->settle($order, $captured['id'], (int) $captured['amount'], 'online checkout', 'found by autopay');

                return 'paid by an earlier checkout';
            }
        }

        $this->billing->voidOrder($order, 'Replaced by the autopay debit — the owner did not finish this checkout');

        return 'cleared';
    }

    /** Tell the owner (and the operator) once per renewal date that autopay could not take it. */
    protected function notifyFailedOnce(SubscriptionAccount $account, SubscriptionMandate $mandate, Carbon $dueOn, string $why, ?float $amount = null): void
    {
        if ($mandate->failure_notified_for && $mandate->failure_notified_for->isSameDay($dueOn)) {
            return;
        }
        $mandate->update(['failure_notified_for' => $dueOn->toDateString()]);

        $amount ??= (float) $this->billing->quoteRenewal($account, BillingPeriod::Monthly->value)['total'];
        $owner = $account->owner?->name ?? 'A customer';

        $this->notifications->push(null, 'autopay_failed', 'account:'.$account->id,
            $why === 'over_limit' ? "Autopay above its limit — {$owner}" : "Autopay could not take payment — {$owner}",
            $why === 'over_limit'
                ? hostelease_money($amount).' is above the '.hostelease_money($mandate->max_amount).' mandate, so nothing was debited. They have been asked to pay.'
                : 'Every attempt for the renewal on '.$dueOn->format('d M Y').' failed. They have been asked to pay.',
            'warning');

        if ($account->owner?->email) {
            $this->mail($account->owner->email, new AutopayMail($account, $why === 'over_limit' ? 'over_limit' : 'failed', $amount, $dueOn));
        }
    }

    protected function mail(string $to, AutopayMail $mail): void
    {
        try {
            Mail::to($to)->send($mail);
        } catch (Throwable $e) {
            Log::warning('Autopay email could not be sent', ['to' => $to, 'error' => $e->getMessage()]);
        }
    }
}
