<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Day-exact proration (decision D5, finding F2). Each case here is one of the
 * worked examples in _artifact/saas_billing_autopay/04_TARGET_DESIGN.md §6.3, so
 * the doc and the code are checked against each other.
 *
 * What the old inline math got wrong and these lock down:
 *   · the denominator was a hard-coded 365 / 30 instead of the REAL length of the
 *     cycle being prorated into (so February mispriced, leap years mispriced);
 *   · nothing clamped the charge at one unit price, so a branch added against a
 *     two-year-out anchor was quoted ₹20,014 for one branch;
 *   · the day count was not pinned to midnight, so the price depended on the time
 *     of day the operator happened to click.
 */
class ProrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
    }

    /**
     * An account anchored on $anchor, with one branch whose own coverage ends on
     * $branchEnd (null = no coverage).
     *
     * @return array{0: SubscriptionAccount, 1: Hostel}
     */
    private function account(Carbon $anchor, string $period = 'yearly', ?Carbon $branchEnd = null, ?float $unitOverride = null): array
    {
        $mobile = (string) fake()->numerify('9#########');
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => $mobile]);
        $branch = Hostel::factory()->create([
            'mobile' => $mobile,
            'status' => 'active',
            'subscription_end' => $branchEnd,
        ]);
        $owner->hostels()->sync([$branch->id]);

        $account = SubscriptionAccount::create([
            'owner_id' => $owner->id,
            'period' => $period,
            'status' => 'active',
            'current_period_start' => $period === 'monthly' ? $anchor->copy()->subMonth() : $anchor->copy()->subYear(),
            'current_period_end' => $anchor,
            'unit_price_override_yearly' => $period === 'yearly' ? $unitOverride : null,
            'unit_price_override_monthly' => $period === 'monthly' ? $unitOverride : null,
        ]);

        return [$account, $branch];
    }

    private function quote(SubscriptionAccount $account, ?Hostel $branch = null): array
    {
        return app(AccountBillingService::class)->quoteAddBranch($account, $branch);
    }

    public function test_half_a_yearly_cycle_costs_the_day_exact_share(): void
    {
        Carbon::setTestNow('2026-11-09 14:30:00');
        [$account] = $this->account(Carbon::parse('2027-05-09'));

        $q = $this->quote($account);

        $this->assertSame(181, $q['days_remaining']);
        $this->assertSame(365, $q['cycle_days']);
        $this->assertSame(4958.90, $q['prorated']);   // 10000 × 181 / 365
    }

    public function test_one_day_before_the_anchor_costs_one_day(): void
    {
        Carbon::setTestNow('2027-05-08 09:00:00');
        [$account] = $this->account(Carbon::parse('2027-05-09'));

        $q = $this->quote($account);

        $this->assertSame(1, $q['days_remaining']);
        $this->assertSame(27.40, $q['prorated']);     // 10000 / 365
    }

    public function test_on_the_anchor_day_there_is_nothing_to_prorate(): void
    {
        Carbon::setTestNow('2027-05-09 11:00:00');
        [$account] = $this->account(Carbon::parse('2027-05-09'));

        $q = $this->quote($account);

        $this->assertSame(0, $q['days_remaining']);
        $this->assertSame(0.0, $q['prorated']);
    }

    public function test_a_branch_is_never_rebilled_for_coverage_it_already_holds(): void
    {
        Carbon::setTestNow('2026-11-09 08:00:00');
        [$account, $branch] = $this->account(
            Carbon::parse('2027-05-09'),
            branchEnd: Carbon::parse('2027-02-09'),
        );

        $q = $this->quote($account, $branch);

        $this->assertSame(89, $q['days_remaining']);  // 09 Feb → 09 May, not from today
        $this->assertSame(2438.36, $q['prorated']);   // 10000 × 89 / 365
    }

    public function test_half_of_february_costs_half_a_monthly_unit(): void
    {
        // The case a nominal 30-day denominator gets wrong: February is 28 days, so
        // 14 days must be exactly half the month's price — not 14/30 of it.
        Carbon::setTestNow('2027-02-15 10:00:00');
        [$account] = $this->account(Carbon::parse('2027-03-01'), 'monthly');

        $q = $this->quote($account);

        $this->assertSame(14, $q['days_remaining']);
        $this->assertSame(28, $q['cycle_days']);
        $this->assertSame(500.00, $q['prorated']);    // 1000 × 14 / 28
    }

    public function test_a_leap_year_cycle_uses_366_days(): void
    {
        Carbon::setTestNow('2028-01-01 00:30:00');
        [$account] = $this->account(Carbon::parse('2028-03-01'));

        $q = $this->quote($account);

        $this->assertSame(366, $q['cycle_days'], 'The 2027-03-01 → 2028-03-01 cycle spans a leap day.');
        $this->assertSame(60, $q['days_remaining']);
        $this->assertSame(1639.34, $q['prorated']);   // 10000 × 60 / 366
    }

    public function test_a_top_up_is_clamped_at_one_unit_price(): void
    {
        // The ₹20,014 bug: an anchor left two years out by F1 priced a single
        // branch above a full term. A top-up TO the anchor can never cost more than
        // one term — beyond that the account needs a renewal, not a top-up.
        Carbon::setTestNow('2026-10-02 12:00:00');
        [$account] = $this->account(Carbon::parse('2028-10-02'));

        $q = $this->quote($account);

        $this->assertSame(731, $q['days_remaining']);
        $this->assertSame(10000.00, $q['prorated']);
    }

    public function test_the_price_does_not_depend_on_the_time_of_day(): void
    {
        $morning = null;
        foreach (['2026-11-09 00:01:00', '2026-11-09 23:59:00'] as $at) {
            Carbon::setTestNow($at);
            [$account] = $this->account(Carbon::parse('2027-05-09'));
            $q = $this->quote($account);

            $morning ??= $q['prorated'];
            $this->assertSame($morning, $q['prorated'], 'Proration drifted with the clock — day counts are not midnight-pinned.');
        }
    }

    public function test_a_bespoke_unit_price_prorates_at_that_rate(): void
    {
        Carbon::setTestNow('2026-11-09 10:00:00');
        [$account] = $this->account(Carbon::parse('2027-05-09'), unitOverride: 8500.0);

        $q = $this->quote($account);

        $this->assertSame(8500.0, $q['unit']);
        $this->assertSame(4215.07, $q['prorated']);   // 8500 × 181 / 365
    }

    public function test_align_and_add_to_cycle_price_an_identical_branch_identically(): void
    {
        Carbon::setTestNow('2026-11-09 10:00:00');
        [$account, $branch] = $this->account(Carbon::parse('2027-05-09'), branchEnd: Carbon::parse('2027-01-09'));

        $billing = app(AccountBillingService::class);
        $add = $billing->quoteAddBranch($account, $branch);
        $align = $billing->quoteAlign($account);

        $this->assertSame(1, $align['count']);
        $this->assertSame($add['prorated'], $align['lines'][0]['amount']);
        $this->assertSame($add['days_remaining'], $align['lines'][0]['days']);
    }

    public function test_cycle_days_reads_the_real_calendar(): void
    {
        $this->assertSame(365, BillingPeriod::Yearly->cycleDays(Carbon::parse('2027-05-09')));
        $this->assertSame(366, BillingPeriod::Yearly->cycleDays(Carbon::parse('2028-03-01')));
        $this->assertSame(28, BillingPeriod::Monthly->cycleDays(Carbon::parse('2027-03-01')));
        $this->assertSame(31, BillingPeriod::Monthly->cycleDays(Carbon::parse('2027-02-01')));  // January
        $this->assertSame(30, BillingPeriod::Monthly->cycleDays(Carbon::parse('2027-05-01')));  // April
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
