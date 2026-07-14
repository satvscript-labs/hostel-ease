<?php

namespace Database\Seeders;

use App\Enums\BillingPeriod;
use App\Models\Hostel;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\HostelService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Phase 5 lifecycle fixtures — companion to 04_phase5_manual_testing.md.
 *
 * Phase 5 is time-based (grace windows, the daily dunning command, reminder
 * emails, suspend). The manual doc mostly moves ONE account's dates around with
 * tinker; this seeder instead stands up a spread of accounts already sitting in
 * each lifecycle state, named "P5 …", so you can eyeball grace / expired /
 * due-soon / suspended side by side and run the command against real data
 * without re-dating anything first.
 *
 * Run with:  php artisan db:seed --class=Phase5TestingSeeder
 *
 * What each fixture is for (§ = section of the manual doc):
 *   P5 Grace         → §1.1 access continues in grace, §3.4 grace reminder
 *   P5 Expired       → §1.2 blocked past grace, §3.4 expired reminder
 *   P5 Renewal 30/15/7/0 → §3 dunning windows [30,15,7,0]; the 7-day one has an
 *                       email set (the main §3.1/§3.2 email test)
 *   P5 No-Email      → §3.3 the "skipped (no email)" path (email is null)
 *   P5 Suspended     → §2 suspend/reactivate + §2.6 "suspension survives renewal"
 *   P5 Multi (2 br.) → §4 per-account alert dedup (ONE alert, not one per branch)
 */
class Phase5TestingSeeder extends Seeder
{
    public function run(): void
    {
        // ── §1 / §3.4 — grace & expired ──
        $this->account('+919750000001', 'P5 Grace Owner', ['P5 Grace Hostel'],
            Carbon::now()->subDay(), 'grace', 'p5.grace@test.hostelease.local');

        $this->account('+919750000002', 'P5 Expired Owner', ['P5 Expired Hostel'],
            Carbon::now()->subDays(10), 'expired', 'p5.expired@test.hostelease.local');

        // ── §3 — dunning windows [30, 15, 7, 0] (each owner has its own email) ──
        $this->account('+919750000003', 'P5 Renewal 30 Owner', ['P5 Renewal 30 Hostel'],
            Carbon::now()->addDays(30), 'active', 'p5.due30@test.hostelease.local');

        $this->account('+919750000004', 'P5 Renewal 15 Owner', ['P5 Renewal 15 Hostel'],
            Carbon::now()->addDays(15), 'active', 'p5.due15@test.hostelease.local');

        $this->account('+919750000005', 'P5 Renewal 7 Owner', ['P5 Renewal 7 Hostel'],
            Carbon::now()->addDays(7), 'active', 'p5.due7@test.hostelease.local');

        $this->account('+919750000006', 'P5 Renewal Today Owner', ['P5 Renewal Today Hostel'],
            Carbon::now(), 'active', 'p5.due0@test.hostelease.local');

        // ── §3.3 — the common "no email on file" path ──
        $this->account('+919750000007', 'P5 No-Email Owner', ['P5 No-Email Hostel'],
            Carbon::now()->addDays(7), 'active', null);

        // ── §2 — suspend / reactivate (pre-suspended so it's visible) ──
        $this->account('+919750000008', 'P5 Suspended Owner', ['P5 Suspended Hostel'],
            Carbon::now()->addMonths(3), 'suspended', 'p5.suspended@test.hostelease.local');

        // ── §4 — per-account alert dedup: 2 branches, one account ──
        $this->account('+919750000009', 'P5 Multi Owner', ['P5 Multi Hostel A', 'P5 Multi Hostel B'],
            Carbon::now()->addDays(5), 'active', 'p5.multi@test.hostelease.local');

        $this->command->info('Phase 5 lifecycle fixtures seeded: P5 Grace / Expired / Renewal 30-15-7-0 / No-Email / Suspended / Multi (2 branches).');
    }

    /**
     * Stand up one owner + account already sitting at $anchor in $status.
     *
     * @param  string[]  $branchNames
     * @param  'active'|'grace'|'expired'|'suspended'  $status
     */
    private function account(string $mobile, string $ownerName, array $branchNames, Carbon $anchor, string $status, ?string $email, string $period = 'yearly'): void
    {
        $owner = User::updateOrCreate(
            ['mobile' => $mobile],
            [
                'name' => $ownerName,
                'email' => $email,
                'password' => Hash::make('password'),
                'role' => 'hostel_admin',
                'is_active' => true,
            ]
        );

        // hostels.status: expired branches read 'expired', suspended read
        // 'suspended', everything else (incl. grace — a branch in its grace
        // window is still 'active', only the ACCOUNT is Grace) reads 'active'.
        $hostelStatus = match ($status) {
            'expired' => 'expired',
            'suspended' => 'suspended',
            default => 'active',
        };

        $ids = [];
        foreach ($branchNames as $name) {
            $hostel = Hostel::updateOrCreate(
                ['mobile' => $mobile, 'name' => $name],
                [
                    'owner_name' => $ownerName,
                    'owner_id' => $owner->id,
                    'email' => $email,
                    'address' => 'Phase 5 Fixture',
                    'city' => 'Ahmedabad',
                    'state' => 'Gujarat',
                    'status' => $hostelStatus,
                    'subscription_start' => $anchor->copy()->subYear(),
                    'subscription_end' => $anchor->copy(),
                ]
            );
            app(HostelService::class)->seedPaymentModes($hostel);
            $ids[] = $hostel->id;

            // A paid legacy subscription so there's a little history on file.
            Subscription::firstOrCreate(
                ['hostel_id' => $hostel->id, 'start_date' => $hostel->subscription_start],
                [
                    'plan' => $period === 'monthly' ? '1_month' : '1_year',
                    'end_date' => $anchor->copy(),
                    'amount' => (float) config('hostelease.subscription_pricing.'.$period, 10000),
                    'payment_status' => 'paid',
                    'payment_method' => 'upi',
                    'transaction_number' => 'P5TXN'.$hostel->id,
                ]
            );
        }

        // Item-14 invariants: pivot access + a primary branch.
        $owner->hostels()->sync($ids);
        if (! $owner->hostel_id) {
            $owner->forceFill(['hostel_id' => $ids[0]])->save();
        }

        // Account spine: anchor from the branches, then force the exact lifecycle
        // status (computeStatus() derives active/grace/expired from the anchor;
        // 'suspended' is a manual override it would never produce on its own).
        $billing = app(AccountBillingService::class);
        $account = $billing->accountFor($owner);
        $billing->refreshAccountAnchor($account, BillingPeriod::from($period));
        $account->update([
            'status' => $status,
            'current_period_end' => $anchor->copy(),
        ]);
    }
}
