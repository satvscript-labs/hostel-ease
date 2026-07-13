<?php

namespace Database\Seeders;

use App\Enums\BillingPeriod;
use App\Enums\DiscountRecurrence;
use App\Enums\DiscountStatus;
use App\Enums\DiscountType;
use App\Models\Discount;
use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\HostelService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Dedicated, isolated fixtures for the Phase 4 manual testing script
 * (_artifact/subscription_update/03_phase4_manual_testing.md). One owner per
 * doc section so each feature can be tested independently and re-tested by
 * simply re-running this seeder, without touching the Ramesh Patel baseline
 * from DemoHostelSeeder or needing to restore/replay the DB backup between
 * sections.
 *
 * Every hostel is backdated (created_at, subscription_start) to a realistic
 * past registration date via Carbon::setTestNow(), then its coverage window
 * is force-set to an exact, documented value — see setCoverage(). "Anchor"
 * branches land 12 months out; "behind" branches land 3–6 months out, so
 * Add to cycle / Align show a meaningful mid-range prorated charge instead
 * of ₹0 or the full list price.
 *
 * No students/rooms/beds/staff are created — billing-relevant rows only.
 * Safe to re-run: existing hostels are found by (mobile, name) and just have
 * their coverage window refreshed to the new relative dates; nothing is
 * duplicated.
 *
 * Run with: php artisan db:seed --class=Phase4TestingSeeder
 */
class Phase4TestingSeeder extends Seeder
{
    /** Fixed password for every login this seeder creates. */
    private const PASSWORD = 'password';

