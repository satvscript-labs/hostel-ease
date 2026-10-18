<?php

namespace App\Services\Billing;

use App\Enums\AccountStatus;
use App\Enums\BillingPeriod;
use App\Enums\OrderKind;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use Carbon\Carbon;

/**
 * The ONE place that decides an account's renewal-reminder stage
 * (_artifact/saas_billing_autopay/24_S4_DESIGN.md).
 *
 * The daily job (email + bell), the owner's dashboard card, Account 360 and the
 * operator's WhatsApp nudge all render what this returns — none of them work out
 * dates or amounts on their own, so they cannot disagree.
 *
 * It never creates anything: no order, no payment link. A reminder points at what
 * already exists — the owner's Subscription page, or a link the operator sent.
 */
class RenewalReminders
{
    /** A missed daily run is caught up for this many days, no more (design §3, R2). */
    public const CATCH_UP_DAYS = 2;

    /** No "has expired" email once the renewal date is this far behind (R9). */
    public const EXPIRED_EMAIL_DAYS = 14;

    /** The card may be snoozed only while more than this many days remain. */
    public const SNOOZE_FLOOR_DAYS = 3;

    /** Snooze choices, as "N days before the renewal date". */
    public const SNOOZE_CHOICES = [7, 3];

    public function __construct(protected AccountBillingService $billing) {}

    /**
     * Should this account be reminded at all? Suspended accounts, accounts with no
     * renewal date and accounts whose every branch is closing are not (E5–E7).
     */
    public function eligible(SubscriptionAccount $account): bool
    {
        return ! $account->trashed()
            && $account->status !== AccountStatus::Suspended
            && $account->current_period_end !== null
            && $this->billing->includedBranches($account)->isNotEmpty();
    }

    /** Length of the current cycle in days — what decides which windows apply (R3). */
    public function cycleDays(SubscriptionAccount $account): int
    {
        $start = $account->current_period_start;
        $end = $account->current_period_end;

        if ($start && $end && $end->greaterThan($start)) {
            return (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay());
        }

        return match ($account->period) {
            BillingPeriod::Trial => 14,
            BillingPeriod::Monthly => 30,
            default => 365,
        };
    }

