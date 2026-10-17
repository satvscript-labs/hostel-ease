<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\Billing\RevenueMetrics;
use App\Services\HostelService;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S5 revenue metrics (_artifact/saas_billing_autopay/28_S5_DESIGN.md) against a fixture
 * whose answers are worked out by hand, here, from the dates and prices — never by
 * calling the code under test. ₹10,000/yr · ₹1,000/mo · no volume rules · grace 3 days.
 * "Today" is 17 Oct 2026.
 *
 *  A  2 branches yearly from 1 Apr 2026, + a branch added 5 Oct (prorated to 1 Apr 2027)
 *  B  1 branch monthly from 5 Oct
 *  C  yearly 1 Nov 2025 → 1 Nov 2026, renewed IN ADVANCE on 10 Oct (1 Nov 2026 → 2027)
 *  D  yearly 1 Sep 2025 → 1 Sep 2026, then a FREE renewal (₹0) to 1 Sep 2027
 *  E  paid 2 Oct, VOIDED the same day
 *  F  yearly 20 Oct 2025 → 20 Oct 2026, a renewal proforma PENDING since 5 Oct
 *  G  yearly 20 Aug 2025 → 20 Aug 2026, never renewed — LOST in August
 *  H  trial from 1 Sep 2026, subscribed yearly on 15 Sep — CONVERTED
 *  I  trial from 10 Sep 2026, never paid — LAPSED
 *  J  trial from 10 Oct 2026 — RUNNING
 */
class RevenueMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected AccountBillingService $billing;

    protected int $seq = 0;

    /** @var array<string, SubscriptionAccount> */
    protected array $acct = [];

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
        config(['hostelease.grace_days' => 3]);
        $this->billing = app(AccountBillingService::class);
    }

    protected function at(string $when, \Closure $fn): mixed
    {
        $this->travelTo(Carbon::parse($when.' 10:00:00'));
        try {
            return $fn();
        } finally {
            $this->travelBack();
        }
    }

    /** @return array{0: User, 1: list<Hostel>} */
    protected function owner(string $tag, int $branches): array
    {
        $mobile = '9333'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);
        $o = User::factory()->create(['role' => 'hostel_admin', 'mobile' => $mobile, 'name' => "Owner {$tag}"]);
        $b = array_map(fn ($i) => app(HostelService::class)->createBranchForOwner($o, ['name' => "{$tag}{$i}"]), range(1, $branches));

        return [$o, $b];
    }

    protected function build(): void
    {
        // A — 2 branches yearly from 1 Apr 2026; a third added on 5 Oct.
        $this->at('2026-04-01', function () {
            [$o] = $this->owner('A', 2);
            $this->acct['A'] = $this->billing->accountFor($o);
            $this->billing->renewAccount($this->acct['A'], 'yearly', ['payment_status' => 'paid']);
        });
        $this->at('2026-10-05', function () {
            $o = $this->acct['A']->owner;
            $b = app(HostelService::class)->createBranchForOwner($o, ['name' => 'A3']);
            $this->billing->addBranch($this->acct['A']->fresh(), $b, ['payment_status' => 'paid']);
        });

        // B — monthly from 5 Oct.
        $this->at('2026-10-05', function () {
            [$o] = $this->owner('B', 1);
            $this->acct['B'] = $this->billing->accountFor($o);
            $this->billing->renewAccount($this->acct['B'], 'monthly', ['payment_status' => 'paid']);
        });

        // C — yearly from 1 Nov 2025, renewed in advance on 10 Oct 2026.
        $this->at('2025-11-01', function () {
            [$o] = $this->owner('C', 1);
            $this->acct['C'] = $this->billing->accountFor($o);
            $this->billing->renewAccount($this->acct['C'], 'yearly', ['payment_status' => 'paid']);
        });
        $this->at('2026-10-10', fn () => $this->billing->renewAccount($this->acct['C']->fresh(), 'yearly', ['payment_status' => 'paid']));

        // D — yearly from 1 Sep 2025, then a free renewal.
        $this->at('2025-09-01', function () {
            [$o, $b] = $this->owner('D', 1);
            $this->acct['D'] = $this->billing->accountFor($o);
            $this->billing->renewAccount($this->acct['D'], 'yearly', ['payment_status' => 'paid']);
            $b[0]->forceFill(['free_renewals' => 1])->save();
        });
        $this->at('2026-08-25', fn () => $this->billing->renewAccount($this->acct['D']->fresh(), 'yearly', ['payment_status' => 'paid']));

        // E — paid and voided the same day.
        $this->at('2026-10-02', function () {
            [$o] = $this->owner('E', 1);
            $this->acct['E'] = $this->billing->accountFor($o);
            $order = $this->billing->renewAccount($this->acct['E'], 'yearly', ['payment_status' => 'paid']);
            $this->billing->voidOrder($order, 'cheque bounced');
        });

        // F — yearly from 20 Oct 2025; a renewal proforma pending since 5 Oct 2026.
        $this->at('2025-10-20', function () {
            [$o] = $this->owner('F', 1);
            $this->acct['F'] = $this->billing->accountFor($o);
            $this->billing->renewAccount($this->acct['F'], 'yearly', ['payment_status' => 'paid']);
        });
        $this->at('2026-10-05', fn () => $this->billing->renewAccount($this->acct['F']->fresh(), 'yearly', ['payment_status' => 'pending']));

        // G — yearly from 20 Aug 2025, never renewed.
        $this->at('2025-08-20', function () {
            [$o] = $this->owner('G', 1);
            $this->acct['G'] = $this->billing->accountFor($o);
            $this->billing->renewAccount($this->acct['G'], 'yearly', ['payment_status' => 'paid']);
        });

        // H — trial 1 Sep, subscribed yearly 15 Sep.
        $this->at('2026-09-01', function () {
            [$o, $b] = $this->owner('H', 1);
            $this->billing->recordBranchRenewal($b[0], 'trial', ['payment_status' => 'paid']);
            $this->acct['H'] = $this->billing->accountFor($o);
        });
        $this->at('2026-09-15', fn () => $this->billing->renewAccount($this->acct['H']->fresh(), 'yearly', ['payment_status' => 'paid']));

        // I — trial 10 Sep, never paid.  J — trial 10 Oct, still running.
        foreach (['I' => '2026-09-10', 'J' => '2026-10-10'] as $tag => $when) {
            $this->at($when, function () use ($tag) {
                [$o, $b] = $this->owner($tag, 1);
                $this->billing->recordBranchRenewal($b[0], 'trial', ['payment_status' => 'paid']);
                $this->acct[$tag] = $this->billing->accountFor($o);
            });
        }

        $this->travelTo(Carbon::parse('2026-10-17 10:00:00'));
    }

    /** A line's share inside [from, to): amount × overlap days ÷ line days — the definition, by hand. */
    protected static function share(float $amount, string $start, string $end, string $from, string $to): float
    {
        $s = max(strtotime($start), strtotime($from));
        $e = min(strtotime($end), strtotime($to));
        $days = (strtotime($end) - strtotime($start)) / 86400;

        return $e > $s ? $amount * (($e - $s) / 86400) / $days : 0.0;
    }

    protected function metrics(): RevenueMetrics
    {
        return new RevenueMetrics;
    }

    // ── MRR family ──────────────────────────────────────────────────────────

    public function test_mrr_counts_only_paid_lines_live_today_at_their_monthly_rate(): void
    {
        $this->build();
        $m = $this->metrics()->mrrByAccount();
        $y = 10000 / 12;   // a yearly branch

        $this->assertEqualsWithDelta(3 * $y, $m[$this->acct['A']->id], 0.02, 'A: 2 branches + one prorated branch at the same yearly rate');
        $this->assertEqualsWithDelta(1000, $m[$this->acct['B']->id], 0.01, 'B: a full monthly line is ₹1,000');
        $this->assertEqualsWithDelta($y, $m[$this->acct['C']->id], 0.01, 'C: the advance renewal adds nothing until it starts');
        $this->assertArrayNotHasKey($this->acct['D']->id, $m, 'D: on a free renewal — a customer, but ₹0 MRR');
        $this->assertArrayNotHasKey($this->acct['E']->id, $m, 'E: voided');
        $this->assertEqualsWithDelta($y, $m[$this->acct['F']->id], 0.01, 'F: still covered to 20 Oct; the pending renewal is not revenue');
        $this->assertArrayNotHasKey($this->acct['G']->id, $m, 'G: lost');
        $this->assertEqualsWithDelta($y, $m[$this->acct['H']->id], 0.01);
        $this->assertArrayNotHasKey($this->acct['J']->id, $m, 'J: trial');

        $mrr = 6 * $y + 1000;
        $this->assertEqualsWithDelta($mrr, $this->metrics()->mrr(), 0.05);
        $this->assertSame(5, $this->metrics()->payingCustomers());
        $this->assertEqualsWithDelta($mrr / 5, $this->metrics()->arpa(), 0.05);
    }

    public function test_a_february_monthly_line_is_one_month_not_more(): void
    {
        $this->at('2027-02-01', function () {
            [$o] = $this->owner('Feb', 1);
            $this->billing->renewAccount($this->billing->accountFor($o), 'monthly', ['payment_status' => 'paid']);
        });
        $this->travelTo(Carbon::parse('2027-02-15'));

        $this->assertEqualsWithDelta(1000, $this->metrics()->mrr(), 0.01);
    }

    // ── cash vs earned vs deferred ──────────────────────────────────────────

    public function test_cash_is_dated_by_when_it_was_paid_and_net_of_voids(): void
    {
        $this->build();
        $prorated = round(10000 * 178 / 365, 2);   // A3: 5 Oct 2026 → 1 Apr 2027

        $this->assertEqualsWithDelta($prorated + 1000 + 10000, $this->metrics()->cash(Carbon::parse('2026-10-01'), Carbon::parse('2026-11-01')), 0.01,
            'October: A3 + B + C\'s advance renewal; E voided, F pending, J a ₹0 trial');
        $this->assertEqualsWithDelta(10000, $this->metrics()->cash(Carbon::parse('2026-09-01'), Carbon::parse('2026-10-01')), 0.01, 'September: H subscribed');
        $this->assertEqualsWithDelta(0, $this->metrics()->cash(Carbon::parse('2026-08-01'), Carbon::parse('2026-09-01')), 0.01, 'August: D\'s free renewal is ₹0');
    }

    public function test_a_proforma_paid_later_is_cash_in_the_month_it_was_paid(): void
    {
        $order = $this->at('2026-09-28', function () {
            [$o] = $this->owner('K', 1);

            return $this->billing->renewAccount($this->billing->accountFor($o), 'yearly', ['payment_status' => 'pending']);
        });
        $this->assertNull($order->fresh()->paid_at);

        $this->at('2026-10-03', fn () => $this->billing->acceptOrder($order->fresh(), ['payment_method' => 'upi']));

        $this->assertSame('2026-10-03', $order->fresh()->paid_at->toDateString());
        $this->assertEqualsWithDelta(0, $this->metrics()->cash(Carbon::parse('2026-09-01'), Carbon::parse('2026-10-01')), 0.01);
        $this->assertEqualsWithDelta(10000, $this->metrics()->cash(Carbon::parse('2026-10-01'), Carbon::parse('2026-11-01')), 0.01);
    }

    public function test_paid_at_is_stamped_once_and_kept_when_voided(): void
    {
        $order = $this->at('2026-06-01', function () {
            [$o] = $this->owner('L', 1);

            return $this->billing->renewAccount($this->billing->accountFor($o), 'yearly', ['payment_status' => 'paid']);
        });
        $this->assertSame('2026-06-01', $order->fresh()->paid_at->toDateString());

        $this->at('2026-06-10', fn () => $this->billing->voidOrder($order->fresh(), 'test'));

        $this->assertSame('2026-06-01', $order->fresh()->paid_at->toDateString(), 'voiding never rewrites when it was paid');
        $this->assertNull(SubscriptionOrder::where('payment_status', 'pending')->value('paid_at'));
    }

    public function test_revenue_earned_is_accrued_by_day(): void
    {
        $this->build();
        $oct = ['2026-10-01', '2026-11-01'];
        $prorated = round(10000 * 178 / 365, 2);

        $expected = self::share(10000, '2026-04-01', '2027-04-01', ...$oct) * 2      // A1, A2
            + self::share($prorated, '2026-10-05', '2027-04-01', ...$oct)              // A3
            + self::share(1000, '2026-10-05', '2026-11-05', ...$oct)                   // B
            + self::share(10000, '2025-11-01', '2026-11-01', ...$oct)                  // C (first year only)
            + self::share(10000, '2025-10-20', '2026-10-20', ...$oct)                  // F
            + self::share(10000, '2026-09-15', '2027-09-15', ...$oct);                 // H

        $this->assertEqualsWithDelta($expected, $this->metrics()->earned(Carbon::parse($oct[0]), Carbon::parse($oct[1])), 0.05);
        $this->assertLessThan($this->metrics()->cash(Carbon::parse($oct[0]), Carbon::parse($oct[1])), $expected, 'an advance renewal is cash, not revenue');
    }

    public function test_deferred_is_cash_for_service_not_yet_delivered(): void
    {
        $this->build();
        $today = '2026-10-17';
        $far = '2099-01-01';
        $prorated = round(10000 * 178 / 365, 2);

        $expected = self::share(10000, '2026-04-01', '2027-04-01', $today, $far) * 2
            + self::share($prorated, '2026-10-05', '2027-04-01', $today, $far)
            + self::share(1000, '2026-10-05', '2026-11-05', $today, $far)
            + self::share(10000, '2025-11-01', '2026-11-01', $today, $far)
            + 10000                                                                    // C's advance renewal, in full
            + self::share(10000, '2025-10-20', '2026-10-20', $today, $far)
            + self::share(10000, '2026-09-15', '2027-09-15', $today, $far);

        $this->assertEqualsWithDelta($expected, $this->metrics()->deferred(), 0.05);
    }

    // ── receivables ─────────────────────────────────────────────────────────

    public function test_receivables_are_pending_orders_aged_by_when_raised(): void
    {
        $this->build();
        $r = $this->metrics()->receivables();

        $this->assertSame(1, $r['count']);
        $this->assertEqualsWithDelta(10000, $r['total'], 0.01);
        $this->assertEqualsWithDelta(10000, $r['mid'], 0.01, 'raised 12 days ago: 8–30');
        $this->assertSame(0.0, $r['fresh']);
    }

    // ── customers and churn ─────────────────────────────────────────────────

    public function test_customers_covered_include_free_renewals_but_not_trials(): void
    {
        $this->build();

        $this->assertSame(6, $this->metrics()->customersCovered(), 'A B C D F H — D on a free renewal counts; G lost; I J trials');
    }

    public function test_a_customer_is_lost_only_after_grace_and_trials_never_churn(): void
    {
        $this->build();
        $lost = $this->metrics()->lostCustomers();

        $this->assertSame([$this->acct['G']->id], $lost->pluck('account')->all(), 'only G — I (a lapsed trial) is not churn');
        $this->assertSame('2026-08-20', $lost->first()['ended']->toDateString());
        $this->assertEqualsWithDelta(10000 / 12, $lost->first()['mrr'], 0.01, 'the MRR G took with them');

        // F's cover ends 20 Oct: not lost on the 22nd (grace), lost on the 24th.
        $this->assertFalse($this->metrics()->lostCustomers(Carbon::parse('2026-10-22'))->contains('account', $this->acct['F']->id));
        $this->assertTrue($this->metrics()->lostCustomers(Carbon::parse('2026-10-24'))->contains('account', $this->acct['F']->id));
    }

    public function test_churn_rate_is_lost_over_covered_at_the_start_of_the_month(): void
    {
        $this->build();

        // Covered on 1 Aug: A C D F G = 5. Lost in August: G.
        $this->assertSame(20.0, $this->metrics()->churnRate(Carbon::parse('2026-08-01'), Carbon::parse('2026-09-01')));
        $this->assertSame(0.0, $this->metrics()->churnRate(Carbon::parse('2026-09-01'), Carbon::parse('2026-10-01')));
    }

    // ── MRR movement ────────────────────────────────────────────────────────

    public function test_mrr_movement_classifies_every_change_and_adds_up(): void
    {
        $this->build();
        $y = 10000 / 12;
        $mv = $this->metrics()->movement(Carbon::parse('2026-10-01'), Carbon::parse('2026-11-01'));

        $this->assertEqualsWithDelta(5 * $y, $mv['start'], 0.05, '1 Oct: A(2) C F H');
        $this->assertEqualsWithDelta(1000, $mv['new'], 0.01, 'B');
        $this->assertEqualsWithDelta($y, $mv['expansion'], 0.02, 'A added a branch');
        $this->assertEqualsWithDelta($y, $mv['churned'], 0.02, 'F lapsed on 20 Oct, renewal still pending');
        $this->assertSame(0.0, $mv['contraction']);
        $this->assertSame(0.0, $mv['reactivation']);
        $this->assertEqualsWithDelta($mv['end'], $mv['start'] + $mv['new'] + $mv['expansion'] + $mv['reactivation'] - $mv['contraction'] - $mv['churned'], 0.02, 'the identity holds');
    }

    public function test_a_customer_who_comes_back_is_a_reactivation_not_new(): void
    {
        $this->build();
        // G returns on 5 Nov 2026.
        $this->at('2026-11-05', fn () => $this->billing->renewAccount($this->acct['G']->fresh(), 'yearly', ['payment_status' => 'paid']));
        $this->travelTo(Carbon::parse('2026-12-02'));

        $mv = $this->metrics()->movement(Carbon::parse('2026-11-01'), Carbon::parse('2026-12-01'));

        $this->assertEqualsWithDelta(10000 / 12, $mv['reactivation'], 0.02);
        $this->assertSame(0.0, $mv['new']);
    }

    public function test_cancelling_one_of_several_branches_is_contraction(): void
    {
        $this->build();
        // A's cancelled branch keeps its paid line to 1 Apr 2027 — so cancelling changes
        // nothing until then. Measured across 1 Apr 2027, A renews only the other two.
        $a = $this->acct['A']->fresh();
        $branch = Hostel::where('owner_id', $a->owner_id)->where('name', 'A3')->sole();
        $this->billing->cancelBranch($branch, 'closing');
        $this->at('2027-03-25', fn () => $this->billing->renewAccount($a->fresh(), 'yearly', ['payment_status' => 'paid']));
        $this->travelTo(Carbon::parse('2027-04-10'));

        $mv = $this->metrics()->movement(Carbon::parse('2027-03-01'), Carbon::parse('2027-04-02'));

        $this->assertEqualsWithDelta(10000 / 12, $mv['contraction'], 0.02, 'one of three branches gone');
    }

    // ── trials ──────────────────────────────────────────────────────────────

    public function test_trial_cohorts_by_month_of_first_trial(): void
    {
        $this->build();
        $c = $this->metrics()->trialCohorts();

        $this->assertSame(['started' => 2, 'converted' => 1, 'running' => 0, 'lapsed' => 1, 'rate' => 50.0], $c['2026-09'], 'H converted, I lapsed');
        $this->assertSame(['started' => 1, 'converted' => 0, 'running' => 1, 'lapsed' => 0, 'rate' => null], $c['2026-10'], 'J still running — no rate yet');
        $this->assertSame(['2026-10', '2026-09'], array_keys($c), 'newest first; accounts that never had a trial are not a cohort');
    }

    // ── the page ────────────────────────────────────────────────────────────

    public function test_the_revenue_page_shows_the_figures_to_the_operator_only(): void
    {
        $this->build();
        $admin = User::factory()->superAdmin()->create();

        $res = $this->actingAs($admin)->get(route('superadmin.revenue'))->assertOk();
        $res->assertSee(hostelease_money($this->metrics()->mrr()))
            ->assertSee(hostelease_money($this->metrics()->deferred()))
            ->assertSee('Owner G')            // a lost customer, linked to Account 360
            ->assertSee(route('superadmin.accounts.show', $this->acct['G']->public_id), false)
            ->assertSee('50%');               // September's trial conversion

        $this->actingAs($this->acct['A']->owner)->get(route('superadmin.revenue'))->assertRedirect();
    }

    public function test_the_revenue_page_with_no_data_shows_empty_states(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())->get(route('superadmin.revenue'))
            ->assertOk()->assertSee('No payments yet')->assertSee('No trials yet')->assertSee('Nobody has left');
    }

    public function test_the_dashboard_counts_cash_by_when_it_was_paid(): void
    {
        $order = $this->at(now()->subMonthNoOverflow()->startOfMonth()->addDays(20)->toDateString(), function () {
            [$o] = $this->owner('M', 1);

            return $this->billing->renewAccount($this->billing->accountFor($o), 'yearly', ['payment_status' => 'pending']);
        });
        $this->billing->acceptOrder($order->fresh(), ['payment_method' => 'upi']);   // paid THIS month

        $this->actingAs(User::factory()->superAdmin()->create())->get(route('superadmin.dashboard'))
            ->assertOk()->assertSee('Cash collected')
            ->assertViewHas('stats', fn (array $s) => abs($s['monthly_revenue'] - 10000) < 0.01)
            ->assertViewHas('charts', fn (array $c) => abs($c['revenue']->last() - 10000) < 0.01 && abs($c['revenue']->slice(-2, 1)->first()) < 0.01);
    }

    /** Money paid BEFORE a trial (old data, an operator's direct sale) is not a conversion of it. */
    public function test_only_a_payment_after_the_trial_converts_it(): void
    {
        $account = $this->at('2026-01-10', function () {
            [$o] = $this->owner('P', 1);
            $a = $this->billing->accountFor($o);
            $this->billing->renewAccount($a, 'monthly', ['payment_status' => 'paid']);

            return $a;
        });
        $this->at('2026-09-05', function () use ($account) {
            $order = SubscriptionOrder::create(['account_id' => $account->id, 'period' => 'trial', 'kind' => 'trial', 'quantity' => 1, 'subtotal' => 0, 'discount_total' => 0, 'amount' => 0, 'payment_status' => 'paid', 'collection' => 'offline']);
            $order->lines()->create(['branch_id' => Hostel::where('owner_id', $account->owner_id)->value('id'), 'amount' => 0, 'start_date' => '2026-09-05', 'end_date' => '2026-09-19']);
        });
        $this->travelTo(Carbon::parse('2026-10-17'));

        $this->assertSame(['started' => 1, 'converted' => 0, 'running' => 0, 'lapsed' => 1, 'rate' => 0.0], $this->metrics()->trialCohorts()['2026-09']);
    }

    // ── empty ───────────────────────────────────────────────────────────────

    public function test_with_no_data_every_figure_is_zero_never_an_error(): void
    {
        $m = $this->metrics();

        $this->assertSame(0.0, $m->mrr());
        $this->assertSame(0.0, $m->arpa());
        $this->assertSame(0.0, $m->deferred());
        $this->assertNull($m->churnRate(Carbon::parse('2026-09-01'), Carbon::parse('2026-10-01')));
        $this->assertSame([], $m->trialCohorts());
        $this->assertSame(0, $m->receivables()['count']);
    }
}