    public function run(): void
    {
        // ---- §1 Customers list — /superadmin/accounts (active + expired rows for the Status filter) ----
        $t1a = $this->provisionBranch('+917990000101', 'Test 1 Owner', 'Test 1 Hostel 1', 5, 'test1.owner@test.hostelease.local');
        $this->setCoverage($t1a, now()->subMonths(5), now()->addMonths(7));

        $t1b = $this->provisionBranch('+917990000102', 'Test 1 Owner Expired', 'Test 1 Hostel 2', 15, 'test1.expired@test.hostelease.local');
        $this->setCoverage($t1b, now()->subMonths(15), now()->subMonths(3), 'expired');

        // ---- §2 Branch setup / branch sync exercise — add "Test 2 Hostel 2" yourself via + New Hostel using the same mobile ----
        $t2 = $this->provisionBranch('+917990000201', 'Test 2 Owner', 'Test 2 Hostel 1', 6, 'test2.owner@test.hostelease.local');
        $this->setCoverage($t2, now()->subMonths(6), now()->addMonths(6));

        // ---- §3 Account 360 layout check — anchor branch + a behind branch (both hero buttons) + a permanent discount (populated panel) ----
        $t3h1 = $this->provisionBranch('+917990000301', 'Test 3 Owner', 'Test 3 Hostel 1', 6, 'test3.owner@test.hostelease.local');
        $this->setCoverage($t3h1, now()->subMonths(6), now()->addMonths(12));

        $t3h2 = $this->provisionBranch('+917990000301', 'Test 3 Owner', 'Test 3 Hostel 2', 1, 'test3.owner@test.hostelease.local');
        $this->setCoverage($t3h2, now()->subMonths(1), now()->addMonths(6));

        $this->seedDiscount(
            $t3h1['account'],
            DiscountRecurrence::EveryRenewal,
            DiscountType::Percentage,
            5,
            'Loyalty — long-term customer (seed fixture)',
        );

        // ---- §4 Renew all — two branches already co-terminated, ready for the core renew flow ----
        $t4h1 = $this->provisionBranch('+917990000401', 'Test 4 Owner', 'Test 4 Hostel 1', 4, 'test4.owner@test.hostelease.local');
        $this->setCoverage($t4h1, now()->subMonths(4), now()->addMonths(8));

        $t4h2 = $this->provisionBranch('+917990000401', 'Test 4 Owner', 'Test 4 Hostel 2', 4, 'test4.owner@test.hostelease.local');
        $this->setCoverage($t4h2, now()->subMonths(4), now()->addMonths(8));

        // ---- §5 Add to cycle — one behind branch, ~180 days remaining (prorate lands near the doc's own ~₹4,900 example) ----
        $t5h1 = $this->provisionBranch('+917990000501', 'Test 5 Owner', 'Test 5 Hostel 1', 6, 'test5.owner@test.hostelease.local');
        $this->setCoverage($t5h1, now()->subMonths(6), now()->addMonths(12));

        $t5h2 = $this->provisionBranch('+917990000501', 'Test 5 Owner', 'Test 5 Hostel 2', 1, 'test5.owner@test.hostelease.local');
        $this->setCoverage($t5h2, now()->subMonths(1), now()->addMonths(6));

        // ---- §6 Align — two branches behind by different amounts, for a bulk top-up with varied per-branch charges ----
        $t6h1 = $this->provisionBranch('+917990000601', 'Test 6 Owner', 'Test 6 Hostel 1', 6, 'test6.owner@test.hostelease.local');
        $this->setCoverage($t6h1, now()->subMonths(6), now()->addMonths(12));

        $t6h2 = $this->provisionBranch('+917990000601', 'Test 6 Owner', 'Test 6 Hostel 2', 4, 'test6.owner@test.hostelease.local');
        $this->setCoverage($t6h2, now()->subMonths(4), now()->addMonths(6));

        $t6h3 = $this->provisionBranch('+917990000601', 'Test 6 Owner', 'Test 6 Hostel 3', 2, 'test6.owner@test.hostelease.local');
        $this->setCoverage($t6h3, now()->subMonths(2), now()->addMonths(3));

        // ---- §7 Comp — clean synced pair, ready for a complimentary grant ----
        $t7h1 = $this->provisionBranch('+917990000701', 'Test 7 Owner', 'Test 7 Hostel 1', 4, 'test7.owner@test.hostelease.local');
        $this->setCoverage($t7h1, now()->subMonths(4), now()->addMonths(8));

        $t7h2 = $this->provisionBranch('+917990000701', 'Test 7 Owner', 'Test 7 Hostel 2', 4, 'test7.owner@test.hostelease.local');
        $this->setCoverage($t7h2, now()->subMonths(4), now()->addMonths(8));

        // ---- §8 Set custom price — clean synced pair at list price, ready to bespoke-price then re-renew ----
        $t8h1 = $this->provisionBranch('+917990000801', 'Test 8 Owner', 'Test 8 Hostel 1', 3, 'test8.owner@test.hostelease.local');
        $this->setCoverage($t8h1, now()->subMonths(3), now()->addMonths(9));

        $t8h2 = $this->provisionBranch('+917990000801', 'Test 8 Owner', 'Test 8 Hostel 2', 3, 'test8.owner@test.hostelease.local');
        $this->setCoverage($t8h2, now()->subMonths(3), now()->addMonths(9));

        // ---- §9 Discounts (9.1–9.4) — clean pair, deliberately NO pre-existing discounts so the walkthrough applies cleanly ----
        $t9h1 = $this->provisionBranch('+917990000901', 'Test 9 Owner', 'Test 9 Hostel 1', 5, 'test9.owner@test.hostelease.local');
        $this->setCoverage($t9h1, now()->subMonths(5), now()->addMonths(7));

        $t9h2 = $this->provisionBranch('+917990000901', 'Test 9 Owner', 'Test 9 Hostel 2', 5, 'test9.owner@test.hostelease.local');
        $this->setCoverage($t9h2, now()->subMonths(5), now()->addMonths(7));

        // ---- §10 Volume tiers / negotiated overview — 3 co-terminated branches, ready to trigger the >=3 tier on Renew all ----
        $t10h1 = $this->provisionBranch('+917990001001', 'Test 10 Owner', 'Test 10 Hostel 1', 6, 'test10.owner@test.hostelease.local');
        $this->setCoverage($t10h1, now()->subMonths(6), now()->addMonths(6));

        $t10h2 = $this->provisionBranch('+917990001001', 'Test 10 Owner', 'Test 10 Hostel 2', 6, 'test10.owner@test.hostelease.local');
        $this->setCoverage($t10h2, now()->subMonths(6), now()->addMonths(6));

        $t10h3 = $this->provisionBranch('+917990001001', 'Test 10 Owner', 'Test 10 Hostel 3', 6, 'test10.owner@test.hostelease.local');
        $this->setCoverage($t10h3, now()->subMonths(6), now()->addMonths(6));

        // ---- §12 Regression check — Hostel 1 stays active (login test); Hostel 2 is yours to manually expire via Hostels → Edit ----
        $t12h1 = $this->provisionBranch('+917990001201', 'Test 12 Owner', 'Test 12 Hostel 1', 6, 'test12.owner@test.hostelease.local');
        $this->setCoverage($t12h1, now()->subMonths(6), now()->addMonths(6));

        $t12h2 = $this->provisionBranch('+917990001201', 'Test 12 Owner', 'Test 12 Hostel 2', 6, 'test12.owner@test.hostelease.local');
        $this->setCoverage($t12h2, now()->subMonths(6), now()->addMonths(6));

        $this->command?->info('Phase 4 testing fixtures seeded: Test 1–10 and Test 12 owners/hostels ready.');
    }

