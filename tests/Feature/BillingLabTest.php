<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\BillingMode;
use App\Models\Hostel;
use App\Models\RenewalReminderSnooze;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\Billing\RenewalReminders;
use App\Services\HostelService;
use App\Support\Tenant;
use Database\Seeders\BillingLabPurgeSeeder;
use Database\Seeders\BillingLabSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The billing lab (database/seeders/BillingLabSeeder.php · doc 27) and the three defects
 * building it surfaced (L1–L3). The lab test asserts every customer is in the state its
 * NAME promises — so the lab can never quietly drift from the manual test it serves.
 */
class BillingLabTest extends TestCase
{
    use RefreshDatabase;

    protected AccountBillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
        $this->billing = app(AccountBillingService::class);
        config(['hostelease.grace_days' => 3, 'hostelease.renewal_reminder_days' => [30, 15, 7, 0], 'hostelease.owner_self_serve' => true]);
    }

    // ── L3: a gap longer than one term is priced per term ─────────────────

    /** @return array{0: User, 1: SubscriptionAccount} */
    protected function renewedAhead(string $period = 'yearly', int $times = 2): array
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9444400001']);
        app(HostelService::class)->createBranchForOwner($owner, ['name' => 'First']);
        $account = $this->billing->accountFor($owner);
        foreach (range(1, $times) as $i) {
            $this->billing->renewAccount($account->fresh(), $period, ['payment_status' => 'paid']);
        }

        return [$owner, $account->fresh()];
    }

    public function test_l3_add_to_cycle_on_an_account_renewed_a_year_ahead_costs_two_years(): void
    {
        [$owner, $account] = $this->renewedAhead();
        $second = app(HostelService::class)->createBranchForOwner($owner, ['name' => 'Second']);

        $q = $this->billing->quoteAddBranch($account, $second);

        $this->assertSame(20000.0, (float) $q['prorated'], '731 days = two whole yearly terms');
    }

    public function test_l3_monthly_renewed_three_months_ahead_tops_up_for_three_months(): void
    {
        [$owner, $account] = $this->renewedAhead('monthly', 3);
        $second = app(HostelService::class)->createBranchForOwner($owner, ['name' => 'Second']);

        $topups = $this->billing->quoteRenewal($account, 'monthly')['topups'];

        $this->assertCount(1, $topups);
        $this->assertSame(3000.0, (float) $topups[0]['amount'], 'three whole monthly terms behind = three units, not one');
    }

    public function test_l3_within_one_term_the_price_is_unchanged(): void
    {
        [$owner, $account] = $this->renewedAhead('yearly', 1);
        $this->travel(73)->days(); // 292 days of 365 left
        $second = app(HostelService::class)->createBranchForOwner($owner, ['name' => 'Second']);

        $q = $this->billing->quoteAddBranch($account->fresh(), $second);

        $this->assertEqualsWithDelta(10000 * $q['days_remaining'] / 365, (float) $q['prorated'], 0.01);
        $this->assertLessThan(10000, (float) $q['prorated']);
    }

    /** The audit agrees: a top-up that paid for two terms holds two terms, not "one term + over-granted". */
    public function test_l3_the_audit_accepts_a_correctly_paid_multi_term_top_up(): void
    {
        [$owner, $account] = $this->renewedAhead();
        $second = app(HostelService::class)->createBranchForOwner($owner, ['name' => 'Second']);
        $this->billing->addBranch($account, $second, ['payment_status' => 'paid']);

        Artisan::call('hostelease:audit-coverage');

        $this->assertStringContainsString('Every branch holds exactly the coverage its payments bought', Artisan::output());
    }

    /** …and a customer who lapsed and came back holds nothing they did not pay for. */
    public function test_the_audit_does_not_count_a_lapse_as_over_granted(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9444400002']);
        app(HostelService::class)->createBranchForOwner($owner, ['name' => 'Only']);
        $account = $this->billing->accountFor($owner);
        $this->travelTo(now()->subDays(420));
        $this->billing->renewAccount($account, 'yearly', ['payment_status' => 'paid']);
        $this->travelBack();
        $this->billing->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'paid']); // back after a 55-day lapse

        Artisan::call('hostelease:audit-coverage');

        $this->assertStringContainsString('Every branch holds exactly the coverage its payments bought', Artisan::output());
    }

    // ── L1: an extension is part of the date, never folded away ───────────

    public function test_l1_fix_keeps_an_extension_of_the_renewal_date(): void
    {
        [, $account] = $this->renewedAhead('yearly', 1);
        $before = $account->current_period_end->copy();
        $this->billing->extendRenewalDate($account, 15, 'days', 'goodwill');

        $this->assertSame([], $this->billing->foldGiftsIntoFreeRenewals($account->fresh(), dryRun: true));
        Artisan::call('hostelease:audit-coverage', ['--fix' => true, '--force' => true]);

        $this->assertTrue($account->fresh()->current_period_end->equalTo($before->copy()->addDays(15)), 'the 15 days are still there');
    }

    // ── L2: a closing account is not "drift" ──────────────────────────────

    public function test_l2_the_audit_does_not_flag_a_closing_account(): void
    {
        [$owner, $account] = $this->renewedAhead('yearly', 1);
        $this->billing->cancelBranch(Hostel::where('owner_id', $owner->id)->sole(), 'closing');
        $this->billing->refreshAccountAnchor($account->fresh());

        Artisan::call('hostelease:audit-coverage');

        $this->assertStringContainsString('Every account anchor matches its furthest branch', Artisan::output());
    }

    // ── L4: never void dates a later payment is built on ──────────────────

    public function test_l4_an_extension_cannot_be_voided_once_a_renewal_is_bought_on_top(): void
    {
        [$owner, $account] = $this->renewedAhead('yearly', 1);
        $extension = $this->billing->extendRenewalDate($account, 15, 'days', 'goodwill');
        $this->billing->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'paid']); // starts ON the extended date
        $end = $account->fresh()->current_period_end->copy();

        $this->assertNotNull($this->billing->voidBlockedReason($extension));
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->patch(route('superadmin.accounts.orders.void', $account), ['order_id' => $extension->id, 'reason' => 'oops'], ['HTTP_REFERER' => '/'])
            ->assertSessionHas('warning');

        $this->assertSame('paid', $extension->fresh()->payment_status->value, 'not voided');
        $this->assertTrue($account->fresh()->current_period_end->equalTo($end));

        $this->expectException(\RuntimeException::class);
        $this->billing->voidOrder($extension->fresh(), 'service backstop');
    }

    public function test_l4_newest_first_still_works(): void
    {
        [, $account] = $this->renewedAhead('yearly', 1);
        $extension = $this->billing->extendRenewalDate($account, 15, 'days', 'goodwill');
        $renewal = $this->billing->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'paid']);

        $this->billing->voidOrder($renewal, 'cheque bounced');   // the newest one first
        $this->billing->voidOrder($extension->fresh(), 'withdrawn'); // now nothing sits on it

        $this->assertSame('voided', $extension->fresh()->payment_status->value);
    }

    public function test_l4_a_void_with_nothing_built_on_it_is_unchanged(): void
    {
        [, $account] = $this->renewedAhead('yearly', 1);
        $only = $account->orders()->sole();

        $this->assertNull($this->billing->voidBlockedReason($only));
        $this->billing->voidOrder($only, 'bounced');
        $this->assertSame(AccountStatus::Expired, $account->fresh()->status);
    }

    // ── L5: an owner's attempt a payment made pointless is cleared ────────

    public function test_l5_an_owners_covered_checkout_attempt_is_voided_when_a_payment_covers_it(): void
    {
        config(['services.razorpay.enabled' => true, 'services.razorpay.key' => 'rzp_test_key', 'services.razorpay.secret' => 'rzp_test_secret']);
        \Illuminate\Support\Facades\Http::fake(['api.razorpay.com/v1/orders/*/payments' => \Illuminate\Support\Facades\Http::response(['items' => []], 200)]);

        [$owner, $account] = $this->renewedAhead('yearly', 1);
        $second = app(HostelService::class)->createBranchForOwner($owner, ['name' => 'Second']);
        $attempt = $this->billing->addBranch($account, $second, ['payment_status' => 'pending', 'collection' => 'checkout']);
        $attempt->forceFill(['razorpay_order_id' => 'order_L5'])->save();
        $this->assertSame(1, SubscriptionOrder::outstanding()->count());

        // The operator then renews everything — the top-up covers "Second".
        $renewal = $this->billing->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'paid']);
        app(\App\Services\Billing\PaymentLinkService::class)->cancelOvertakenLinks($account->fresh(), $renewal->id);

        $this->assertSame('voided', $attempt->fresh()->payment_status->value);
        $this->assertSame(0, SubscriptionOrder::outstanding()->count(), 'nothing left in receivables that nobody owes');
    }

    public function test_l5_an_attempt_with_a_payment_in_flight_is_left_alone(): void
    {
        config(['services.razorpay.enabled' => true, 'services.razorpay.key' => 'rzp_test_key', 'services.razorpay.secret' => 'rzp_test_secret']);
        \Illuminate\Support\Facades\Http::fake(['api.razorpay.com/v1/orders/*/payments' => \Illuminate\Support\Facades\Http::response(['items' => [['id' => 'pay_1', 'status' => 'authorized', 'amount' => 100]]], 200)]);

        [$owner, $account] = $this->renewedAhead('yearly', 1);
        $second = app(HostelService::class)->createBranchForOwner($owner, ['name' => 'Second']);
        $attempt = $this->billing->addBranch($account, $second, ['payment_status' => 'pending', 'collection' => 'checkout']);
        $attempt->forceFill(['razorpay_order_id' => 'order_L5b'])->save();

        $renewal = $this->billing->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'paid']);
        app(\App\Services\Billing\PaymentLinkService::class)->cancelOvertakenLinks($account->fresh(), $renewal->id);

        $this->assertSame('pending', $attempt->fresh()->payment_status->value, 'a payment in flight is never turned into a refund case');
    }

    public function test_l5_an_attempt_that_still_buys_something_is_kept(): void
    {
        config(['services.razorpay.enabled' => true, 'services.razorpay.key' => 'rzp_test_key', 'services.razorpay.secret' => 'rzp_test_secret']);
        \Illuminate\Support\Facades\Http::fake(['api.razorpay.com/v1/orders/*/payments' => \Illuminate\Support\Facades\Http::response(['items' => []], 200)]);

        [$owner, $account] = $this->renewedAhead('yearly', 1);
        $second = app(HostelService::class)->createBranchForOwner($owner, ['name' => 'Second']);
        $attempt = $this->billing->addBranch($account, $second, ['payment_status' => 'pending', 'collection' => 'checkout']);

        app(\App\Services\Billing\PaymentLinkService::class)->cancelOvertakenLinks($account->fresh());

        $this->assertSame('pending', $attempt->fresh()->payment_status->value);
    }

    // ── the lab itself ─────────────────────────────────────────────────────

    protected function lab(string $code): SubscriptionAccount
    {
        $owner = User::where('name', 'like', "Lab {$code} %")->where('role', 'hostel_admin')->where('mobile', 'like', '%0')->firstOrFail();

        return SubscriptionAccount::where('owner_id', $owner->id)->firstOrFail();
    }

    protected function days(SubscriptionAccount $a): int
    {
        return (int) $a->daysUntilAnchor();
    }

    public function test_the_lab_builds_rebuilds_and_every_customer_is_what_its_name_says(): void
    {
        $this->seed(BillingLabSeeder::class);
        $this->seed(BillingLabSeeder::class); // re-runnable: rebuilds, never duplicates

        $this->assertSame(63, User::where('name', 'like', 'Lab S%')->where('mobile', 'like', '%00000')->count());
        $reminders = app(RenewalReminders::class);

        // The ledger agrees with every date it made.
        Artisan::call('hostelease:audit-coverage');
        $audit = Artisan::output();
        $this->assertStringContainsString('Every branch holds exactly the coverage its payments bought', $audit);
        $this->assertStringContainsString('Every account anchor matches its furthest branch', $audit);
        $this->assertStringContainsString('Every branch ends on its account\'s renewal date', $audit);
        $this->assertStringContainsString('Every account cycle is one term long', $audit);

        // S1
        $this->assertSame(5, $this->billing->includedBranches($this->lab('S1-02'))->count());
        $this->assertSame(3, $this->billing->quoteRenewal($this->lab('S1-31'), 'yearly')['quantity']);
        $this->assertNotNull(Hostel::where('name', 'S1-33 · Asked To Remove')->value('cancellation_requested_at'));
        $this->assertNotNull(Hostel::where('name', 'S1-34 · Cancelled Restore Me')->value('cancelled_at'));
        $this->assertTrue($this->billing->includedBranches($this->lab('S1-36'))->isEmpty(), 'S1-36 is closing');
        $this->assertSame(1, $this->lab('S1-41')->orders()->where('payment_status', 'pending')->count());
        $this->assertSame(1, $this->lab('S1-42')->orders()->count());
        $this->assertSame(AccountStatus::Suspended, $this->lab('S1-61')->status);
        $this->assertSame(3, $this->billing->quoteRenewal($this->lab('S1-61'), 'yearly')['quantity'], 'R1: never zero');
        $this->assertSame(AccountStatus::Expired, $this->lab('S1-62')->status);
        $this->assertSame(12, $this->billing->includedBranches($this->lab('S1-07'))->count());

        // S2
        $this->assertSame(1, $this->billing->quoteAlign($this->lab('S2-61'))['count']);
        $this->assertSame(3, $this->billing->quoteAlign($this->lab('S2-65'))['count']);

        // S3
        $this->assertSame(8000.0, (float) $this->lab('S3-03')->unit_price_override_yearly);
        $this->assertSame(18000.0, (float) $this->lab('S3-04')->orders()->where('payment_status', 'pending')->value('amount'));
        $this->assertSame(AccountStatus::Suspended, $this->lab('S3-08')->status);
        $this->assertSame('checkout', $this->lab('S3-09')->orders()->where('payment_status', 'pending')->first()->collection->value);
        $this->assertSame(BillingMode::Managed, $this->lab('S3-20')->billing_mode);
        $this->assertCount(2, $this->billing->quoteRenewal($this->lab('S3-21'), 'yearly')['topups']);
        $this->assertTrue($this->billing->trialJoinable($this->lab('S3-22')));
        $this->assertSame(AccountStatus::Expired, $this->lab('S3-23')->status);
        $this->assertSame(0.0, (float) $this->billing->quoteRenewal($this->lab('S3-24'), 'yearly')['total']);
        $this->assertSame(0.0, (float) $this->billing->quoteRenewal($this->lab('S3-25'), 'monthly')['total']);
        $this->assertGreaterThan(0, (float) $this->billing->quoteRenewal($this->lab('S3-25'), 'yearly')['total']);
        $this->assertGreaterThan(0, (float) $this->billing->quoteRenewal($this->lab('S3-26'), 'yearly')['total']);
        $this->assertSame(3, $this->billing->quoteAlign($this->lab('S3-27'))['count']);
        $this->assertGreaterThanOrEqual(3, $this->lab('S3-28')->orders()->where('payment_status', 'paid')->count());
        $this->assertSame(0.0, (float) $this->billing->quoteRenewal($this->lab('S3-29'), 'monthly')['total']);
        $this->assertSame(9000.0, (float) $this->billing->quoteRenewal($this->lab('S3-29'), 'yearly')['total']);

        // S5 — each one shows what its name says on the Revenue page.
        $rev = new \App\Services\Billing\RevenueMetrics;
        $mrr = $rev->mrrByAccount();
        $this->assertGreaterThan(9000, $rev->deferred() - 0, 'S5-01 adds a whole year of deferred revenue');
        $this->assertEqualsWithDelta(10000 / 12, $mrr[$this->lab('S5-01')->id], 0.02, 'S5-01: the advance year adds no MRR yet');
        $this->assertArrayHasKey($this->lab('S5-02')->id, $mrr);
        $this->assertTrue($rev->lines()->contains(fn ($l) => $l->account === $this->lab('S5-02')->id && $l->start->lessThan(now()->subDays(60))), 'S5-02 had paid before — a comeback');
        $this->assertArrayHasKey($this->lab('S5-03')->id, $mrr);
        $this->assertArrayNotHasKey($this->lab('S5-04')->id, $mrr, 'S5-04: voided');
        $this->assertSame(now()->subDays(3)->toDateString(), $this->lab('S5-05')->orders()->where('payment_status', 'paid')->latest('id')->first()->paid_at->toDateString());
        $this->assertEqualsWithDelta(2 * 10000 / 12, $mrr[$this->lab('S5-06')->id], 0.02, 'S5-06: two branches now');
        $this->assertSame(1, $rev->receivables()['stale_count'], 'S5-07: the one overtaken proforma — flagged, not owed');
        $s508 = $rev->lostCustomers()->firstWhere('account', $this->lab('S5-08')->id);
        if (now()->day > 1 + (int) config('hostelease.grace_days') + 1) {   // past its grace window this month
            $this->assertSame(now()->startOfMonth()->toDateString(), $s508['ended']->toDateString(), 'S5-08 ended on the 1st');
        }

        // S4 — the card stage each one is named for
        $expect = ['S4-01' => null, 'S4-02' => 'calm', 'S4-03' => 'soon', 'S4-04' => 'final', 'S4-05' => 'today', 'S4-06' => 'grace',
            'S4-07' => 'expired', 'S4-08' => null, 'S4-09' => 'soon', 'S4-10' => 'calm', 'S4-14' => 'calm', 'S4-16' => 'soon'];
        foreach ($expect as $code => $tone) {
            $this->assertSame($tone, $reminders->stage($this->lab($code))['tone'] ?? null, "{$code} stage");
        }
        $this->assertSame(BillingMode::Managed, $this->lab('S4-11')->billing_mode);
        $this->assertNull($this->lab('S4-12')->owner->email);
        $this->assertNull($this->lab('S4-13')->owner->email_verified_at);
        $this->assertSame(1, RenewalReminderSnooze::where('account_id', $this->lab('S4-14')->id)->count());
        $this->assertSame(2, $this->lab('S4-15')->reminderLogs()->count());
        $this->assertTrue($reminders->context($this->lab('S4-17'))['free']);

        // Logins follow the 70 P NN 0000 R pattern; co-admins and staff exist where named.
        $this->assertTrue(User::where('mobile', '+917041600001')->where('role', 'hostel_admin')->exists());
        $this->assertTrue(User::where('mobile', '+917041600002')->where('role', 'manager')->exists());

        // And the purge removes it all, leaving nothing behind.
        $this->seed(BillingLabPurgeSeeder::class);
        $this->assertSame(0, User::withTrashed()->where('name', 'like', 'Lab S%')->count());
        $this->assertSame(0, Hostel::withTrashed()->where('name', 'like', 'S_-__ · %')->count());
        $this->assertSame(0, SubscriptionOrder::withTrashed()->where('remarks', 'like', 'Lab S%')->count());
    }
}