    /**
     * The reminder windows that apply to this cycle, largest first. A window is used
     * only if it is no more than about half the cycle: a 14-day trial is not told on
     * sign-up day that it "ends in 14 days", and a monthly plan is not reminded the
     * day after it was paid (R3). The renewal day itself (0) always applies.
     *
     * @return list<int>
     */
    public function windows(SubscriptionAccount $account): array
    {
        $limit = intdiv($this->cycleDays($account), 2) + 1;

        $windows = collect(config('hostelease.renewal_reminder_days', [30, 15, 7, 0]))
            ->map(fn ($w) => (int) $w)
            ->filter(fn (int $w) => $w >= 0 && $w <= $limit)
            ->push(0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $windows;
    }

    /**
     * The window the daily job should send TODAY, or null.
     *
     * Only the most recent window crossed ever fires — never a burst of 30+15+7 —
     * and a window survives up to CATCH_UP_DAYS missed runs (R2). Grace and expired
     * are windows of their own; expired stops being emailed after EXPIRED_EMAIL_DAYS.
     * Reads the status as stored, so call refreshAccountAnchor() first.
     */
    public function dueWindow(SubscriptionAccount $account): ?string
    {
        if (! $this->eligible($account)) {
            return null;
        }

        $days = $account->daysUntilAnchor();

        // S6: autopay will take (or is taking) this renewal — no "please renew" for
        // it. Its own notice ("will be debited") goes out when the debit is raised.
        // If every attempt fails, covers() turns null and the grace reminder follows.
        if (app(AutopayService::class)->covers($account)) {
            return null;
        }

        if ($days >= 0) {
            // The smallest window not yet reached is the one this run belongs to.
            $window = collect($this->windows($account))->filter(fn (int $w) => $w >= $days)->min();

            if ($window === null || $days < $window - self::CATCH_UP_DAYS) {
                return null;
            }

            return 'due_'.$window;
        }

        $status = $this->billing->computeStatus($account);

        if ($status === AccountStatus::Grace) {
            return 'grace';
        }

        if ($status === AccountStatus::Expired && -$days <= self::EXPIRED_EMAIL_DAYS) {
            return 'expired';
        }

        return null;
    }

    /**
     * What the owner should see right now — the dashboard card. Unlike dueWindow()
     * this is continuous: from the first applicable window until the renewal is
     * dealt with. Null when there is nothing to say.
     *
     * @return array{tone:'calm'|'soon'|'final'|'today'|'grace'|'expired', days:int}|null
     */
    public function stage(SubscriptionAccount $account): ?array
    {
        if (! $this->eligible($account)) {
            return null;
        }

        $days = (int) $account->daysUntilAnchor();

        if ($days >= 0) {
            if ($days > max($this->windows($account))) {
                return null;
            }

            $tone = match (true) {
                $days === 0 => 'today',
                $days <= self::SNOOZE_FLOOR_DAYS => 'final',
                $days <= 7 => 'soon',
                default => 'calm',
            };

            return ['tone' => $tone, 'days' => $days];
        }

        // Computed, not the stored status: that is refreshed by the 07:30 job, so on
        // the morning after the renewal date it still read "active" and the card
        // vanished exactly when it mattered most (S4 audit A1).
        return match ($this->billing->computeStatus($account)) {
            AccountStatus::Grace => ['tone' => 'grace', 'days' => $days],
            AccountStatus::Expired => ['tone' => 'expired', 'days' => $days],
            default => null,
        };
    }

    /**
     * Everything a reminder says, computed once: dates, the amount, and the one next
     * step. Shared by the email, the card and the WhatsApp nudge.
     *
     * @return array<string, mixed>
     */
    public function context(SubscriptionAccount $account): array
    {
        $anchor = $account->current_period_end->copy()->startOfDay();
        $graceUntil = $anchor->copy()->addDays((int) config('hostelease.grace_days', 0));
        $isTrial = $account->period === BillingPeriod::Trial;
        $term = $account->period?->isPaid() ? $account->period->value : BillingPeriod::Yearly->value;

        $quote = $this->billing->quoteRenewal($account, $term);
        $amounts = $isTrial
            ? [
                'yearly' => (float) $quote['total'],
                'monthly' => (float) $this->billing->quoteRenewal($account, BillingPeriod::Monthly->value)['total'],
            ]
            : [$term => (float) $quote['total']];

        $waiting = $this->waitingRenewal($account);
        $linkUrl = $waiting && $waiting->hasLiveLink() && str_starts_with((string) $waiting->payment_link_url, 'https://')
            ? $waiting->payment_link_url
            : null;
        $selfServe = $account->selfServeEnabled();

        return [
            'renews_on' => $anchor,
            'grace_until' => $graceUntil,
            'pauses_on' => $graceUntil->copy()->addDay(),
            'is_trial' => $isTrial,
            'term' => $term,
            'amount' => (float) $quote['total'],
            'amounts' => $amounts,
            'branches' => (int) $quote['quantity'],
            // Every branch holds a free renewal: nothing to pay this time (E16).
            'free' => ! $isTrial && (float) $quote['total'] <= 0,
            'self_serve' => $selfServe,
            'managed' => $account->isManaged(),
            // A renewal the operator (or the owner) already raised and that would
            // still buy what it says. Its amount is what is owed — never re-quoted.
            'waiting_amount' => $waiting ? (float) $waiting->amount : null,
            'link_url' => $linkUrl,
            // The one place to pay: the operator's link if there is one, else the
            // owner's own page when they may pay there. Null = we will contact them.
            'pay_url' => $linkUrl ?? ($selfServe ? route('admin.subscription.index') : null),
            // S6: 'paying' (with the bank now) | 'on' (will be debited) | null.
            'autopay' => app(AutopayService::class)->covers($account),
        ];
    }

    /** The renewal already raised for this account that paying would still honour (E11/E12). */
    public function waitingRenewal(SubscriptionAccount $account): ?SubscriptionOrder
    {
        return $account->orders()->outstanding()
            ->where('kind', OrderKind::Renewal->value)
            ->with('lines.branch')
            ->latest('id')
            ->get()
            ->first(fn (SubscriptionOrder $o) => $o->wouldExtendCoverage());
    }

    /**
     * Snooze choices still open for this cycle, as [days-before => date]. Empty in
     * the last SNOOZE_FLOOR_DAYS, on the day and in grace (E18).
     *
     * @return array<int, Carbon>
     */
    public function snoozeChoices(SubscriptionAccount $account): array
    {
        $days = $account->daysUntilAnchor();
        if ($days === null || $days <= self::SNOOZE_FLOOR_DAYS) {
            return [];
        }

        $choices = [];
        foreach (self::SNOOZE_CHOICES as $before) {
            if ($days > $before) {
                $choices[$before] = $account->current_period_end->copy()->startOfDay()->subDays($before);
            }
        }

        return $choices;
    }

    /** The prefilled WhatsApp message for the operator's manual nudge. */
    public function nudgeMessage(SubscriptionAccount $account): string
    {
        $c = $this->context($account);
        $name = $account->owner?->name ?? __('there');
        $date = $c['renews_on']->format('d M Y');
        $days = (int) $account->daysUntilAnchor();
        $noun = $c['is_trial'] ? __('free trial') : __('HostelEase plan');

        $when = match (true) {
            $days > 0 => __('renews on :date (in :n days)', ['date' => $date, 'n' => $days]),
            $days === 0 => __('renews today (:date)', ['date' => $date]),
            $this->billing->computeStatus($account) === AccountStatus::Grace => __('ended on :date — access continues until :until', ['date' => $date, 'until' => $c['grace_until']->format('d M Y')]),
            default => __('ended on :date and access is paused', ['date' => $date]),
        };

        $lines = [__('Hi :name,', ['name' => $name]), __('Your :noun :when.', ['noun' => $noun, 'when' => $when])];

        if ($c['autopay'] === 'paying') {
            $lines[] = __('Your autopay payment of :amount is being processed — nothing to do.', ['amount' => hostelease_money($c['waiting_amount'] ?? $c['amount'])]);
        } elseif ($c['autopay'] === 'on') {
            $lines[] = __(':amount will be paid automatically by UPI Autopay — nothing to do.', ['amount' => hostelease_money($c['amount'])]);
        } elseif ($c['waiting_amount'] !== null && $c['link_url']) {
            $lines[] = __('Amount: :amount. Pay securely here: :url', ['amount' => hostelease_money($c['waiting_amount']), 'url' => $c['link_url']]);
        } elseif ($c['free']) {
            $lines[] = __('Your next renewal is free — nothing to pay.');
        } elseif ($c['is_trial']) {
            $lines[] = __('To continue: :yearly a year or :monthly a month.', ['yearly' => hostelease_money($c['amounts']['yearly']), 'monthly' => hostelease_money($c['amounts']['monthly'])]);
        } else {
            $lines[] = __('Amount: :amount for :n branch(es).', ['amount' => hostelease_money($c['amount']), 'n' => $c['branches']]);
            if ($c['self_serve']) {
                $lines[] = __('You can renew from the Subscription page in HostelEase.');
            }
        }

        $lines[] = __('Reply here if you have any questions. — Team :app', ['app' => config('app.name', 'HostelEase')]);

        return implode("\n", $lines);
    }
}
