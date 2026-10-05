<?php

namespace Tests\Feature;

use App\Enums\BillingMode;
use App\Mail\SubscriptionReminderMail;
use App\Models\ActivityLog;
use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Per-account billing management (BillingMode): the operator decides, customer by
 * customer, whether the owner self-serves or HostelEase manages their billing.
 *
 * The rule: the owner may START a charge only when the platform switch is on AND their
 * account is self-serve. Everything else — paying a link we sent, asking to remove a
 * branch, a payment already in flight — works in both modes. Design:
 * _artifact/saas_billing_autopay/20_BILLING_MODE.md.
 */
class BillingModeTest extends TestCase
{
    use RefreshDatabase;

    private const KEY_SECRET = 'rzp_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
        config([
            'services.razorpay.enabled' => true,
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => self::KEY_SECRET,
            'services.razorpay.webhook_secret' => 'whsec_bm',
            'hostelease.owner_self_serve' => true,
        ]);
    }

    /** @return array{0:User,1:SubscriptionAccount,2:array<int,int>} */
    private function owner(int $branches = 2, array $account = []): array
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9700000777', 'email' => 'bm@example.test']);
        $ids = [];
        for ($i = 0; $i < $branches; $i++) {
            $ids[] = Hostel::factory()->create([
                'mobile' => '9700000777', 'owner_id' => $owner->id, 'status' => 'active',
                'subscription_end' => now()->addMonths(2),
            ])->id;
        }
        $owner->hostels()->sync($ids);
        $owner->forceFill(['hostel_id' => $ids[0]])->save();

        $acct = SubscriptionAccount::create(array_merge([
            'owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active',
            'current_period_end' => now()->addMonths(2),
        ], $account));

        return [$owner, $acct, $ids];
    }

    private function manage(SubscriptionAccount $account): void
    {
        $account->forceFill(['billing_mode' => BillingMode::Managed])->save();
    }

    private function superAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    // ── the default and the rule ─────────────────────────────────────────

    public function test_every_account_starts_self_serve(): void
    {
        [, $account] = $this->owner();

        $this->assertSame(BillingMode::SelfServe, $account->billing_mode, 'in memory, before any refresh');
        $this->assertSame(BillingMode::SelfServe, $account->fresh()->billing_mode, 'and in the database');
    }

    public function test_a_fresh_sign_up_is_self_serve(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin']);
        $account = app(AccountBillingService::class)->accountFor($owner);

        $this->assertSame(BillingMode::SelfServe, $account->fresh()->billing_mode);
    }

    public function test_billing_mode_cannot_be_mass_assigned(): void
    {
        [, $account] = $this->owner();

        // Outside production the app refuses loudly (Model::preventSilentlyDiscardingAttributes);
        // in production the attribute is dropped. Either way the mode does not move.
        try {
            $account->update(['billing_mode' => 'managed']);
        } catch (MassAssignmentException) {
            // refused — as intended
        }

        $this->assertSame(BillingMode::SelfServe, $account->fresh()->billing_mode, 'only the operator action may change it');
    }

    public function test_the_owner_self_serves_only_when_the_platform_switch_is_on_and_the_account_is_self_serve(): void
    {
        [, $account] = $this->owner();

        $this->assertTrue($account->selfServeEnabled());

        $this->manage($account);
        $this->assertFalse($account->selfServeEnabled(), 'managed');

        $account->forceFill(['billing_mode' => BillingMode::SelfServe])->save();
        config(['hostelease.owner_self_serve' => false]);
        $this->assertFalse($account->selfServeEnabled(), 'the platform switch beats the account');
    }

    // ── what a managed owner sees and can do ─────────────────────────────

    public function test_a_managed_owner_sees_managed_by_hostelease_and_no_buttons(): void
    {
        [$owner, $account] = $this->owner();
        $this->manage($account);

        $this->actingAs($owner)->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertSee('Managed by HostelEase')
            ->assertSee('Billing is managed by HostelEase support')
            ->assertDontSee('Add a branch')
            ->assertDontSee('Renew all now')
            ->assertDontSee('checkout.razorpay.com', false);
    }

    public function test_a_self_serve_owner_gets_the_buttons(): void
    {
        [$owner] = $this->owner();

        $this->actingAs($owner)->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertSee('Renew all now')
            ->assertSee('Add a branch')
            ->assertDontSee('Billing is managed by HostelEase support');
    }

    public function test_a_managed_owner_cannot_start_a_renewal_or_add_a_branch(): void
    {
        [$owner, $account] = $this->owner();
        $this->manage($account);
        Http::fake();   // nothing may reach Razorpay

        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'yearly'])
            ->assertStatus(403)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'handled by the HostelEase team'));

        $this->actingAs($owner)->postJson(route('admin.subscription.add-branch'), ['name' => 'New Wing'])
            ->assertStatus(403);

        $this->assertSame(0, SubscriptionOrder::count());
        $this->assertNull(Hostel::where('name', 'New Wing')->first());
        Http::assertNothingSent();
    }

    public function test_a_managed_owner_can_still_pay_a_link_we_sent(): void
    {
        [$owner, $account] = $this->owner();
        $order = app(AccountBillingService::class)->renewAccount($account, 'yearly', ['payment_status' => 'pending', 'collection' => 'link']);
        $order->update(['payment_link_id' => 'plink_BM', 'payment_link_url' => 'https://rzp.io/i/managed', 'payment_link_status' => 'created', 'payment_link_attempts' => 1]);
        $this->manage($account);

        $this->actingAs($owner)->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertSee('https://rzp.io/i/managed', false);
    }

    public function test_a_managed_owner_can_still_ask_to_remove_a_branch(): void
    {
        [$owner, $account, $ids] = $this->owner();
        $this->manage($account);

        $this->actingAs($owner)->post(route('admin.branches.request-removal'), ['branch_id' => $ids[1], 'reason' => 'closing it'])
            ->assertRedirect();

        $this->assertNotNull(Hostel::find($ids[1])->cancellation_requested_at);
    }

    /** Switched to managed while the owner is in the Razorpay window: the money still lands. */
    public function test_a_payment_already_in_flight_still_settles_after_switching_to_managed(): void
    {
        [$owner, $account] = $this->owner();
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_BM', 'amount' => 1, 'currency' => 'INR', 'receipt' => 'r'], 200)]);
        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'yearly'])->assertOk();

        $this->manage($account);   // the operator flips it mid-payment

        Http::fake(['api.razorpay.com/v1/payments/pay_BM' => Http::response([
            'id' => 'pay_BM', 'amount' => 2000000, 'currency' => 'INR', 'status' => 'captured', 'order_id' => 'order_BM',
        ], 200)]);
        $this->actingAs($owner)->postJson(route('admin.subscription.confirm'), [
            'razorpay_order_id' => 'order_BM', 'razorpay_payment_id' => 'pay_BM',
            'razorpay_signature' => hash_hmac('sha256', 'order_BM|pay_BM', self::KEY_SECRET),
        ])->assertOk()->assertJsonPath('state', 'applied');

        $this->assertSame('paid', SubscriptionOrder::sole()->payment_status->value);
    }

    // ── the operator's control ───────────────────────────────────────────

    public function test_the_operator_switches_a_customer_to_managed_and_back(): void
    {
        [, $account] = $this->owner();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('superadmin.accounts.billing-mode', $account), ['mode' => 'managed', 'reason' => 'Owner asked us to handle renewals'])
            ->assertRedirect()->assertSessionHas('success');

        $account->refresh();
        $this->assertSame(BillingMode::Managed, $account->billing_mode);
        $this->assertNotNull($account->billing_mode_changed_at);

        $log = ActivityLog::where('subject_type', SubscriptionAccount::class)->where('subject_id', $account->id)->latest('id')->first();
        $this->assertStringContainsString('Self-serve to Managed by HostelEase', $log->description);
        $this->assertStringContainsString('Owner asked us to handle renewals', $log->description);
        $this->assertSame(['from' => 'self_serve', 'to' => 'managed'], $log->properties['billing_mode']);

        $this->actingAs($admin)->post(route('superadmin.accounts.billing-mode', $account), ['mode' => 'self_serve'])
            ->assertSessionHas('success');
        $this->assertSame(BillingMode::SelfServe, $account->fresh()->billing_mode);
    }

    public function test_saving_the_same_mode_changes_nothing(): void
    {
        [, $account] = $this->owner();

        $this->actingAs($this->superAdmin())->post(route('superadmin.accounts.billing-mode', $account), ['mode' => 'self_serve'])
            ->assertSessionHas('warning');

        $this->assertNull($account->fresh()->billing_mode_changed_at);
        $this->assertSame(0, ActivityLog::where('subject_type', SubscriptionAccount::class)->count());
    }

    public function test_only_a_known_mode_is_accepted(): void
    {
        [, $account] = $this->owner();

        $this->actingAs($this->superAdmin())->post(route('superadmin.accounts.billing-mode', $account), ['mode' => 'owner_decides'])
            ->assertSessionHasErrors('mode');

        $this->assertSame(BillingMode::SelfServe, $account->fresh()->billing_mode);
    }

    public function test_an_owner_cannot_change_their_own_billing_mode(): void
    {
        [$owner, $account] = $this->owner();
        $this->manage($account);

        $this->actingAs($owner)->post(route('superadmin.accounts.billing-mode', $account), ['mode' => 'self_serve']);

        $this->assertSame(BillingMode::Managed, $account->fresh()->billing_mode);
    }

    public function test_account_360_says_who_handles_billing_and_when_self_serve_is_off_for_everyone(): void
    {
        [, $account] = $this->owner();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('superadmin.accounts.show', $account))
            ->assertOk()
            ->assertSee('Who handles billing')
            ->assertSee('Self-serve')
            ->assertDontSee('off for everyone');

        config(['hostelease.owner_self_serve' => false]);
        $this->actingAs($admin)->get(route('superadmin.accounts.show', $account))
            ->assertSee('off for everyone')
            ->assertSee('switched off for every customer');

        $this->manage($account);
        $this->actingAs($admin)->get(route('superadmin.accounts.show', $account))
            ->assertSee('Managed by HostelEase');
    }

    public function test_switching_to_managed_warns_about_charges_the_owner_left_open(): void
    {
        [$owner, $account] = $this->owner();
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_OPEN', 'amount' => 1, 'currency' => 'INR', 'receipt' => 'r'], 200)]);
        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'yearly'])->assertOk();

        $this->actingAs($this->superAdmin())->get(route('superadmin.accounts.show', $account))
            ->assertSee('The owner started 1 charge that is still unpaid.');
    }

    public function test_the_customers_list_marks_and_filters_managed_accounts(): void
    {
        [, $selfServe] = $this->owner();
        $other = User::factory()->create(['role' => 'hostel_admin', 'name' => 'Managed Mehta']);
        $managed = SubscriptionAccount::create(['owner_id' => $other->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addMonth()]);
        $this->manage($managed);
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('superadmin.accounts.index'))
            ->assertOk()->assertSee('Managed Mehta')->assertSee('sac-managed', false);

        $this->actingAs($admin)->get(route('superadmin.accounts.index', ['billing' => 'managed']))
            ->assertSee('Managed Mehta')
            ->assertDontSee(route('superadmin.accounts.show', $selfServe), false);

        $this->actingAs($admin)->get(route('superadmin.accounts.index', ['billing' => 'self_serve']))
            ->assertDontSee('Managed Mehta');
    }

    // ── words that follow the mode ───────────────────────────────────────

    public function test_the_reminder_email_sends_a_self_serve_owner_to_pay_and_tells_a_managed_one_we_will_handle_it(): void
    {
        [, $account] = $this->owner();

        $html = (new SubscriptionReminderMail($account, 'upcoming', 5))->render();
        $this->assertStringContainsString(route('admin.subscription.index'), $html);
        $this->assertStringContainsString('Renew now', $html);

        $this->manage($account);
        $html = (new SubscriptionReminderMail($account->fresh(), 'upcoming', 5))->render();
        $this->assertStringContainsString('Our team will be in touch to renew it for you', $html);
        $this->assertStringNotContainsString('Renew now', $html);

        // Platform switch off: everyone is effectively managed.
        $account->forceFill(['billing_mode' => BillingMode::SelfServe])->save();
        config(['hostelease.owner_self_serve' => false]);
        $html = (new SubscriptionReminderMail($account->fresh(), 'expired', -2))->render();
        $this->assertStringContainsString('Please contact us at', $html);
    }

    public function test_the_dashboard_notice_follows_the_mode(): void
    {
        [$owner, $account] = $this->owner(1, ['current_period_end' => now()->addDays(5)]);

        $this->actingAs($owner)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('Renew soon to keep every branch active.');

        $this->manage($account);
        $this->actingAs($owner)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('Our team will be in touch to renew it for you.');
    }
}
