<?php

namespace App\Console\Commands;

use App\Models\SubscriptionAccount;
use App\Models\SubscriptionMandate;
use App\Services\Billing\AutopayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Raise today's autopay debits (S6 · 31_S6_DESIGN.md §3B). Runs daily BEFORE the
 * reminders job, so an account whose debit is in flight is told "being debited", not
 * "please renew". Safe to run more than once a day: each attempt is unique per account
 * and renewal date (L2), and a new attempt is raised only once the last one has a final
 * status.
 */
class ChargeDueAutopay extends Command
{
    protected $signature = 'hostelease:charge-due-autopay';

    protected $description = 'Raise due UPI Autopay debits (monthly plans), retry failed ones on schedule, and reconcile any stuck debit with Razorpay.';

    public function handle(AutopayService $autopay): int
    {
        // No early stop when autopay is switched off: a debit already in flight must still
        // be reconciled (money taken is always applied — L11). debitDue() checks the
        // switch itself, after that, before raising anything new.
        $tally = [];

        // Accounts with an active mandate, plus any with a debit still in flight (its
        // mandate may have been paused or cancelled since — the debit still resolves).
        $accountIds = SubscriptionMandate::where('status', SubscriptionMandate::ACTIVE)->whereNotNull('token_id')->pluck('account_id')
            ->merge(\App\Models\SubscriptionOrder::where('collection', 'autopay')->where('payment_status', 'pending')->pluck('account_id'))
            ->unique();

        foreach (SubscriptionAccount::with('owner')->whereIn('id', $accountIds)->cursor() as $account) {
            try {
                $result = $autopay->debitDue($account);
            } catch (Throwable $e) {
                $result = 'error';
                Log::error('Autopay: failed processing account', ['account' => $account->id, 'error' => $e->getMessage()]);
            }
            $key = str_starts_with($result, 'skipped') ? 'skipped' : $result;
            $tally[$key] = ($tally[$key] ?? 0) + 1;
        }

        $this->info('Autopay — '.(collect($tally)->map(fn ($n, $k) => "{$k}: {$n}")->implode(', ') ?: 'no active mandates').'.');

        return self::SUCCESS;
    }
}
