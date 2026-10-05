<?php

namespace App\Services\Billing;

use App\Enums\AccountStatus;
use App\Enums\CollectionMethod;
use App\Enums\OrderKind;
use App\Enums\PaymentStatus;
use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionOrderLine;
use App\Services\ActivityLogger;
use App\Services\RazorpayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * S3 — owner self-serve payments, on the same core as the operator's.
 *
 * Design: _artifact/saas_billing_autopay/14_S3_DESIGN.md.
 *
 * THE SHAPE, identical to S2's payment links: an owner checkout is a PENDING ORDER
 * WITH A RAZORPAY CHECKOUT ATTACHED. The order — quantity, lines, amount — is written
 * when the price is SHOWN, and the payment then settles that exact order through
 * PaymentSettlement, the same code links use. The Phase 6 code this replaces did it
 * the other way round: it charged a quote, then rebuilt the order from a fresh quote
 * when the money arrived — so a branch cancelled while checkout was open left a
 * customer who paid for two branches with one renewed (design §1 P2).
 *
 * ONE OPEN DEMAND PER CHARGE SHAPE, across every channel (design §3). Before creating
 * anything, it looks for what is already open and steers the owner to pay THAT:
 * an operator's link, an operator's proforma, or their own earlier attempt.
 *
 * NOTHING MONEY-RELATED COMES FROM THE BROWSER (13 §2). Starting a charge takes a
 * charge shape and a branch or term; confirming takes only Razorpay's three ids. The
 * amount is always the order's, read from the database.
 *
 * Who may call it — owner-only, kill switch, co-admins read-only — is the
 * controller's job. This class assumes it was handed an authorised account.
 */
class CheckoutService
{
    public function __construct(
        protected RazorpayService $razorpay,
        protected AccountBillingService $billing,
        protected PaymentSettlement $settlement,
        protected ActivityLogger $logger,
        protected PaymentLinkService $paymentLinks,
    ) {}

    public function isEnabled(): bool
    {
        return $this->razorpay->isConfigured();
    }

    // -----------------------------------------------------------------
    // Starting a charge
    // -----------------------------------------------------------------

