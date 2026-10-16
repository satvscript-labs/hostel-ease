<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\Notification;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionReminderLog;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\Billing\RenewalReminders;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * S4 audit (_artifact/saas_billing_autopay/25_S4_AUDIT.md) — one regression test per
 * finding, each written to FAIL against the code as built and pass after the fix.
 */
class S4AuditTest extends TestCase
{
    use RefreshDatabase;

    protected static int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
        config([
            'hostelease.grace_days' => 3,
            'hostelease.renewal_reminder_days' => [30, 15, 7, 0],
            'hostelease.owner_self_serve' => true,
            'services.razorpay.enabled' => true,
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
        ]);
        Mail::fake();
    }

    /** @return array{0: User, 1: SubscriptionAccount, 2: Hostel} */
    protected function acct(int $days, string $status = 'active', string $period = 'yearly'): array
    {
        $seq = ++static::$seq;
        $mobile = '95555'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
        $o = User::factory()->create(['role' => 'hostel_admin', 'mobile' => $mobile, 'email' => "a{$seq}@example.com", 'email_verified_at' => now()]);
        $end = now()->addDays($days)->startOfDay();
        $h = Hostel::factory()->create(['mobile' => $mobile, 'owner_id' => $o->id, 'status' => 'active', 'subscription_end' => $end]);
        $o->hostels()->sync([$h->id]);
        $o->forceFill(['hostel_id' => $h->id])->save();
        $a = SubscriptionAccount::create(['owner_id' => $o->id, 'period' => $period, 'status' => $status, 'current_period_end' => $end]);

        return [$o, $a, $h];
    }

    /** A1 — the morning after the renewal date, before the 07:30 job: the card must not vanish. */
    public function test_a1_an_overdue_account_keeps_its_card_before_the_daily_job_runs(): void
    {
        [$o, $a] = $this->acct(-1, 'active'); // stored status is yesterday's

        $this->assertSame('grace', app(RenewalReminders::class)->stage($a)['tone']);
        // The daily job refreshes the status first, but the service never relies on it.
        $this->assertSame('grace', app(RenewalReminders::class)->dueWindow($a));
        $this->actingAs($o)->get(route('admin.dashboard'))->assertSee('rc rc--grace', false)->assertSee('to keep access');
    }

    /** A2 — with self-serve off for everyone the owner cannot pay; the card must say who renews. */
    public function test_a2_the_card_says_we_renew_when_self_serve_is_off_for_everyone(): void
    {
        config(['hostelease.owner_self_serve' => false]);
        [$o] = $this->acct(5);

        $this->actingAs($o)->get(route('admin.dashboard'))
            ->assertSee('We look after your billing — our team will be in touch to renew it.')
            ->assertDontSee('Review & renew');
    }

    /** A3 — a run that died after claiming the window: the retry must still post the bell. */
    public function test_a3_a_crashed_claim_still_posts_the_bell_on_retry(): void
    {
        [, $a, $h] = $this->acct(7);
        SubscriptionReminderLog::create(['account_id' => $a->id, 'cycle_end' => $a->current_period_end->toDateString(), 'window' => 'due_7',
            'channel' => 'in_app', 'status' => 'pending', 'attempts' => 0, 'detail' => []]);
        SubscriptionReminderLog::query()->update(['updated_at' => now()->subHour()]);

        $this->artisan('hostelease:process-subscription-lifecycle')->assertSuccessful();

        $this->assertSame(1, Notification::where('hostel_id', $h->id)->where('type', 'renewal_reminder')->count());
        $this->assertSame('sent', SubscriptionReminderLog::sole()->status);
        Mail::assertSentCount(1);
    }

    /** A4 — a pre-S4 log row is shown as what it was, never as delivered in-app. */
    public function test_a4_a_pre_s4_row_is_not_claimed_as_delivered_in_app(): void
    {
        [, $a] = $this->acct(5);
        SubscriptionReminderLog::create(['account_id' => $a->id, 'cycle_end' => $a->current_period_end->toDateString(), 'window' => 'due_30',
            'channel' => 'email', 'status' => 'skipped_no_email', 'attempts' => 1, 'detail' => null]);

        $html = $this->actingAs(User::factory()->superAdmin()->create())->get(route('superadmin.accounts.show', $a))->getContent();
        $row = substr($html, strpos($html, '30 days before'), 600);

        $this->assertStringContainsString('No email', $row);
        $this->assertStringNotContainsString('In app', $row);
    }

    /** A5 — "Renew for free" must actually renew: a ₹0 renewal settles without Razorpay. */
    public function test_a5_a_fully_free_renewal_completes_and_spends_the_free_renewal(): void
    {
        Http::fake(); // nothing may reach Razorpay
        [$o, $a, $h] = $this->acct(5);
        app(AccountBillingService::class)->recordBranchRenewal($h, 'yearly', ['payment_status' => 'paid']);
        $h->refresh()->forceFill(['free_renewals' => 1])->save();
        $before = $a->fresh()->current_period_end->copy();

        $this->actingAs($o)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'yearly'])
            ->assertOk()->assertJsonPath('mode', 'paid');

        $this->assertTrue($a->fresh()->current_period_end->equalTo($before->copy()->addYear()));
        $this->assertSame(0, (int) $h->fresh()->free_renewals, 'the free renewal is spent');
        $order = SubscriptionOrder::where('account_id', $a->id)->latest('id')->first();
        $this->assertSame(['paid', 0.0], [$order->payment_status->value, (float) $order->amount]);
        Http::assertNothingSent();
    }

    /** A5 — the free path is only for a truly free total: one paying branch still goes to Razorpay. */
    public function test_a5_a_partly_free_renewal_is_still_charged(): void
    {
        [$o, $a, $h] = $this->acct(5);
        $billing = app(AccountBillingService::class);
        $billing->recordBranchRenewal($h, 'yearly', ['payment_status' => 'paid']);
        $second = Hostel::factory()->create(['mobile' => $o->mobile, 'owner_id' => $o->id, 'status' => 'active', 'subscription_end' => $a->fresh()->current_period_end]);
        $o->hostels()->attach($second->id);
        $h->refresh()->forceFill(['free_renewals' => 1])->save();
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_A5', 'amount' => 1, 'currency' => 'INR'], 200)]);

        $this->actingAs($o)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'yearly'])
            ->assertOk()->assertJsonPath('mode', 'checkout');

        $this->assertSame(1, (int) $h->fresh()->free_renewals, 'nothing is spent until it is paid');
    }

    /** A6 — a monthly account's free renewal is a free MONTH, never a free year. */
    public function test_a6_a_free_renewal_is_worth_one_renewal_of_the_accounts_own_term(): void
    {
        [$o, $a, $h] = $this->acct(5, 'active', 'monthly');
        $h->forceFill(['free_renewals' => 1])->save();
        $billing = app(AccountBillingService::class);

        $this->assertSame(0.0, (float) $billing->quoteRenewal($a->fresh(), 'monthly')['total'], 'used on its own term');
        $yearly = $billing->quoteRenewal($a->fresh(), 'yearly');
        $this->assertSame($billing->unitPrice($a->fresh(), \App\Enums\BillingPeriod::Yearly), (float) $yearly['total'], 'not used on the other term');
        $this->assertSame(1, $yearly['gifts_waiting']);

        // And the owner is told why, rather than silently charged.
        $this->actingAs($o)->get(route('admin.subscription.index'))->assertSee('Your free renewal is for a monthly renewal');
    }

    /** A6 — a trial account (no paid term yet) uses its free renewal on whichever term it picks. */
    public function test_a6_a_trial_uses_its_free_renewal_on_the_term_it_picks(): void
    {
        [, $a, $h] = $this->acct(5, 'trial', 'trial');
        $h->forceFill(['free_renewals' => 1])->save();

        $this->assertSame(0.0, (float) app(AccountBillingService::class)->quoteRenewal($a->fresh(), 'yearly')['total']);
    }

    /** A7 — a new stage is a fresh bell entry, not the unread old one rewritten under its old time. */
    public function test_a7_a_new_stage_is_a_fresh_bell_entry(): void
    {
        [, $a, $h] = $this->acct(7);
        $this->artisan('hostelease:process-subscription-lifecycle');
        $this->travelTo($a->current_period_end->copy()->setTime(9, 0));
        $this->artisan('hostelease:process-subscription-lifecycle');

        $n = Notification::where('hostel_id', $h->id)->where('type', 'renewal_reminder')->whereNull('read_at')->get();
        $this->assertCount(1, $n);
        $this->assertTrue($n->first()->created_at->isToday());
        $this->assertStringContainsString('today', $n->first()->title);
    }

    /** Snooze is an admin's alone: staff cannot reach it. */
    public function test_staff_cannot_snooze(): void
    {
        [, , $h] = $this->acct(20);
        $staff = User::factory()->create(['role' => 'manager', 'hostel_id' => $h->id, 'mobile' => '9555599999']);
        $staff->hostels()->sync([$h->id]);

        $this->actingAs($staff)->post(route('admin.subscription.snooze'), ['before' => 7]);

        $this->assertDatabaseCount('renewal_reminder_snoozes', 0);
    }
}
