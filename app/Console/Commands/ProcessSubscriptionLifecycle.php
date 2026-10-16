<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionReminderMail;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionReminderLog;
use App\Services\Billing\AccountBillingService;
use App\Services\Billing\RenewalReminders;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Daily lifecycle tick (BR-18, BR-19; S4 · 24_S4_DESIGN.md):
 *  1. Recompute every account's status from its anchor (active → grace → expired
 *     purely from the passage of time; a manual Suspended flag is never overwritten).
 *  2. Send the reminder window that is due today — at most once per (account,
 *     cycle, window) — by email (inline, so the log is true) and in-app (one bell
 *     entry per account on every billable branch's feed).
 *  3. Clear that bell entry once the account is no longer due (it was renewed).
 *
 * What is due, and what each reminder says, is RenewalReminders' call — this
 * command only delivers it.
 */
class ProcessSubscriptionLifecycle extends Command
{
    protected $signature = 'hostelease:process-subscription-lifecycle';

    protected $description = 'Recompute account lifecycle status and send renewal / grace / expiry reminders (email + in-app, once per window per cycle).';

    /** An email that failed is retried on later runs, up to this many attempts in all. */
    public const MAX_EMAIL_ATTEMPTS = 3;

    public const BELL_TYPE = 'renewal_reminder';

    public function handle(AccountBillingService $billing, RenewalReminders $reminders, NotificationService $notifications): int
    {
        $tally = ['email' => 0, 'in_app' => 0, 'failed' => 0, 'errors' => 0];

        SubscriptionAccount::with('owner')->cursor()->each(function (SubscriptionAccount $account) use ($billing, $reminders, $notifications, &$tally) {
            try {
                $billing->refreshAccountAnchor($account);
                $account->refresh();

                // Nothing left to say (renewed, suspended, closing): take the bell
                // entry down from every feed it was put on.
                if (! $reminders->stage($account)) {
                    $this->clearBell($account, $billing, $notifications);
                }

                $window = $reminders->dueWindow($account);
                if (! $window) {
                    return;
                }

                $result = $this->deliver($account, $window, $reminders, $billing, $notifications);
                if ($result) {
                    $tally[$result]++;
                }
            } catch (Throwable $e) {
                $tally['errors']++;
                Log::error('Subscription lifecycle: failed processing account', ['account_id' => $account->id, 'error' => $e->getMessage()]);
            }
        });

        $this->info("Lifecycle processed — by email: {$tally['email']}, in-app only: {$tally['in_app']}, email failed: {$tally['failed']}, errors: {$tally['errors']}.");

        return self::SUCCESS;
    }

    /**
     * Send one window. Returns the tally bucket, or null when it was already done.
     */
    protected function deliver(SubscriptionAccount $account, string $window, RenewalReminders $reminders, AccountBillingService $billing, NotificationService $notifications): ?string
    {
        $cycle = $account->current_period_end->toDateString();

        $log = SubscriptionReminderLog::where('account_id', $account->id)
            ->whereDate('cycle_end', $cycle)
            ->where('window', $window)
            ->first();

        // Done already — unless its email failed and may still be retried, or a run
        // died after claiming it (left 'pending' well past any run in flight).
        $retry = $log && $log->attempts < self::MAX_EMAIL_ATTEMPTS && (
            $log->status === 'failed'
            || ($log->status === 'pending' && $log->updated_at?->lt(now()->subMinutes(30)))
        );
        if ($log && ! $retry) {
            return null;
        }

        if (! $log) {
            // Claim the window first: a second run racing this one hits the unique
            // index and backs off, so nobody is emailed twice (E2).
            try {
                $log = SubscriptionReminderLog::create([
                    'account_id' => $account->id, 'cycle_end' => $cycle, 'window' => $window,
                    'channel' => 'in_app', 'status' => 'pending', 'attempts' => 0, 'detail' => [],
                ]);
            } catch (QueryException) {
                return null;
            }

            // In-app goes out exactly once per window, whatever happens to the email.
            $this->postBell($account, $window, $reminders, $billing, $notifications);
        }

        $owner = $account->owner;
        $email = $owner?->email;
        $detail = (array) $log->detail + ['in_app' => 'posted'];

        if (! $email) {
            $status = 'no_email';
        } else {
            try {
                Mail::to($email)->send(new SubscriptionReminderMail($account, $window));
                $status = 'sent';
                $detail['email_verified'] = (bool) $owner->email_verified_at;
                unset($detail['error']);
            } catch (Throwable $e) {
                $status = 'failed';
                $detail['error'] = mb_substr($e->getMessage(), 0, 250);
                Log::warning('Renewal reminder email failed', ['account_id' => $account->id, 'window' => $window, 'error' => $e->getMessage()]);
            }
        }

        $log->update([
            'channel' => $status === 'sent' ? 'email' : 'in_app',
            'status' => $status,
            'attempts' => $log->attempts + ($email ? 1 : 0),
            'detail' => $detail,
        ]);

        return match ($status) {
            'sent' => 'email',
            'failed' => 'failed',
            default => 'in_app',
        };
    }

    /** One entry per account, on every billable branch's feed (the feed is per branch). */
    protected function postBell(SubscriptionAccount $account, string $window, RenewalReminders $reminders, AccountBillingService $billing, NotificationService $notifications): void
    {
        $c = $reminders->context($account);
        $noun = $c['is_trial'] ? __('trial') : __('plan');
        $days = (int) $account->daysUntilAnchor();

        [$title, $level] = match (true) {
            $window === 'grace' => [__('Your :noun has ended — renew by :date', ['noun' => $noun, 'date' => $c['grace_until']->format('d M')]), 'danger'],
            $window === 'expired' => [__('Your :noun has expired', ['noun' => $noun]), 'danger'],
            $days === 0 => [__('Your :noun renews today', ['noun' => $noun]), 'danger'],
            default => [trans_choice('Your :noun renews in :n day|Your :noun renews in :n days', $days, ['noun' => $noun, 'n' => $days]), $days <= 7 ? 'warning' : 'info'],
        };

        $message = match (true) {
            $c['waiting_amount'] !== null => __(':amount is waiting to be paid.', ['amount' => hostelease_money($c['waiting_amount'])]),
            $c['free'] => __('Your next renewal is free — nothing to pay.'),
            $c['self_serve'] => __('Renew from your Subscription page — every branch renews together.'),
            default => __('Our team will be in touch to renew it for you.'),
        };

        foreach ($billing->includedBranches($account) as $branch) {
            $notifications->push($branch->id, self::BELL_TYPE, 'account:'.$account->id, $title, $message, $level);
        }
    }

    protected function clearBell(SubscriptionAccount $account, AccountBillingService $billing, NotificationService $notifications): void
    {
        foreach ($billing->allBranches($account) as $branch) {
            $notifications->clear($branch->id, self::BELL_TYPE, 'account:'.$account->id);
        }
    }
}