    /**
     * Renew every billable branch, on one date, in one payment.
     *
     * @return array{mode:'link'|'checkout'|'paid'|'held', order:SubscriptionOrder, url?:string, razorpay?:array, message?:string}
     *
     * @throws RuntimeException with an owner-readable message
     */
    public function startRenewal(SubscriptionAccount $account, string $period): array
    {
        if (! in_array($period, ['yearly', 'monthly'], true)) {
            throw new RuntimeException('Choose a yearly or monthly renewal.');
        }

        return DB::transaction(function () use ($account, $period) {
            $this->lock($account);

            if ($this->billing->includedBranches($account)->isEmpty()) {
                throw new RuntimeException('There are no branches on your plan to renew. Contact us if that is not right.');
            }

            // Every pending renewal, newest first. Only one that would still BUY
            // something counts as "the open demand" (S3 audit): an overtaken renewal
            // - its dates already covered by a later payment - is not a bill, and
            // offering it was how a customer paid twice for one year.
            $pending = $account->orders()
                ->where('payment_status', PaymentStatus::Pending->value)
                ->where('kind', OrderKind::Renewal->value)
                ->orderByDesc('id')
                ->get();

            $open = $pending->first(fn (SubscriptionOrder $o) => $o->wouldExtendCoverage(fresh: true));

            // The owner's OWN overtaken attempts are cleared out, so they stop
            // sitting in receivables as money nobody owes. supersede() asks Razorpay
            // first, so one that was actually paid is applied, not voided. Operator
            // charges are left for the operator to void - they are theirs.
            foreach ($pending as $stale) {
                if (($open && $stale->is($open)) || ! $this->isOwnersOwn($stale)) {
                    continue;
                }
                if ($result = $this->supersede($stale)) {
                    return $result;
                }
            }

            if ($open && ! $this->isOwnersOwn($open)) {
                // The OPERATOR has already billed this renewal. Pay that — never a
                // second demand beside it (12 §4). A different term is the owner's to
                // raise with us, not to work around: it may carry a negotiated price.
                if ($open->period?->value !== $period) {
                    throw new RuntimeException(sprintf(
                        'You already have a %s renewal of %s waiting to be paid. Pay that one, or contact us if you would rather switch to %s.',
                        strtolower($open->period?->label() ?? 'pending'),
                        hostelease_money($open->amount),
                        $period,
                    ));
                }

                return $this->payExisting($open);
            }

            if ($open) {
                // The owner's own earlier attempt. Same term and same price → the
                // same order and the same Razorpay order: a retry, not a new charge.
                // Compared on WHICH branches, not how many (S3 audit): one branch
                // removed and another added leaves the count and the price unchanged,
                // and reusing that order would charge for the old set - the new branch
                // left out of the renewal the owner thinks they paid for.
                $fresh = $this->billing->quoteRenewal($account, $period);
                // unique(): a branch with a top-up has two lines on the order.
                $orderBranches = $open->lines()->pluck('branch_id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
                $quoteBranches = collect($fresh['branch_ids'])->map(fn ($id) => (int) $id)->sort()->values()->all();

                $unchanged = $open->period?->value === $period
                    && $orderBranches === $quoteBranches
                    && $open->amountPaise() === (int) round($fresh['total'] * 100);

                if ($unchanged) {
                    return $this->payExisting($open);
                }

                if ($result = $this->supersede($open)) {
                    return $result;   // the earlier attempt had actually been paid
                }
            }

            $order = $this->billing->renewAccount($account, $period, [
                'payment_status' => PaymentStatus::Pending->value,
                'collection' => CollectionMethod::Checkout->value,
                'remarks' => 'Online renewal started by the owner',
            ]);

            return $this->payExisting($order);
        });
    }

    /**
     * Bring one branch onto the account's renewal date, prorated.
     *
     * Only for a branch BEHIND a live, PAID cycle. Without a paid cadence there is
     * nothing to co-terminate with (a trial anchor is not something to buy into),
     * and without a live anchor the right action is Renew-all, which brings every
     * branch along.
     *
     * @return array{mode:'link'|'checkout'|'paid'|'held', order:SubscriptionOrder, url?:string, razorpay?:array, message?:string}
     *
     * @throws RuntimeException with an owner-readable message
     */
    public function startAddBranch(SubscriptionAccount $account, Hostel $branch): array
    {
        return DB::transaction(function () use ($account, $branch) {
            $this->lock($account);
            $account->refresh();
            $branch->refresh();

            if ($branch->isCancelled()) {
                throw new RuntimeException("{$branch->name} is scheduled for removal, so it is not added to your plan.");
            }

            $anchor = $account->current_period_end;
            if (! $account->period?->isPaid() || ! $anchor || ! $anchor->isFuture()) {
                throw new RuntimeException('Renew your plan to bring this branch on — it will renew together with the rest.');
            }
            if ($branch->subscription_end && ! $branch->subscription_end->lt($anchor)) {
                throw new RuntimeException("{$branch->name} is already covered to your renewal date. Nothing to pay.");
            }

            $open = $this->openAddBranchOrder($account, $branch);

            // Overtaken - the branch is already covered past what this would buy.
            // Not a demand: clear it if it is ours, ignore it if it is the operator's.
            if ($open && ! $open->wouldExtendCoverage(fresh: true)) {
                if ($this->isOwnersOwn($open) && ($result = $this->supersede($open))) {
                    return $result;
                }
                $open = null;
            }

            if ($open && ! $this->isOwnersOwn($open)) {
                return $this->payExisting($open);
            }

            if ($open) {
                $fresh = $this->billing->quoteAddBranch($account, $branch);
                if ($open->amountPaise() === (int) round($fresh['breakdown']['final'] * 100)) {
                    return $this->payExisting($open);
                }

                if ($result = $this->supersede($open)) {
                    return $result;
                }
            }

            $order = $this->billing->addBranch($account, $branch, [
                'payment_status' => PaymentStatus::Pending->value,
                'collection' => CollectionMethod::Checkout->value,
                'remarks' => "{$branch->name} added to the plan by the owner",
            ]);

            if ($order->amountPaise() < 100) {
                throw new RuntimeException("There is nothing to pay to bring {$branch->name} onto your renewal date.");
            }

            return $this->payExisting($order);
        });
    }

    /**
     * Pay a charge that is already open — an operator's proforma or link, or the
     * owner's own earlier attempt. What the "Payment due" panel calls.
     *
     * @return array{mode:'link'|'checkout'|'paid'|'held', order:SubscriptionOrder, url?:string, razorpay?:array, message?:string}
     *
     * @throws RuntimeException with an owner-readable message
     */
    public function startForOrder(SubscriptionAccount $account, SubscriptionOrder $order): array
    {
        return DB::transaction(function () use ($account, $order) {
            $this->lock($account);
            $order->refresh();

            if ($order->account_id !== $account->id) {
                throw new RuntimeException('That charge is not on your account.');
            }
            if ($order->payment_status !== PaymentStatus::Pending) {
                throw new RuntimeException($order->payment_status === PaymentStatus::Paid
                    ? 'That charge has already been paid — thank you.'
                    : 'That charge is no longer payable.');
            }
            if (! $order->kind?->isChargeable() || $order->amountPaise() < 100) {
                throw new RuntimeException('There is nothing to pay on that charge.');
            }

            return $this->payExisting($order);
        });
    }

    // -----------------------------------------------------------------
    // Confirming a payment
    // -----------------------------------------------------------------

    /**
     * The browser callback. Takes ONLY Razorpay's three ids — the order is found by
     * the Razorpay order id, inside this account, and the amount is the gateway's.
     *
     * Deliberately not behind the kill switch (design §1 P5): this never STARTS a
     * charge, it settles one the customer has already paid. Telling a paying customer
     * "this is unavailable" was the old behaviour.
     *
     * @return array{state:'applied'|'already'|'pending'|'refused', message:string}
     *
     * @throws RuntimeException when the callback itself cannot be trusted
     */
    public function confirm(SubscriptionAccount $account, string $razorpayOrderId, string $paymentId, string $signature): array
    {
        // Authenticity first — before anything is read on the strength of these ids.
        if (! $this->razorpay->verifySignature($razorpayOrderId, $paymentId, $signature)) {
            throw new RuntimeException("We couldn't verify that payment here. If you were charged it will show up within a few minutes, and you will not be charged twice.");
        }

        // Scoped to THIS account: a valid signature for somebody else's order is
        // still somebody else's order.
        $order = $account->orders()->where('razorpay_order_id', $razorpayOrderId)->first();
        if (! $order) {
            throw new RuntimeException('We could not match that payment to a charge on your account. If you were charged, it will be confirmed within a few minutes.');
        }

        // The gateway's own record is the authority on what was paid. If it cannot be
        // read we do NOT fall back to assuming the quote, as the old code did — the
        // webhook will settle it moments later, so the honest answer is "confirming".
        try {
            $payment = $this->razorpay->fetchPayment($paymentId);
        } catch (RuntimeException $e) {
            Log::warning('Checkout confirm: payment fetch failed — leaving it to the webhook', [
                'order_id' => $order->id, 'payment' => $paymentId, 'error' => $e->getMessage(),
            ]);

            return ['state' => 'pending', 'message' => 'Payment received — we are confirming it now. Your plan will update within a few minutes.'];
        }

        if ($payment['order_id'] && $payment['order_id'] !== $razorpayOrderId) {
            Log::warning('Checkout confirm: payment belongs to a different order', [
                'order_id' => $order->id, 'payment' => $paymentId, 'payment_order' => $payment['order_id'],
            ]);

            throw new RuntimeException("We couldn't verify that payment here. If you were charged it will show up within a few minutes, and you will not be charged twice.");
        }

        // Authorised but not yet captured: the money is not ours yet. order.paid fires
        // on capture, and the webhook settles it then.
        if ($payment['status'] !== 'captured') {
            return ['state' => 'pending', 'message' => 'Payment received — we are confirming it now. Your plan will update within a few minutes.'];
        }

        $result = $this->settleOrder($order, $paymentId, $payment['amount'], 'callback');

        return match ($result) {
            PaymentSettlement::APPLIED, PaymentSettlement::ALREADY => [
                'state' => $result,
                'message' => 'Payment successful — your plan is updated.',
            ],
            default => [
                'state' => 'refused',
                'message' => 'Your payment was received, but we need to check it before it is applied. Our team has been alerted and will be in touch — you have not lost anything.',
            ],
        };
    }

    /**
     * `order.paid` for a checkout order. Resolves OUR order from the notes stamped at
     * creation, cross-checked against the Razorpay order id, then settles it through
     * exactly the same path as the callback.
     */
    public function settleFromWebhook(array $orderEntity, array $paymentEntity): void
    {
        $razorpayOrderId = $orderEntity['id'] ?? null;
        $paymentId = $paymentEntity['id'] ?? null;
        $paidPaise = (int) ($paymentEntity['amount'] ?? 0);

        if (! $paymentId) {
            Log::warning('Checkout webhook: order.paid with no payment id', ['razorpay_order' => $razorpayOrderId]);

            return;
        }

        $order = $this->resolveOrder($orderEntity);

        if (! $order) {
            // Money captured with nothing here to apply it to. 200 and a human —
            // never a 500, which makes Razorpay retry forever.
            $this->settlement->alertUnapplied(null, $paymentId, $paidPaise,
                'no charge here matches Razorpay order '.($razorpayOrderId ?? 'unknown'));

            return;
        }

        $this->settleOrder($order, $paymentId, $paidPaise, 'webhook');
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /** Settle, then record what is checkout-specific. */
    protected function settleOrder(SubscriptionOrder $order, string $paymentId, int $paidPaise, string $source): string
    {
        $result = $this->settlement->settle($order, $paymentId, $paidPaise, 'online checkout', $source);

        if ($result === PaymentSettlement::APPLIED) {
            // While pending, `collection` records who OPENED the charge (checkout =
            // the owner). Once paid it records how the money ARRIVED — so an
            // operator's proforma the owner paid online now says so.
            $order->update(['collection' => CollectionMethod::Checkout->value]);

            $this->logger->log(
                'subscription.paid',
                "Online checkout paid ({$source}) — {$order->invoiceNumber()} · ".hostelease_money($order->amount),
                $order->fresh(),
                ['payment' => $paymentId, 'razorpay_order_id' => $order->razorpay_order_id],
            );

            // This payment may have made an operator's live link pointless - an
            // add-branch link for a branch this renewal just covered. Kill it before
            // the customer taps it from an old SMS and pays for nothing.
            if ($order->account) {
                $this->paymentLinks->cancelOvertakenLinks($order->account, $order->id);
            }
        }

        return $result;
    }

    /**
     * Hand the owner the instrument for an open charge: its live link if the operator
     * sent one, otherwise a Razorpay Checkout on that same order.
     */
    protected function payExisting(SubscriptionOrder $order): array
    {
        // The last gate before money (S3 audit). Whatever route led here - the
        // Payment due list, a reused attempt, an operator's proforma or link - never
        // open a payment for a charge whose every date is already covered.
        $state = $order->coverageState(fresh: true);
        if ($state === 'stale') {
            throw new RuntimeException('Part of this charge has already been paid separately, so its amount is out of date. Start the renewal again and you will see the correct total.');
        }
        if ($state !== 'extends') {
            throw new RuntimeException('Everything this charge covers is already paid up, so there is nothing to pay on it. If you think that is wrong, please contact us.');
        }

        // One live instrument per order. A live link means the operator's link IS
        // how this charge gets paid; opening a checkout beside it would give the
        // customer two ways to pay one bill.
        if ($order->hasLiveLink()) {
            // The browser is sent straight to this URL, and it came from Razorpay's
            // API — so only ever an https one. Anything else means the stored link is
            // not what we think it is: stop, rather than redirect a customer to it.
            if (! str_starts_with((string) $order->payment_link_url, 'https://')) {
                throw new RuntimeException('This payment link could not be opened. Please contact us and we will send you a fresh one.');
            }

            return ['mode' => 'link', 'order' => $order, 'url' => $order->payment_link_url];
        }

        $this->assertConfigured();

        if (! $order->razorpay_order_id) {
            $rzp = $this->razorpay->createOrder(
                $order->amountPaise(),
                (string) $order->public_id,
                $this->notes($order),
            );

            // Never trust a 200 that does not name an order — the same rule as S2's
            // empty-link guard. Throwing rolls the pending order back with it.
            if (! filled($rzp['id'] ?? null)) {
                throw new RuntimeException('Online payment could not be started just now. Nothing has been charged — please try again in a moment.');
            }

            $order->update(['razorpay_order_id' => $rzp['id']]);
        }

        $owner = $order->account?->owner;

        return [
            'mode' => 'checkout',
            'order' => $order->fresh(),
            'razorpay' => [
                'key' => $this->razorpay->keyId(),
                'order_id' => $order->razorpay_order_id,
                // The ORDER's amount, read from the database — never a figure the
                // browser computed. Razorpay rejects a checkout whose amount differs
                // from the order's, which is a second lock on the same door.
                'amount' => $order->amountPaise(),
                'currency' => 'INR',
                'name' => config('hostelease.company.name', config('app.name')),
                'description' => $this->description($order),
                'prefill' => array_filter([
                    'name' => $owner?->name,
                    'email' => $owner?->email,
                    'contact' => $owner?->mobile,
                ]),
            ],
        ];
    }

    /**
     * Void the owner's own abandoned attempt so a fresh one can be priced — but ask
     * Razorpay first whether it was in fact paid. Razorpay orders cannot be
     * cancelled, and a webhook can lag, so "abandoned" may really mean "paid a
     * minute ago". Voiding that would turn a payment into a refund case.
     *
     * @return array|null a result to return if the earlier attempt turned out to be paid
     *
     * @throws RuntimeException when Razorpay cannot be asked — better to wait than to guess
     */
    protected function supersede(SubscriptionOrder $open): ?array
    {
        if ($open->razorpay_order_id) {
            try {
                $payments = $this->razorpay->fetchOrderPayments($open->razorpay_order_id);
            } catch (RuntimeException $e) {
                throw new RuntimeException('We could not check your earlier payment attempt just now. Please try again in a moment — you will not be charged twice.');
            }

            // A payment still IN FLIGHT - started or authorised, not yet captured -
            // means "wait", not "abandoned" (S3 audit). Voiding now would land its
            // capture, seconds later, on a voided order: a refund case made by us.
            if (collect($payments)->contains(fn (array $p) => in_array($p['status'], ['created', 'authorized'], true))) {
                throw new RuntimeException('Your earlier payment attempt is still being processed. Give it a minute, then refresh this page - you will not be charged twice.');
            }

            $captured = collect($payments)->firstWhere('status', 'captured');
            if ($captured) {
                // RETURN, never throw: this runs inside the caller's transaction, so
                // a throw here would roll back the very settlement it just made — the
                // payment would be found, applied, and silently un-applied.
                $result = $this->settleOrder($open, $captured['id'], $captured['amount'], 'found on retry');

                // Only say "paid" when it was. A refused settlement (a short capture,
                // say) has raised an alert, and the owner must hear that we are
                // checking - not that everything is fine.
                return $result === PaymentSettlement::REFUSED
                    ? [
                        'mode' => 'held',
                        'order' => $open->fresh(),
                        'message' => 'We found a payment from your earlier attempt that needs a check before it is applied. Our team has been alerted and will be in touch - please do not pay again.',
                    ]
                    : [
                        'mode' => 'paid',
                        'order' => $open->fresh(),
                        'message' => 'Good news - your earlier payment had already gone through, so there is nothing more to pay. Your plan is updated.',
                    ];
            }
        }

        $this->billing->voidOrder($open, 'Superseded by a newer online checkout — the price had changed');

        return null;
    }

    /**
     * Who opened a still-pending charge. `collection = checkout` on a PENDING order
     * means the owner did; anything else (offline proforma, payment link) means the
     * operator did. Settlement rewrites it to how the money arrived, but only once
     * the order is paid — so this is reliable for exactly the orders it is asked about.
     */
    protected function isOwnersOwn(SubscriptionOrder $order): bool
    {
        return $order->collection === CollectionMethod::Checkout;
    }

    /** The open add-branch charge for THIS branch, whoever opened it. */
    protected function openAddBranchOrder(SubscriptionAccount $account, Hostel $branch): ?SubscriptionOrder
    {
        $candidates = $account->orders()
            ->where('payment_status', PaymentStatus::Pending->value)
            ->where('kind', OrderKind::AddBranch->value)
            ->pluck('id');

        if ($candidates->isEmpty()) {
            return null;
        }

        $id = SubscriptionOrderLine::whereIn('order_id', $candidates)
            ->where('branch_id', $branch->id)
            ->max('order_id');

        return $id ? SubscriptionOrder::find($id) : null;
    }

    /**
     * Find OUR order from a Razorpay order entity. The `he_order_id` note is the
     * fast path; the stored razorpay_order_id is the cross-check, so a note pointing
     * at the wrong order cannot redirect a payment.
     */
    protected function resolveOrder(array $orderEntity): ?SubscriptionOrder
    {
        $razorpayOrderId = $orderEntity['id'] ?? null;
        $noteId = $orderEntity['notes']['he_order_id'] ?? null;

        if ($noteId && ctype_digit((string) $noteId)) {
            $order = SubscriptionOrder::with('account.owner')->find((int) $noteId);
            // EXACT match only (S3 audit). Accepting a note on an order with no
            // Razorpay id stored let a note alone choose which charge got paid.
            if ($order && $order->razorpay_order_id !== null && $order->razorpay_order_id === $razorpayOrderId) {
                return $order;
            }
        }

        return $razorpayOrderId
            ? SubscriptionOrder::with('account.owner')->where('razorpay_order_id', $razorpayOrderId)->first()
            : null;
    }

    /**
     * Serialise on the account row, then re-read — the S2 audit's D1 pattern — and
     * refuse to START anything on an account the operator has put on hold.
     *
     * Suspended is an operator decision (a dispute, a hold), and computeStatus()
     * keeps it Suspended whatever is paid. So a suspended owner who paid would be
     * charged and stay blocked. The page hides the buttons; this is the server-side
     * half, so a crafted request cannot take money for a service it will not give.
     * Paying a link the OPERATOR sent stays possible — that never comes through here.
     */
    protected function lock(SubscriptionAccount $account): void
    {
        SubscriptionAccount::query()->whereKey($account->getKey())->lockForUpdate()->first();
        $account->refresh();

        if ($account->status === AccountStatus::Suspended) {
            throw new RuntimeException('Your account is on hold, so online payments are paused. Please contact us and we will sort it out with you.');
        }
    }

    /** @throws RuntimeException */
    protected function assertConfigured(): void
    {
        if (! $this->razorpay->isConfigured()) {
            throw new RuntimeException('Online payment is not available right now. Please contact us and we will renew your plan for you.');
        }
    }

    /**
     * Back-references on the Razorpay order. Every key `he_`-prefixed, plus
     * `type = checkout`, for the same reason as S2's link notes: the webhook routes
     * on these, and the retired Phase 6 arms read `branch_id`/`period` — names a
     * checkout order must never carry.
     *
     * @return array<string,string>
     */
    protected function notes(SubscriptionOrder $order): array
    {
        return array_filter([
            'type' => 'checkout',
            'he_order_id' => (string) $order->id,
            'he_order_ref' => (string) $order->public_id,
            'he_account_id' => (string) $order->account_id,
            'he_kind' => $order->kind?->value,
            'he_invoice' => $order->invoiceNumber(),
        ]);
    }

    protected function description(SubscriptionOrder $order): string
    {
        $branches = $order->quantity.' branch'.($order->quantity === 1 ? '' : 'es');

        return match ($order->kind) {
            OrderKind::Renewal => ($order->period?->label() ?? 'Plan').' renewal · '.$branches,
            OrderKind::AddBranch => 'Branch added to your plan',
            OrderKind::Align => 'Bringing '.$branches.' onto your renewal date',
            default => 'Subscription · '.$order->invoiceNumber(),
        };
    }
}
