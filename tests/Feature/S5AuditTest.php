<?php

namespace Tests\Feature;

use App\Models\SubscriptionOrder;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\Billing\RevenueMetrics;
use App\Services\HostelService;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * S5 audit (_artifact/saas_billing_autopay/29_S5_AUDIT.md) — one regression test per
 * finding, each failing against the code as first built.
 */
class S5AuditTest extends TestCase
{
    use RefreshDatabase;

    protected AccountBillingService $billing;

    protected int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
        config(['hostelease.grace_days' => 3]);
        $this->billing = app(AccountBillingService::class);
    }

    /** One branch on a plan bought at $when. */
    protected function acct(string $when, string $period = 'yearly')
    {
        $this->travelTo(Carbon::parse($when.' 10:00'));
        $o = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9222'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT)]);
        app(HostelService::class)->createBranchForOwner($o, ['name' => 'B'.$this->seq]);
        $a = $this->billing->accountFor($o);
        $this->billing->renewAccount($a, $period, ['payment_status' => 'paid']);
        $this->travelBack();

        return $a->fresh();
    }

    /** B1 — a plan ending on the 1st was last used the day before: that month's loss, in that month's rate. */
    public function test_b1_a_plan_ending_on_the_first_is_the_previous_months_churn(): void
    {
        $this->acct('2025-09-01');                       // ends 1 Sep 2026 — last covered day 31 Aug
        $steady = $this->acct('2025-08-01');             // ends 1 Aug 2026, renewed on time below
        $this->travelTo('2026-07-25 10:00');
        $this->billing->renewAccount($steady->fresh(), 'yearly', ['payment_status' => 'paid']);
        $this->travelTo('2026-10-17');
        $m = new RevenueMetrics;

        $this->assertSame(50.0, $m->churnRate(Carbon::parse('2026-08-01'), Carbon::parse('2026-09-01')), '1 of the 2 covered on 1 Aug left in August');
        $this->assertSame(0.0, $m->churnRate(Carbon::parse('2026-09-01'), Carbon::parse('2026-10-01')), 'not September — it was never used in September');
    }

    /** B2 — a charge a later payment already covered is not owed; it is flagged for voiding, here and on Customers. */
    public function test_b2_owed_counts_only_charges_that_still_buy_something(): void
    {
        $a = $this->acct('2026-01-10');
        $this->travelTo('2026-10-01 10:00');
        $proforma = $this->billing->renewAccount($a->fresh(), 'yearly', ['payment_status' => 'pending', 'collection' => 'offline']);
        $this->billing->renewAccount($a->fresh(), 'yearly', ['payment_status' => 'paid']);   // the customer paid another way
        $other = $this->acct('2026-05-01');
        $this->travelTo('2026-10-05 10:00');
        $this->billing->renewAccount($other->fresh(), 'yearly', ['payment_status' => 'pending', 'collection' => 'offline']); // genuinely owed
        $this->travelTo('2026-10-17');

        $r = (new RevenueMetrics)->receivables();
        $this->assertSame([10000.0, 1, 10000.0, 1], [$r['total'], $r['count'], $r['stale'], $r['stale_count']]);

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get(route('superadmin.accounts.index'))->assertSee('already covered — void it');
        $this->actingAs($admin)->get(route('superadmin.revenue'))->assertSee('1 charge already covered — void it');

        $this->billing->voidOrder($proforma->fresh(), 'overtaken');
        $this->assertSame(0, (new RevenueMetrics)->receivables()['stale_count']);
    }

    /** B3 — a negotiated two-year top-up (₹19,000) bought two terms; the audit must not call one of them over-granted. */
    public function test_b3_audit_accepts_a_negotiated_multi_term_top_up(): void
    {
        $a = $this->acct('2026-10-17');
        $this->travelTo('2026-10-17 11:00');
        $this->billing->renewAccount($a->fresh(), 'yearly', ['payment_status' => 'paid']);   // a year ahead
        $second = app(HostelService::class)->createBranchForOwner($a->owner, ['name' => 'Second']);
        $this->billing->addBranch($a->fresh(), $second, ['payment_status' => 'paid', 'amount' => 19000]);

        Artisan::call('hostelease:audit-coverage');

        $this->assertStringContainsString('Every branch holds exactly the coverage its payments bought', Artisan::output());
    }

    /** B3 — a year and ~200 days, negotiated 5% down (≈1.47 units): honest, not over-granted. */
    public function test_b3_a_negotiated_term_and_a_part_is_not_over_granted(): void
    {
        $a = $this->acct(now()->subDays(165)->toDateString());   // 200 days left
        $this->travelTo(now()->setTime(11, 0));
        $this->billing->renewAccount($a->fresh(), 'yearly', ['payment_status' => 'paid']);   // a year ahead
        $second = app(HostelService::class)->createBranchForOwner($a->owner, ['name' => 'Second']);
        $q = $this->billing->quoteAddBranch($a->fresh(), $second);
        $this->billing->addBranch($a->fresh(), $second, ['payment_status' => 'paid', 'amount' => round((float) $q['prorated'] * 0.95, 2)]);

        Artisan::call('hostelease:audit-coverage');

        $this->assertStringContainsString('Every branch holds exactly the coverage its payments bought', Artisan::output());
    }

    /** B3 — and F1's doubled year (one term paid, two held) is still caught. */
    public function test_b3_audit_still_catches_one_term_paid_two_held(): void
    {
        $a = $this->acct('2026-01-10');
        $branch = $a->owner->hostels()->first();
        $branch->forceFill(['subscription_end' => Carbon::parse('2028-01-10')])->save();

        Artisan::call('hostelease:audit-coverage');
        $out = Artisan::output();   // read ONCE: the buffer empties on read

        $this->assertStringNotContainsString('Every branch holds exactly the coverage its payments bought', $out);
        $this->assertMatchesRegularExpression('/'.preg_quote($branch->name, '/').'.*?\| 365 /', $out, 'its row shows 365 days over');
    }

    /** B3 — a single LINE spanning two years but paid as one term is over-granted (F1's shape, in the ledger itself). */
    public function test_b3_a_two_year_line_paid_as_one_term_is_caught(): void
    {
        $a = $this->acct('2026-01-10');
        $line = SubscriptionOrder::where('account_id', $a->id)->sole()->lines()->sole();
        $line->forceFill(['end_date' => Carbon::parse('2028-01-10')])->save();   // ₹10,000 for 730 days
        $line->branch->forceFill(['subscription_end' => Carbon::parse('2028-01-10')])->save();

        Artisan::call('hostelease:audit-coverage');
        $out = Artisan::output();   // read ONCE: the buffer empties on read

        $this->assertStringNotContainsString('Every branch holds exactly the coverage its payments bought', $out);
        $this->assertMatchesRegularExpression('/'.preg_quote($line->branch->name, '/').'.*?\| 365 /', $out, 'its row shows 365 days over');
    }

    /** B5 — the paying tile says what its two numbers are. */
    public function test_b5_paying_customers_reads_n_of_m(): void
    {
        $this->acct('2026-06-01');
        $this->travelTo('2026-10-17');

        $this->actingAs(User::factory()->superAdmin()->create())->get(route('superadmin.revenue'))
            ->assertSee('1 <span class="fs-6 opacity-50 fw-normal">of 1</span>', false);
    }

    /** B6 — the chart's month-end MRR is the same number as How MRR moved's "End". */
    public function test_b6_month_end_mrr_matches_the_movement_end(): void
    {
        $this->acct('2025-10-01');                            // ends 1 Oct 2026, not renewed: 30 Sep ≠ 1 Oct
        $this->acct('2026-09-15', 'monthly');
        $this->travelTo('2026-10-17');

        $res = $this->actingAs(User::factory()->superAdmin()->create())->get(route('superadmin.revenue'))->assertOk();
        $months = collect($res->viewData('months'))->keyBy('key');
        $movement = collect($res->viewData('movement'))->keyBy('label');

        $this->assertEqualsWithDelta($movement['Sep 26']['end'], $months['2026-09']['mrr'], 0.001);
        $this->assertEqualsWithDelta($movement['Oct 26']['end'], $months['2026-10']['mrr'], 0.001, 'this month: both as of today');
    }

    /** A paid order without paid_at (should never exist) never breaks the money pages. */
    public function test_a_paid_order_missing_paid_at_breaks_nothing(): void
    {
        $this->acct(now()->toDateString());
        SubscriptionOrder::query()->update(['paid_at' => null]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('superadmin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('superadmin.revenue'))->assertOk();
    }
}
