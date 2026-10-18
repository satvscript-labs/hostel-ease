<?php

namespace App\Services\Billing;

use App\Models\RenewalReminderSnooze;
use App\Models\SubscriptionAccount;
use App\Models\User;

/**
 * The owner's dashboard renewal card (S4 · 24_S4_DESIGN.md §7) — everything the view
 * renders, built from RenewalReminders so it can never disagree with the emails.
 *
 * Shown to hostel admins (the owner and co-admins), never to staff. A viewer may
 * snooze it while it is early; in the last days there is no snooze.
 */
class RenewalCard
{
    public function __construct(protected RenewalReminders $reminders) {}

    /** @return array<string, mixed>|null */
    public function for(SubscriptionAccount $account, User $viewer): ?array
    {
        if (! $viewer->isHostelAdmin()) {
            return null;
        }

        $stage = $this->reminders->stage($account);
        // Expired accounts never reach the dashboard (EnsureActiveSubscription).
        if (! $stage || $stage['tone'] === 'expired') {
            return null;
        }

        $c = $this->reminders->context($account);
        $days = $stage['days'];
        $tone = $stage['tone'];
        $noun = $c['is_trial'] ? __('trial') : __('plan');
        $isOwner = $account->owner_id === $viewer->id;

        $headline = match ($tone) {
            'grace' => __('Renew by :date to keep access', ['date' => $c['grace_until']->format('d M')]),
            'today' => $c['is_trial'] ? __('Your trial ends today') : __('Your plan renews today'),
            default => $c['is_trial']
                ? trans_choice('Your trial ends in :n day|Your trial ends in :n days', $days, ['n' => $days])
                : trans_choice('Your plan renews in :n day|Your plan renews in :n days', $days, ['n' => $days]),
        };

        $money = match (true) {
            // S6: the bank has it, or will — reassurance, not a demand.
            $c['autopay'] === 'paying' => __('Autopay is paying :amount — nothing to do', ['amount' => hostelease_money($c['waiting_amount'] ?? $c['amount'])]),
            $c['autopay'] === 'on' => __(':amount will be paid by autopay — nothing to do', ['amount' => hostelease_money($c['amount'])]),
            $c['waiting_amount'] !== null => __(':amount is waiting to be paid', ['amount' => hostelease_money($c['waiting_amount'])]),
            $c['free'] => __('Nothing to pay — your next renewal is free'),
            $c['is_trial'] => __(':yearly a year or :monthly a month to continue', ['yearly' => hostelease_money($c['amounts']['yearly']), 'monthly' => hostelease_money($c['amounts']['monthly'])]),
            default => hostelease_money($c['amount']).' · '.trans_choice(':n branch|:n branches', $c['branches'], ['n' => $c['branches']]).' · '.__(ucfirst($c['term'])),
        };

        // The one action. A link the operator sent can be paid by any admin; the
        // owner's own page can start a charge only for the owner on self-serve.
        $cta = match (true) {
            $c['autopay'] !== null => ['label' => __('View subscription'), 'url' => route('admin.subscription.index'), 'external' => false],
            $c['link_url'] !== null => ['label' => __('Pay :amount', ['amount' => hostelease_money($c['waiting_amount'])]), 'url' => $c['link_url'], 'external' => true],
            $c['self_serve'] && $isOwner => ['label' => $c['waiting_amount'] !== null
                ? __('Pay :amount', ['amount' => hostelease_money($c['waiting_amount'])])
                : ($c['free'] ? __('Renew for free') : __('Review & renew')),
                'url' => route('admin.subscription.index'), 'external' => false],
            default => ['label' => __('View subscription'), 'url' => route('admin.subscription.index'), 'external' => false],
        };

        $note = match (true) {
            $c['link_url'] !== null => null,
            // Managed — or self-serve switched off for everyone, which the owner page
            // also shows as "Managed by HostelEase" (S4 audit A2).
            ! $c['self_serve'] => __('We look after your billing — our team will be in touch to renew it.'),
            ! $isOwner => __('The account owner renews the plan.'),
            default => null,
        };

        $snooze = RenewalReminderSnooze::where('user_id', $viewer->id)
            ->where('account_id', $account->id)
            ->whereDate('cycle_end', $account->current_period_end->toDateString())
            ->first();
        $snoozedUntil = $snooze && $snooze->until->isFuture() ? $snooze->until : null;

        // The ring fills as the date approaches: empty at the first reminder, full on
        // the day, and full in grace.
        $span = max(1, max($this->reminders->windows($account)));
        $progress = $days < 0 ? 1.0 : round(1 - min($days, $span) / $span, 3);

        $timeline = [['label' => $c['is_trial'] ? __('Trial ends') : __('Renews'), 'date' => $c['renews_on']->format('d M')]];
        if ($c['grace_until']->greaterThan($c['renews_on'])) {
            $timeline[] = ['label' => __('Grace until'), 'date' => $c['grace_until']->format('d M')];
        }
        $timeline[] = ['label' => __('Access pauses'), 'date' => $c['pauses_on']->format('d M')];

        // The number in the ring: days left to renew, or — in grace — days of access left.
        $graceLeft = max(0, (int) now()->startOfDay()->diffInDays($c['grace_until'], false));
        [$ringNumber, $ringLabel] = $tone === 'grace'
            ? [$graceLeft, trans_choice('day left|days left', $graceLeft)]
            : ($days === 0 ? [__('Today'), ''] : [$days, trans_choice('day|days', $days)]);

        return [
            'tone' => $tone,
            'days' => $days,
            'ringNumber' => $ringNumber,
            'ringLabel' => $ringLabel,
            'headline' => $headline,
            'money' => $money,
            'cta' => $cta,
            'note' => $note,
            'progress' => $progress,
            'timeline' => $timeline,
            // Where "today" sits on the timeline: before the first point, or in grace.
            'position' => $days >= 0 ? 0 : 1,
            'cycle' => $account->current_period_end->toDateString(),
            'snoozedUntil' => $snoozedUntil,
            'snoozeChoices' => collect($this->reminders->snoozeChoices($account))
                ->map(fn ($date, $before) => ['before' => $before, 'date' => $date->format('d M')])
                ->values()->all(),
        ];
    }
}
