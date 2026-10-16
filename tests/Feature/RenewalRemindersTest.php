<?php

namespace Tests\Feature;

use App\Mail\SubscriptionReminderMail;
use App\Models\Hostel;
use App\Models\Notification;
use App\Models\RenewalReminderSnooze;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionReminderLog;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\Billing\RenewalReminders;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * S4 — reminders that land (_artifact/saas_billing_autopay/24_S4_DESIGN.md §9).
 */
class RenewalRemindersTest extends TestCase
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
        ]);
        Mail::fake();
    }

    /**
     * An owner, their branches and account, all ending on $end.
     *
     * @return array{0: User, 1: SubscriptionAccount, 2: list<Hostel>}
     */
    protected function account(int $daysLeft, array $opts = []): array
    {
        $seq = ++static::$seq;
        $mobile = '93333'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
        $owner = User::factory()->create([
            'role' => 'hostel_admin', 'mobile' => $mobile,
            'email' => ($opts['email'] ?? true) ? "owner{$seq}@example.com" : null,
            'email_verified_at' => (($opts['email'] ?? true) && ($opts['verified'] ?? true)) ? now() : null,
        ]);

        $end = now()->addDays($daysLeft)->startOfDay();
        $branches = [];
        foreach (range(1, $opts['branches'] ?? 1) as $i) {
            $branches[] = Hostel::factory()->create([
                'mobile' => $mobile, 'owner_id' => $owner->id, 'status' => 'active', 'subscription_end' => $end,
            ]);
        }
        $owner->hostels()->sync(collect($branches)->pluck('id')->all());
        $owner->forceFill(['hostel_id' => $branches[0]->id])->save();

        $period = $opts['period'] ?? 'yearly';
        $account = SubscriptionAccount::create([
            'owner_id' => $owner->id, 'period' => $period,
            'status' => $period === 'trial' ? 'trial' : 'active',
            'current_period_start' => $opts['start'] ?? null,
            'current_period_end' => $end,
        ]);

        return [$owner, $account, $branches];
    }

    protected function run_(): void
    {
        $this->artisan('hostelease:process-subscription-lifecycle')->assertSuccessful();
    }

    /** Move the whole account's renewal date (what a renewal does to the dates). */
    protected function moveAnchor(SubscriptionAccount $account, array $branches, \Carbon\Carbon $end): void
    {
        foreach ($branches as $b) {
            $b->forceFill(['subscription_end' => $end])->save();
        }
        $account->forceFill(['current_period_end' => $end, 'status' => 'active'])->save();
    }

    // ── windows: cycles, catch-up, cycle length ────────────────────────────

    public function test_a_second_cycle_is_reminded_again(): void
    {
        [, $account, $branches] = $this->account(7);
        $this->run_();
        Mail::assertSentCount(1);

        // Renewed for a year; a year on, the 7-day window comes round again.
        $next = $account->current_period_end->copy()->addYear();
        $this->moveAnchor($account, $branches, $next);
        $this->travelTo($next->copy()->subDays(7)->setTime(9, 0));
        $this->run_();

        Mail::assertSentCount(2);
        $this->assertSame(2, SubscriptionReminderLog::where('account_id', $account->id)->where('window', 'due_7')->count());
    }

    public function test_a_missed_run_is_caught_up_but_not_three_days_late(): void
    {
        [, $late] = $this->account(5);     // the 7-day run was missed twice
        [, $tooLate] = $this->account(4);  // missed three times — gone

        $this->run_();

        $this->assertDatabaseHas('subscription_reminder_logs', ['account_id' => $late->id, 'window' => 'due_7']);
        $this->assertDatabaseMissing('subscription_reminder_logs', ['account_id' => $tooLate->id]);
    }

    public function test_only_the_most_recent_window_fires_never_a_burst(): void
    {
        [, $account] = $this->account(14);

        $this->run_();

        $this->assertSame(['due_15'], SubscriptionReminderLog::where('account_id', $account->id)->pluck('window')->all());
        Mail::assertSentCount(1);
    }

    public function test_windows_follow_the_cycle_length(): void
    {
        // A 14-day trial on its first day: no "ends in 14 days".
        [, $trial] = $this->account(14, ['period' => 'trial', 'start' => now()->startOfDay()]);
        // A monthly plan the day after paying: no "renews in 30 days".
        [, $monthly] = $this->account(29, ['period' => 'monthly', 'start' => now()->subDay()->startOfDay()]);

        $this->assertSame([7, 0], app(RenewalReminders::class)->windows($trial));
        $this->assertSame([15, 7, 0], app(RenewalReminders::class)->windows($monthly));

        $this->run_();
        $this->assertDatabaseCount('subscription_reminder_logs', 0);
    }

    public function test_an_account_with_every_branch_closing_is_not_reminded(): void
    {
        [$owner, $account, $branches] = $this->account(7);
        $branches[0]->forceFill(['cancelled_at' => now()])->save();

        // The card reads the account as it stands, before any daily refresh.
        $this->assertNull(app(RenewalReminders::class)->stage($account->fresh()));
        $this->actingAs($owner)->get(route('admin.dashboard'))->assertDontSee('class="rc rc--', false);

        $this->run_();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('subscription_reminder_logs', 0);
    }

    public function test_no_expired_email_long_after_the_fact(): void
    {
        [, $recent] = $this->account(-10);
        [, $old] = $this->account(-20);

        $this->run_();

        $this->assertDatabaseHas('subscription_reminder_logs', ['account_id' => $recent->id, 'window' => 'expired']);
        $this->assertDatabaseMissing('subscription_reminder_logs', ['account_id' => $old->id]);
    }

    // ── channels ──────────────────────────────────────────────────────────

    public function test_email_goes_out_inline_and_the_log_says_so(): void
    {
        [, $account] = $this->account(7, ['verified' => false]);

        $this->run_();

        Mail::assertSent(SubscriptionReminderMail::class, fn ($m) => $m->account->id === $account->id && $m->window === 'due_7');
        Mail::assertNothingQueued();
        $log = SubscriptionReminderLog::where('account_id', $account->id)->sole();
        $this->assertSame(['email', 'sent', 1], [$log->channel, $log->status, $log->attempts]);
        $this->assertFalse($log->detail['email_verified']);
    }

    public function test_no_email_falls_back_to_in_app_and_is_recorded(): void
    {
        [, $account, $branches] = $this->account(7, ['email' => false]);

        $this->run_();

        Mail::assertNothingSent();
        $log = SubscriptionReminderLog::where('account_id', $account->id)->sole();
        $this->assertSame(['in_app', 'no_email'], [$log->channel, $log->status]);
        $this->assertSame(1, Notification::where('hostel_id', $branches[0]->id)->where('type', 'renewal_reminder')->count());
    }

    public function test_a_failed_email_is_retried_and_capped(): void
    {
        [, $account, $branches] = $this->account(7);

        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP is down'));
        $this->run_();

        $log = SubscriptionReminderLog::where('account_id', $account->id)->sole();
        $this->assertSame(['in_app', 'failed', 1], [$log->channel, $log->status, $log->attempts]);
        $this->assertSame(1, Notification::where('hostel_id', $branches[0]->id)->where('type', 'renewal_reminder')->count(), 'in-app goes out anyway');

        // The owner reads the bell entry; a retry must not bring it back.
        Notification::where('type', 'renewal_reminder')->update(['read_at' => now()]);

        // Next day, still inside the window's catch-up band: retried, and it works.
        $this->travel(1)->days();
        Mail::swap(new \Illuminate\Support\Testing\Fakes\MailFake(new \Illuminate\Mail\MailManager(app())));
        $this->run_();
        $log->refresh();
        $this->assertSame(['email', 'sent', 2], [$log->channel, $log->status, $log->attempts]);
        Mail::assertSentCount(1);
        $this->assertSame(0, Notification::where('hostel_id', $branches[0]->id)->where('type', 'renewal_reminder')->whereNull('read_at')->count(), 'the bell is not posted twice');
    }

    public function test_retries_stop_after_three_attempts(): void
    {
        [, $account] = $this->account(7);
        SubscriptionReminderLog::create([
            'account_id' => $account->id, 'cycle_end' => $account->current_period_end->toDateString(),
            'window' => 'due_7', 'channel' => 'in_app', 'status' => 'failed', 'attempts' => 3, 'detail' => [],
        ]);

        $this->run_();

        Mail::assertNothingSent();
    }

    // ── what it says ──────────────────────────────────────────────────────

    public function test_the_reminder_carries_the_operators_link_and_its_amount(): void
    {
        [, $account] = $this->account(7, ['branches' => 2]);
        $order = app(AccountBillingService::class)->renewAccount($account, 'yearly', ['payment_status' => 'pending', 'collection' => 'link']);
        $order->forceFill(['payment_link_id' => 'plink_1', 'payment_link_url' => 'https://rzp.io/i/abc', 'payment_link_status' => 'created'])->save();

        $c = app(RenewalReminders::class)->context($account->fresh());
        $this->assertSame('https://rzp.io/i/abc', $c['pay_url']);
        $this->assertSame((float) $order->amount, $c['waiting_amount']);

        $html = (new SubscriptionReminderMail($account->fresh(), 'due_7'))->render();
        $this->assertStringContainsString('https://rzp.io/i/abc', $html);
        $this->assertStringContainsString(hostelease_money($order->amount), $html);
        $this->assertStringContainsString('https://rzp.io/i/abc', app(RenewalReminders::class)->nudgeMessage($account->fresh()));
    }

    public function test_a_link_that_is_not_https_is_never_offered(): void
    {
        [, $account] = $this->account(7);
        $order = app(AccountBillingService::class)->renewAccount($account, 'yearly', ['payment_status' => 'pending', 'collection' => 'link']);
        $order->forceFill(['payment_link_id' => 'plink_2', 'payment_link_url' => 'javascript:alert(1)', 'payment_link_status' => 'created'])->save();

        $c = app(RenewalReminders::class)->context($account->fresh());
        $this->assertNull($c['link_url']);
        $this->assertSame(route('admin.subscription.index'), $c['pay_url']);
    }

    public function test_a_renewal_covered_by_free_renewals_says_nothing_to_pay(): void
    {
        [, $account, $branches] = $this->account(7);
        $branches[0]->forceFill(['free_renewals' => 1])->save();

        $html = (new SubscriptionReminderMail($account->fresh(), 'due_7'))->render();

        $this->assertStringContainsString('your next renewal is free', $html);
    }

    // ── the bell ──────────────────────────────────────────────────────────

    public function test_one_bell_entry_per_branch_feed_cleared_once_renewed(): void
    {
        [$owner, $account, $branches] = $this->account(7, ['branches' => 2]);
        $this->run_();

        foreach ($branches as $b) {
            $this->assertSame(1, Notification::where('hostel_id', $b->id)->where('type', 'renewal_reminder')->count());
        }

        // Read it; the same stage tomorrow does not bring it back.
        Notification::where('type', 'renewal_reminder')->update(['read_at' => now()]);
        $this->travel(1)->days();
        $this->run_();
        $this->assertSame(0, Notification::where('type', 'renewal_reminder')->whereNull('read_at')->count());

        // A new stage posts a new one; renewing clears what is unread.
        $this->travelTo($account->current_period_end->copy()->setTime(9, 0));
        $this->run_();
        $this->assertSame(2, Notification::where('type', 'renewal_reminder')->whereNull('read_at')->count());

        $this->moveAnchor($account, $branches, $account->current_period_end->copy()->addYear());
        $this->run_();
        $this->assertSame(0, Notification::where('type', 'renewal_reminder')->whereNull('read_at')->count());
    }

    public function test_staff_never_see_billing_reminders(): void
    {
        [$owner, , $branches] = $this->account(7);
        $warden = User::factory()->create(['role' => 'warden', 'hostel_id' => $branches[0]->id, 'mobile' => '9333399999']);
        $this->run_();

        $this->assertSame(1, Notification::forUser($owner)->where('type', 'renewal_reminder')->count());
        $this->assertSame(0, Notification::forUser($warden)->where('type', 'renewal_reminder')->count());
    }

    public function test_the_old_per_branch_alert_is_retired(): void
    {
        [, , $branches] = $this->account(7);
        Notification::create(['hostel_id' => $branches[0]->id, 'type' => 'subscription_due', 'title' => 'Subscription expiring', 'data' => ['sig' => 'sub'], 'level' => 'warning']);

        $this->artisan('hostelease:generate-notifications')->assertSuccessful();

        $this->assertSame(0, Notification::where('type', 'subscription_due')->count());
    }

    // ── the dashboard card ────────────────────────────────────────────────

    public function test_a_trial_account_sees_the_card(): void
    {
        [$owner] = $this->account(5, ['period' => 'trial', 'start' => now()->subDays(9)->startOfDay()]);

        $this->actingAs($owner)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('class="rc rc--soon"', false)->assertSee('Your trial ends in 5 days');
    }

    public function test_snooze_quiets_the_card_until_the_chosen_day(): void
    {
        [$owner, $account] = $this->account(20);

        $this->actingAs($owner)->get(route('admin.dashboard'))->assertSee('Remind me later');

        $this->actingAs($owner)->post(route('admin.subscription.snooze'), ['before' => 7])->assertSessionHas('success');
        $snooze = RenewalReminderSnooze::sole();
        $this->assertSame($account->current_period_end->copy()->subDays(7)->toDateString(), $snooze->until->toDateString());

        $this->actingAs($owner)->get(route('admin.dashboard'))->assertSee('quiet until');

        // On the chosen day the card is back, in full.
        $this->travelTo($snooze->until->copy()->setTime(9, 0));
        $this->actingAs($owner)->get(route('admin.dashboard'))->assertDontSee('quiet until')->assertSee('Your plan renews in 7 days');
    }

    public function test_snooze_is_refused_in_the_last_days_and_for_a_made_up_choice(): void
    {
        [$owner] = $this->account(3);
        $this->actingAs($owner)->post(route('admin.subscription.snooze'), ['before' => 3])->assertSessionHas('warning');
        $this->actingAs($owner)->post(route('admin.subscription.snooze'), ['before' => 1])->assertSessionHas('warning');
        $this->actingAs($owner)->get(route('admin.dashboard'))->assertDontSee('Remind me later');

        [$early] = $this->account(20);
        $this->actingAs($early)->post(route('admin.subscription.snooze'), ['before' => 19])->assertSessionHas('warning');

        // Seven days out, "7 days before" would be today: not offered, not accepted.
        [$week] = $this->account(7);
        $this->actingAs($week)->get(route('admin.dashboard'))->assertSee('3 days before')->assertDontSee('7 days before');
        $this->actingAs($week)->post(route('admin.subscription.snooze'), ['before' => 7])->assertSessionHas('warning');

        $this->assertDatabaseCount('renewal_reminder_snoozes', 0);
    }

    public function test_a_new_cycle_ignores_the_old_snooze(): void
    {
        [$owner, $account, $branches] = $this->account(20);
        $this->actingAs($owner)->post(route('admin.subscription.snooze'), ['before' => 7]);

        // Renewed early, then a year on the card is due again — unsnoozed.
        $next = $account->current_period_end->copy()->addYear();
        $this->moveAnchor($account, $branches, $next);
        $this->travelTo($next->copy()->subDays(20)->setTime(9, 0));

        $this->actingAs($owner)->get(route('admin.dashboard'))->assertSee('Remind me later')->assertDontSee('quiet until');
    }

    public function test_moving_the_renewal_date_lifts_the_snooze(): void
    {
        [$owner, $account, $branches] = $this->account(20);
        $this->actingAs($owner)->post(route('admin.subscription.snooze'), ['before' => 7]);

        // The operator extends the date by ten days: a new date, a new conversation.
        $this->moveAnchor($account, $branches, $account->current_period_end->copy()->addDays(10));

        $this->actingAs($owner)->get(route('admin.dashboard'))->assertDontSee('quiet until')->assertSee('Your plan renews in 30 days');
    }

    public function test_a_co_admin_sees_the_card_but_not_a_pay_button_and_staff_see_nothing(): void
    {
        [$owner, , $branches] = $this->account(5);
        $coAdmin = User::factory()->create(['role' => 'hostel_admin', 'hostel_id' => $branches[0]->id, 'mobile' => '9333388888']);
        $coAdmin->hostels()->sync([$branches[0]->id]);
        $warden = User::factory()->create(['role' => 'manager', 'hostel_id' => $branches[0]->id, 'mobile' => '9333377777']);
        $warden->hostels()->sync([$branches[0]->id]);

        $this->actingAs($owner)->get(route('admin.dashboard'))->assertSee('Review & renew');
        $this->actingAs($coAdmin)->get(route('admin.dashboard'))->assertSee('View subscription')->assertDontSee('Review & renew')
            ->assertSee('The account owner renews the plan.');
        $this->actingAs($warden)->get(route('admin.dashboard'))->assertDontSee('class="rc rc--', false);
    }

    public function test_the_owner_is_asked_for_an_email_and_a_co_admin_is_not(): void
    {
        [$owner, , $branches] = $this->account(200, ['email' => false]);
        $coAdmin = User::factory()->create(['role' => 'hostel_admin', 'hostel_id' => $branches[0]->id, 'mobile' => '9333366666', 'email_verified_at' => null]);
        $coAdmin->hostels()->sync([$branches[0]->id]);

        $this->actingAs($owner)->get(route('admin.dashboard'))->assertSee('Add your email so reminders and receipts reach you');
        $this->actingAs($coAdmin)->get(route('admin.dashboard'))->assertDontSee('Add your email so reminders');
    }

    // ── operator side ─────────────────────────────────────────────────────

    public function test_the_nudge_opens_whatsapp_with_the_message_and_records_it(): void
    {
        [, $account] = $this->account(5);
        $admin = User::factory()->superAdmin()->create();

        $res = $this->actingAs($admin)->post(route('superadmin.accounts.nudge', $account));

        $res->assertRedirect();
        $this->assertStringStartsWith('https://wa.me/', $res->headers->get('Location'));
        $this->assertStringContainsString(rawurlencode('renews on'), $res->headers->get('Location'));
        $this->assertNotNull($account->fresh()->last_nudged_at);

        $this->actingAs($admin)->get(route('superadmin.accounts.show', $account))->assertSee('Nudged');
    }

    public function test_only_the_operator_can_nudge_and_never_without_a_renewal_coming(): void
    {
        [$owner, $account] = $this->account(5);
        // The super-admin area turns everyone else away (its role middleware
        // redirects) — and nothing is recorded.
        $this->actingAs($owner)->post(route('superadmin.accounts.nudge', $account))->assertRedirect();
        $this->assertNull($account->fresh()->last_nudged_at);

        [, $far] = $this->account(200);
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->post(route('superadmin.accounts.nudge', $far))->assertSessionHas('warning');
        $this->assertNull($far->fresh()->last_nudged_at);
    }

    public function test_the_operator_sees_who_cannot_be_reached(): void
    {
        [, $none] = $this->account(5, ['email' => false]);
        [, $unverified] = $this->account(5, ['verified' => false]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('superadmin.accounts.index'))->assertSee('No email')->assertSee('Email not verified');
        $this->actingAs($admin)->get(route('superadmin.accounts.show', $none))->assertSee('No email');
    }
}