    /**
     * Provision one hostel (+ its owner, on first call for a given mobile) the
     * same way the real "+ New Hostel" form does, backdating created_at /
     * subscription_start to $registeredMonthsAgo. Idempotent: a second call
     * with the same (mobile, name) returns the existing row untouched.
     *
     * @return array{hostel: Hostel, admin: User, account: SubscriptionAccount}
     */
    private function provisionBranch(string $mobile, string $ownerName, string $hostelName, int $registeredMonthsAgo, string $email): array
    {
        $existing = Hostel::where('mobile', $mobile)->where('name', $hostelName)->first();

        if ($existing) {
            $admin = User::where('mobile', $mobile)->where('role', 'hostel_admin')->firstOrFail();

            return ['hostel' => $existing, 'admin' => $admin, 'account' => app(AccountBillingService::class)->accountFor($admin)];
        }

        Carbon::setTestNow(now()->subMonths($registeredMonthsAgo));

        try {
            $result = app(HostelService::class)->provision([
                'name' => $hostelName,
                'owner_name' => $ownerName,
                'mobile' => $mobile,
                'email' => $email,
                'address' => 'Testing Fixture Address',
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
                'status' => 'active',
                'plan' => 'yearly',
                'payment_status' => 'paid',
                'payment_method' => 'cash',
            ]);
        } finally {
            Carbon::setTestNow();
        }

        // Predictable login for every seeded owner, regardless of the random
        // password HostelService::provision() would otherwise generate.
        $result['admin']->update(['password' => Hash::make(self::PASSWORD)]);

        return [
            'hostel' => $result['hostel'],
            'admin' => $result['admin'],
            'account' => app(AccountBillingService::class)->accountFor($result['admin']),
        ];
    }

    /** Force a branch's coverage window to an exact value and resync its account anchor. */
    private function setCoverage(array $branch, Carbon $start, Carbon $end, string $status = 'active'): void
    {
        $branch['hostel']->update([
            'subscription_start' => $start,
            'subscription_end' => $end,
            'status' => $status,
        ]);

        app(AccountBillingService::class)->refreshAccountAnchor($branch['account']->fresh(), BillingPeriod::Yearly);
    }

    /** Attach a negotiated discount to an account (upserted by account + reason, so re-runs don't duplicate it). */
    private function seedDiscount(SubscriptionAccount $account, DiscountRecurrence $recurrence, DiscountType $type, float $value, string $reason): void
    {
        Discount::updateOrCreate(
            ['account_id' => $account->id, 'reason' => $reason],
            [
                'branch_id' => null,
                'recurrence' => $recurrence->value,
                'type' => $type->value,
                'value' => $value,
                'status' => DiscountStatus::Active->value,
            ],
        );
    }
}
