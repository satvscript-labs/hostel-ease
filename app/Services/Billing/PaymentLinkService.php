<?php

namespace App\Services\Billing;

use App\Enums\CollectionMethod;
use App\Enums\OrderKind;
use App\Enums\PaymentLinkStatus;
use App\Enums\PaymentStatus;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionOrderLine;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use App\Services\RazorpayService;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * S2 — operator-initiated online collection via Razorpay Payment Links.
 *
 * Design: _artifact/saas_billing_autopay/10_S2_DESIGN.md.
 *
 * THE WHOLE IDEA IN ONE SENTENCE: a payment link is a PENDING ORDER WITH A URL
 * ATTACHED. So this class contains no pricing, no proration, no coverage and no
 * anchor logic — it creates links, tracks their state, and when one is paid it
 * hands the order to AccountBillingService::acceptOrder(), which is the same
 * method the operator's "the cash arrived" button has used since S1. There is
 * exactly one way coverage is granted in this system and a payment link does not
 * add a second one.
 *
 * DEPENDENCY DIRECTION MATTERS: this depends on AccountBillingService, never the
 * reverse. That is why "cancel the live link" on accept/void lives in the
 * controller (which knows about both) rather than inside acceptOrder() — a
 * circular binding here would be a container-resolution bug waiting to happen.
 */
class PaymentLinkService
{
    /** Razorpay's floor is 15 minutes; stay clear of it so clock skew cannot bite. */
    private const MIN_EXPIRY_MINUTES = 20;

    /** Razorpay's ceiling is 6 months. */
    private const MAX_EXPIRY_DAYS = 180;

    public function __construct(
        protected RazorpayService $razorpay,
        protected AccountBillingService $billing,
        protected ActivityLogger $logger,
        protected NotificationService $notifications,
        protected PaymentSettlement $settlement,
    ) {}

    public function isEnabled(): bool
    {
        return $this->razorpay->isConfigured();
    }

    // -----------------------------------------------------------------
    // Issuing
    // -----------------------------------------------------------------

    /**
     * Create a charge and its payment link as ONE atomic act.
     *
     * $createOrder is a closure that produces the PENDING order — in practice a
     * call to renewAccount() / addBranch() / align() with `payment_status =>
     * pending`, so the link is always collecting for a charge the normal engine
     * priced. The quote is never computed here.
     *
     * The Razorpay call happens INSIDE the transaction on purpose (design §6 BP4):
     * if link creation fails, the order rolls back and no orphan proforma is left
     * sitting in receivables as money nobody was ever asked for. The HTTP client
     * carries a 15s timeout so a hung gateway cannot hold the transaction open.
     *
     * @param  Closure(): SubscriptionOrder  $createOrder
     *
     * @throws RuntimeException with an operator-readable message.
     */
    public function collect(Closure $createOrder): SubscriptionOrder
    {
        // Pre-flight the commonest failure before taking a lock.
        $this->assertConfigured();

        return DB::transaction(fn () => $this->attach($createOrder()));
    }

    /**
     * Attach a link to an order that already exists — the "issue a link for this
     * proforma" and "re-issue after expiry" paths.
     *
     * @throws RuntimeException with an operator-readable message.
     */
    public function issue(SubscriptionOrder $order): SubscriptionOrder
    {
        $this->assertConfigured();

        return DB::transaction(fn () => $this->attach($order));
    }

