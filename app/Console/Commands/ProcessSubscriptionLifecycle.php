<?php

namespace App\Console\Commands;

use App\Enums\AccountStatus;
use App\Mail\SubscriptionReminderMail;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionReminderLog;
use App\Services\Billing\AccountBillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Daily lifecycle tick (BR-18, BR-19):
 *  1. Recompute every account's status from its anchor (advances
 *     active → grace → expired purely from the passage of time, with no
 *     billing action required; a manual Suspended flag is never overwritten).
 *  2. Send at most one reminder per (account, window) — upcoming-renewal
 *     windows before the anchor, one on entering grace, one on hard expiry.
 *
 * Email is a base/secondary channel here (BRD §1): most owners won't have SMS
 * available yet and many won't provide an email at all, so a missing email is
 * logged and skipped, never an error.
 */
class ProcessSubscriptionLifecycle extends Command
{
    protected $signature = 'hostelease:process-subscription-lifecycle';

    protected $description = 'Recompute account lifecycle status and send renewal/grace/expiry reminder emails (once per window).';

    public function handle(AccountBillingService $billing): int
    {
        $sent = 0;
        $skippedNoEmail = 0;
        $errors = 0;

        SubscriptionAccount::with('owner')->whereNull('deleted_at')->cursor()->each(function (SubscriptionAccount $account) use ($billing, &$sent, &$skippedNoEmail, &$errors) {
            try {
                $billing->refreshAccountAnchor($account);
                $account->refresh();

                if ($account->status === AccountStatus::Suspended || ! $account->current_period_end) {
                    return;
                }

                [$window, $kind] = $this->resolveWindow($account);
                if (! $window) {
                    return;
                }

                if (SubscriptionReminderLog::where('account_id', $account->id)->where('window', $window)->exists()) {
                    return;
                }

                $email = $account->owner?->email;
                $daysUntil = (int) $account->daysUntilAnchor();

                if ($email) {
                    Mail::to($email)->queue(new SubscriptionReminderMail($account, $kind, $daysUntil));
                    SubscriptionReminderLog::create(['account_id' => $account->id, 'window' => $window, 'channel' => 'email', 'status' => 'sent']);
                    $sent++;
                } else {
                    SubscriptionReminderLog::create(['account_id' => $account->id, 'window' => $window, 'channel' => 'email', 'status' => 'skipped_no_email']);
                    $skippedNoEmail++;
                }
            } catch (Throwable $e) {
                $errors++;
                Log::error('Subscription lifecycle: failed processing account', ['account_id' => $account->id, 'error' => $e->getMessage()]);
            }
        });

        $this->info("Lifecycle processed — emailed: {$sent}, skipped (no email): {$skippedNoEmail}, errors: {$errors}.");

        return self::SUCCESS;
    }

    /** @return array{0: ?string, 1: ?string} [window key, mail kind] */
    protected function resolveWindow(SubscriptionAccount $account): array
    {
        $daysUntil = $account->daysUntilAnchor();
        if ($daysUntil === null) {
            return [null, null];
        }

        $windows = config('hostelease.renewal_reminder_days', [30, 15, 7, 0]);
        if ($daysUntil >= 0 && in_array($daysUntil, $windows, true)) {
            return ["due_{$daysUntil}", 'upcoming'];
        }

        if ($account->status === AccountStatus::Grace) {
            return ['grace_start', 'grace'];
        }

        if ($account->status === AccountStatus::Expired) {
            return ['expired', 'expired'];
        }

        return [null, null];
    }
}