    /**
     * The guards, the call, and the stamp. Runs inside a transaction in both
     * entry points, so every `throw` below rolls back whatever the caller created.
     *
     * @throws RuntimeException
     */
    protected function attach(SubscriptionOrder $order): SubscriptionOrder
    {
        $account = $order->account;

        if (! $account) {
            throw new RuntimeException('That charge is not attached to a customer account, so there is nobody to send a link to.');
        }

        // SERIALISE ON THE ACCOUNT ROW before reading what links it already has.
        // Every guard below is a read-then-write, so without a lock two operators
        // clicking "Create link" at the same moment — or one operator double-clicking
        // — both see "no live link", both pass, and the customer receives two demands
        // for one charge. A no-op on SQLite (single writer); real on MySQL, which is
        // production.
        SubscriptionAccount::query()->whereKey($account->getKey())->lockForUpdate()->first();

        // ...and then RE-READ the order. The lock serialises the two requests, but
        // the loser is still holding the model instance it loaded BEFORE waiting —
        // on which hasLiveLink() is false, because the winner's link was stamped
        // after that read. Without this, a double-clicked button issues two links
        // and the second overwrites the first's id: the first link stays live and
        // payable at Razorpay with nothing here pointing at it.
        $order->refresh();

        // ── Guards (design §6 BP11). Each says what to do about it, because an
        // operator reading "refused" with no reason will just click again. ──
        if ($order->payment_status === PaymentStatus::Paid) {
            throw new RuntimeException('That charge is already paid — there is nothing to collect.');
        }
        if ($order->payment_status !== PaymentStatus::Pending) {
            throw new RuntimeException('A payment link can only be issued for a charge that is awaiting payment (this one is '.$order->payment_status->label().').');
        }
        if ($order->amountPaise() < 100) {
            throw new RuntimeException('A payment link needs at least ₹1. This charge is '.hostelease_money($order->amount).', so collect nothing and accept it instead.');
        }
        if ($order->hasLiveLink()) {
            throw new RuntimeException('This charge already has a live payment link. Cancel it before issuing another — two live links for one charge is how a customer pays twice.');
        }

        // The owner has this charge open in online checkout (S3). Razorpay orders
        // cannot be cancelled, so that checkout stays payable — a link beside it
        // would give the customer two ways to pay one bill.
        if ($order->hasOpenCheckout()) {
            throw new RuntimeException('The customer has opened this charge for online checkout, which stays payable. Ask them to complete it there — or void this charge and quote a fresh one if it needs to change.');
        }

        $this->assertNoDuplicateLiveLink($account, $order);

        $attempt = ((int) $order->payment_link_attempts) + 1;

        // `payment_link_attempts` is an unsigned TINYINT, so 256 would be a hard
        // write error under MySQL's strict mode — a 500 on a money action. Nobody
        // will re-issue one charge 255 times, which is exactly why this should say
        // something human rather than fall over.
        if ($attempt > 255) {
            throw new RuntimeException('This charge has had 255 payment links issued against it. Something is wrong — void it and raise a fresh charge rather than issuing another link.');
        }
        $reference = $this->reference($order, $attempt);
        $expireBy = $this->expiryTimestamp();

        $link = $this->razorpay->createPaymentLink(
            amountPaise: $order->amountPaise(),
            description: $this->description($order),
            referenceId: $reference,
            customer: [
                'name' => $account->owner?->name,
                'email' => $account->owner?->email,
                'contact' => $account->owner?->mobile,
            ],
            notes: $this->notes($order),
            expireBy: $expireBy,
            notifySms: (bool) config('hostelease.payment_links.notify_sms', true),
            notifyEmail: (bool) config('hostelease.payment_links.notify_email', true),
            remindersEnabled: (bool) config('hostelease.payment_links.reminders', true),
        );

        // A 200 with no id or no URL is not a usable link, and storing it would be
        // worse than failing: `payment_link_id = ''` is not null, so hasLiveLink()
        // would report a live link that does not exist — blocking every re-issue and
        // offering the operator a Send button with nothing behind it. Throwing here
        // rolls the whole charge back, which is the honest outcome.
        if (! filled($link['id']) || ! filled($link['short_url'])) {
            throw new RuntimeException('Razorpay accepted the request but returned no usable payment link. Nothing has been charged or recorded — try again, or record this payment offline.');
        }

        $order->update([
            'payment_link_id' => $link['id'],
            'payment_link_url' => $link['short_url'],
            'payment_link_ref' => $reference,
            'payment_link_status' => PaymentLinkStatus::tryFrom($link['status'])?->value
                ?? PaymentLinkStatus::Created->value,
            'payment_link_expires_at' => $link['expire_by'] ? Carbon::createFromTimestamp($link['expire_by']) : null,
            'payment_link_attempts' => $attempt,
            // How the money is being collected, now that we know (S1's CollectionMethod).
            'collection' => CollectionMethod::Link->value,
        ]);

        $this->logger->log(
            'subscription.update',
            ($attempt > 1 ? "Re-issued payment link (attempt {$attempt})" : 'Issued payment link')
                .' for '.$order->invoiceNumber().' — '.hostelease_money($order->amount),
            $order,
            ['payment_link_id' => $link['id'], 'reference_id' => $reference],
        );

        return $order->fresh();
    }

    // -----------------------------------------------------------------
    // Lifecycle
    // -----------------------------------------------------------------

    /**
     * Kill a live link so it can never take money again.
     *
     * Called directly by the operator, and — crucially — whenever a charge is
     * settled some other way. The customer who pays by bank transfer and then taps
     * the week-old link in their inbox is design §6 BP2, and this is the only thing
     * standing between them and paying twice.
     *
     * NEVER THROWS for an upstream refusal. Razorpay rejects a cancel on a link it
     * considers already paid/expired/cancelled, and the operator's action (accept
     * the payment, void the order) must not fail because of a state they cannot
     * control. Instead the real status is read back and stored, so the discrepancy
     * becomes visible rather than silently assumed away.
     *
     * @return bool whether Razorpay confirmed the cancellation
     */
    public function cancel(SubscriptionOrder $order, string $reason = '', bool $log = true): bool
    {
        if (! $order->payment_link_id) {
            return false;
        }

        if (! $this->razorpay->isConfigured()) {
            Log::warning('Payment link cancel skipped: Razorpay is not configured', ['order_id' => $order->id]);

            return false;
        }

        try {
            $result = $this->razorpay->cancelPaymentLink($order->payment_link_id);
            $status = PaymentLinkStatus::tryFrom($result['status']) ?? PaymentLinkStatus::Cancelled;
            $order->update(['payment_link_status' => $status->value]);

            if ($log) {
                $this->logger->log(
                    'subscription.update',
                    "Cancelled the payment link on {$order->invoiceNumber()}".($reason ? " — {$reason}" : ''),
                    $order,
                );
            }

            return true;
        } catch (RuntimeException $e) {
            // Razorpay would not cancel it. Find out what it actually thinks the
            // link is, and record THAT — a link Razorpay reports as `paid` while our
            // order is pending is precisely the missed-webhook case the operator
            // needs to see (design §6 BP6), not something to bury in a log line.
            Log::warning('Payment link could not be cancelled at Razorpay', [
                'order_id' => $order->id,
                'payment_link_id' => $order->payment_link_id,
                'error' => $e->getMessage(),
            ]);

            $this->reconcileStatus($order);

            return false;
        }
    }

    /**
     * Cancel a live link because the charge has been settled elsewhere. Quiet by
     * design: this runs as a side effect of accept/void, so it must never surface
     * an error to the operator or abort their action.
     */
    public function cancelIfLive(SubscriptionOrder $order, string $reason): void
    {
        if (! $order->hasLiveLink()) {
            return;
        }

        $this->cancel($order, $reason);
    }

    /**
     * Ask Razorpay to re-send a live link by SMS or email — the free nudge.
     *
     * @throws RuntimeException with an operator-readable message.
     */
    public function resend(SubscriptionOrder $order, string $medium = 'sms'): void
    {
        $this->assertConfigured();

        if (! $order->hasLiveLink()) {
            throw new RuntimeException('There is no live payment link on this charge to re-send.');
        }

        $this->razorpay->notifyPaymentLink($order->payment_link_id, $medium);

        $this->logger->log(
            'subscription.update',
            "Re-sent the payment link for {$order->invoiceNumber()} by {$medium}",
            $order,
        );
    }

    /**
     * Read the link's true state from Razorpay and reconcile — including APPLYING a
     * payment the webhook never delivered.
     *
     * This is the safety valve for design §6 BP6, the most likely production
     * failure in the whole phase: an unsubscribed webhook event or a mismatched
     * secret fails *silently*, so a customer pays and nothing happens. Because the
     * fetch response carries `payments[].payment_id`, this can do the full apply
     * through the identical path the webhook uses — turning a silent money bug into
     * one button.
     *
     * @return array{status:?PaymentLinkStatus, applied:bool, message:string}
     *
     * @throws RuntimeException when Razorpay cannot be reached.
     */
    public function checkWithRazorpay(SubscriptionOrder $order): array
    {
        $this->assertConfigured();

        if (! $order->payment_link_id) {
            throw new RuntimeException('There is no payment link on this charge to check.');
        }

        $link = $this->razorpay->fetchPaymentLink($order->payment_link_id);
        $status = PaymentLinkStatus::tryFrom($link['status']);

        $order->update([
            'payment_link_status' => $status?->value ?? $order->payment_link_status?->value,
            'payment_link_expires_at' => $link['expire_by']
                ? Carbon::createFromTimestamp($link['expire_by'])
                : $order->payment_link_expires_at,
        ]);

        if ($order->payment_status === PaymentStatus::Paid) {
            return [
                'status' => $status,
                'applied' => false,
                'message' => 'Razorpay reports this link as '.($status?->label() ?? $link['status'])
                    .'. The charge is already recorded as paid here, so nothing changed.',
            ];
        }

        // A captured payment is the thing worth acting on, whatever the link's
        // headline status says — `partially_paid` also carries captures, and those
        // must be measured against the full amount, not applied on sight.
        $captured = collect($link['payments'])
            ->filter(fn (array $p) => filled($p['payment_id']))
            ->sortByDesc('amount')
            ->first();

        if (! $captured) {
            return [
                'status' => $status,
                'applied' => false,
                'message' => 'Razorpay reports this link as '.($status?->label() ?? $link['status'])
                    .' with no payment captured against it.',
            ];
        }

        $paidPaise = max((int) $link['amount_paid'], (int) $captured['amount']);
        $applied = $this->applyPaid($order, (string) $captured['payment_id'], $paidPaise, 'manual check');

        return [
            'status' => $status,
            'applied' => $applied,
            'message' => $applied
                ? 'Razorpay had taken this payment and we had not recorded it — the charge is now paid and coverage is updated.'
                : 'Razorpay reports a payment on this link that could not be applied. A Super Admin alert has been raised with the details.',
        ];
    }

    /** Record what Razorpay currently thinks a link is, writing nothing else. */
    public function reconcileStatus(SubscriptionOrder $order): ?PaymentLinkStatus
    {
        if (! $order->payment_link_id || ! $this->razorpay->isConfigured()) {
            return $order->payment_link_status;
        }

        try {
            $link = $this->razorpay->fetchPaymentLink($order->payment_link_id);
            $status = PaymentLinkStatus::tryFrom($link['status']);

            if ($status) {
                $order->update(['payment_link_status' => $status->value]);
            }

            return $status ?? $order->payment_link_status;
        } catch (RuntimeException $e) {
            Log::warning('Payment link status could not be read from Razorpay', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return $order->payment_link_status;
        }
    }

    // -----------------------------------------------------------------
    // Applying money
    // -----------------------------------------------------------------

    /**
     * Apply a payment that arrived on a link — from the webhook or the manual check.
     *
     * The money rules are NOT here any more: they live in PaymentSettlement, which
     * owner checkout (S3) shares, so the two channels cannot apply money by
     * different rules. This method only adds what is link-specific — the link's own
     * status, and the audit line.
     *
     * @param  int  $paidPaise  what Razorpay says was actually paid
     * @param  string  $source  for the audit trail: 'webhook' | 'manual check'
     * @return bool whether the charge is now paid by this payment
     */
    public function applyPaid(SubscriptionOrder $order, string $paymentId, int $paidPaise, string $source = 'webhook'): bool
    {
        $result = $this->settlement->settle($order, $paymentId, $paidPaise, 'payment link', $source);

        if ($result === PaymentSettlement::REFUSED) {
            return false;
        }

        if ($result === PaymentSettlement::APPLIED) {
            $order->update(['payment_link_status' => PaymentLinkStatus::Paid->value]);

            $this->logger->log(
                'subscription.paid',
                "Payment link paid ({$source}) — {$order->invoiceNumber()} · ".hostelease_money($order->amount),
                $order->fresh(),
                ['payment' => $paymentId, 'payment_link_id' => $order->payment_link_id],
            );
        }

        return true;
    }

    /** Record that a link lapsed. The CHARGE is still owed — only the link died. */
    public function markExpired(SubscriptionOrder $order): void
    {
        $order->update(['payment_link_status' => PaymentLinkStatus::Expired->value]);

        // Worth telling the operator about: a link that quietly lapses is a renewal
        // that quietly stops being chased, and the order stays in receivables
        // looking exactly like one nobody has sent yet.
        $this->notifications->push(
            null,
            'payment_link_expired',
            'plink_expired:'.$order->id,
            'A payment link expired unpaid',
            ($order->account?->owner?->name ?? 'A customer')."'s link for ".hostelease_money($order->amount)
                ." ({$order->invoiceNumber()}) expired before it was paid. Re-issue it, or collect another way.",
            'warning',
        );

        $this->logger->log('subscription.update', "Payment link expired unpaid — {$order->invoiceNumber()}", $order);
    }

    /** Record that a link was cancelled upstream (from the Razorpay dashboard, say). */
    public function markCancelled(SubscriptionOrder $order): void
    {
        $order->update(['payment_link_status' => PaymentLinkStatus::Cancelled->value]);

        $this->logger->log('subscription.update', "Payment link cancelled — {$order->invoiceNumber()}", $order);
    }

    /** A partial payment arrived on a link we created with partials disabled. */
    public function markPartiallyPaid(SubscriptionOrder $order, int $paidPaise): void
    {
        $order->update(['payment_link_status' => PaymentLinkStatus::PartiallyPaid->value]);

        // The de-dupe token must be unique PER ORDER, not the literal '(partial)' it
        // used to be: the alert signature is built from this, so two partially-paid
        // links on different customers collided and the second alert overwrote the
        // first — losing one of them silently. There is no payment id to key on here,
        // so the order is the key.
        $this->alertUnapplied(
            $order,
            'partial-on-order-'.$order->id,
            $paidPaise,
            'the link was partly paid, and partial payments are not enabled on our links',
            'A partial payment of ',
        );
    }

    // -----------------------------------------------------------------
    // Resolution
    // -----------------------------------------------------------------

    /**
     * Find OUR order from a Razorpay payment-link entity, three ways, cheapest
     * first. Three because a webhook that cannot find its order is money captured
     * with nothing to apply it to, and that is worth redundancy.
     *
     *  1. `payment_link_id` — the normal path, and UNIQUE, so it cannot be ambiguous.
     *  2. `notes.he_order_id` — survives even if our stamp never committed.
     *  3. `reference_id` — our own, unique per link.
     */
    public function resolveOrder(array $linkEntity): ?SubscriptionOrder
    {
        $linkId = $linkEntity['id'] ?? null;

        if ($linkId) {
            $order = SubscriptionOrder::with('account.owner')->where('payment_link_id', $linkId)->first();
            if ($order) {
                return $order;
            }
        }

        $noteId = $linkEntity['notes']['he_order_id'] ?? null;
        if ($noteId && ctype_digit((string) $noteId)) {
            $order = SubscriptionOrder::with('account.owner')->find((int) $noteId);
            if ($order) {
                return $order;
            }
        }

        $reference = $linkEntity['reference_id'] ?? null;
        if ($reference) {
            return SubscriptionOrder::with('account.owner')->where('payment_link_ref', $reference)->first();
        }

        return null;
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /**
     * Refuse a link that duplicates a demand already out with the customer.
     *
     * The rule is per CHARGE SHAPE, because "one live link per order" is not enough
     * on its own — nothing stopped the operator quoting the *same work* twice as two
     * separate orders, each with its own link. Measured in the S2 audit: two
     * add-branch links for the same branch, and two align links, both sat live at
     * once, and a customer who tapped both paid twice for identical coverage. (The
     * ledger absorbs it without double-granting — addLine() never shortens, so the
     * second payment buys a date the branch already has — which makes it WORSE, not
     * better: the money is gone and there is nothing to show for it.)
     *
     *  · renewal    — one per account. A renewal always covers the whole billable
     *                 estate, so two are necessarily the same demand.
     *  · align      — one per account, for the same reason.
     *  · add_branch — one per account PER BRANCH. Two different branches being added
     *                 at once is legitimate and stays allowed.
     *
     * @throws RuntimeException
     */
    protected function assertNoDuplicateLiveLink(SubscriptionAccount $account, SubscriptionOrder $order): void
    {
        if (! in_array($order->kind, [OrderKind::Renewal, OrderKind::Align, OrderKind::AddBranch], true)) {
            return;
        }

        $rivals = $account->orders()
            ->withLiveLink()
            ->where('kind', $order->kind->value)
            ->whereKeyNot($order->getKey())
            ->get();

        if ($rivals->isEmpty()) {
            return;
        }

        if ($order->kind === OrderKind::AddBranch) {
            // Narrow it to the same branch. One grouped query, not one per rival.
            $mine = $order->lines()->pluck('branch_id')->all();
            $theirs = SubscriptionOrderLine::whereIn('order_id', $rivals->modelKeys())
                ->whereIn('branch_id', $mine)
                ->pluck('order_id')
                ->unique();

            $rivals = $rivals->whereIn('id', $theirs->all());

            if ($rivals->isEmpty()) {
                return;
            }
        }

        $rival = $rivals->first();
        $what = match ($order->kind) {
            OrderKind::Renewal => 'renewal link',
            OrderKind::Align => 'alignment link',
            default => 'payment link for that branch',
        };

        throw new RuntimeException(
            "This customer already has a live {$what} ({$rival->invoiceNumber()} · "
                .hostelease_money($rival->amount).'). Cancel that one first, or collect against it — '
                .'two live links for the same charge is how a customer pays twice.'
        );
    }

    /** @throws RuntimeException */
    protected function assertConfigured(): void
    {
        if (! $this->razorpay->isConfigured()) {
            throw new RuntimeException('Razorpay is not configured, so online collection is unavailable. Set RAZORPAY_ENABLED, RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET, then record this payment offline in the meantime.');
        }
    }

    /**
     * `reference_id` is UNIQUE at Razorpay, which is a free second layer of
     * idempotency on attempt 1 — it refuses a duplicate link for the same charge
     * before we even get to our own guard. The flip side is that a RE-ISSUE must
     * vary it, hence the attempt suffix. Capped at 40 chars by the API; a ULID is
     * 26, so there is room for the suffix.
     */
    protected function reference(SubscriptionOrder $order, int $attempt): string
    {
        $base = $order->public_id ?: ('order-'.$order->id);

        return $attempt > 1 ? $base.'-'.$attempt : $base;
    }

    /** What the customer reads on Razorpay's hosted page. */
    protected function description(SubscriptionOrder $order): string
    {
        $company = config('hostelease.company.name', 'HostelEase');
        $what = match ($order->kind) {
            OrderKind::Renewal => $order->quantity.' branch'.($order->quantity === 1 ? '' : 'es').' · '.($order->period?->label() ?? 'subscription').' renewal',
            OrderKind::AddBranch => 'New branch added to your subscription',
            OrderKind::Align => 'Bringing '.$order->quantity.' branch'.($order->quantity === 1 ? '' : 'es').' onto your renewal date',
            OrderKind::Purchase => $order->quantity.' branch'.($order->quantity === 1 ? '' : 'es').' · subscription',
            default => 'Subscription charge',
        };

        return "{$company} — {$what} ({$order->invoiceNumber()})";
    }

    /**
     * Our back-reference, carried on the link and on the Razorpay order behind it.
     *
     * EVERY KEY IS PREFIXED `he_`, and that is a safety measure rather than a
     * naming preference (design §6 BP1). Razorpay may propagate a link's notes to
     * its underlying order, and our `order.paid` handler reads `notes.branch_id` +
     * `notes.period` to decide what to do. If link notes used those names, one
     * payment could be applied twice — once by each webhook arm. With `he_`
     * prefixes the legacy arm's required keys are simply never present, so even an
     * unguarded fall-through can only log and return.
     *
     * @return array<string,string>
     */
    protected function notes(SubscriptionOrder $order): array
    {
        return array_filter([
            'type' => 'payment_link',
            'he_order_id' => (string) $order->id,
            'he_order_ref' => (string) $order->public_id,
            'he_account_id' => (string) $order->account_id,
            'he_kind' => $order->kind?->value,
            'he_invoice' => $order->invoiceNumber(),
        ]);
    }

    /**
     * Clamp the configured window into Razorpay's own limits (at least 15 minutes
     * ahead, at most 6 months) so a mis-set env value cannot make every link
     * creation fail with a validation error.
     */
    protected function expiryTimestamp(): int
    {
        $days = (int) config('hostelease.payment_links.expiry_days', 7);
        $days = max(0, min($days, self::MAX_EXPIRY_DAYS));

        $expiry = Carbon::now()->addDays($days);
        $floor = Carbon::now()->addMinutes(self::MIN_EXPIRY_MINUTES);

        return ($expiry->lessThan($floor) ? $floor : $expiry)->getTimestamp();
    }

    /** Delegates to the shared settlement — one alert format for every channel. */
    protected function alertUnapplied(?SubscriptionOrder $order, string $paymentId, int $paidPaise, string $why, string $lead = ''): void
    {
        $this->settlement->alertUnapplied($order, $paymentId, $paidPaise, $why, $lead);
    }

    /** A paid link we could not match to any order at all. */
    public function alertUnresolved(array $linkEntity, string $paymentId, int $paidPaise): void
    {
        Log::error('Payment link webhook: no local order could be resolved', [
            'payment_link_id' => $linkEntity['id'] ?? null,
            'reference_id' => $linkEntity['reference_id'] ?? null,
            'payment' => $paymentId,
            'captured_paise' => $paidPaise,
        ]);

        $this->notifications->push(
            null,
            'payment_unapplied',
            'payment_unapplied:'.$paymentId,
            'Payment received for an unknown charge — manual review',
            hostelease_money($paidPaise / 100).' was paid on Razorpay payment link '
                .($linkEntity['id'] ?? 'unknown').' (reference '.($linkEntity['reference_id'] ?? 'none')
                .'), but no matching charge exists here. Find the customer in Razorpay\'s dashboard and record the payment from Account 360.',
            'danger',
        );
    }
}
