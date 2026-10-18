<?php

namespace Tests\Feature;

use App\Mail\AutopayMail;
use App\Models\Hostel;
use App\Models\Notification;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionMandate;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionOrderLine;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\Billing\AutopayService;
use App\Services\Billing\PaymentLinkService;
use App\Services\Billing\RenewalReminders;
use App\Support\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * S6 — UPI Autopay (_artifact/saas_billing_autopay/31_S6_DESIGN.md).
 *
 * One fake Razorpay for the whole file: it records every call and answers from
 * $this->rzp, so a test can say "the bank paid", "the bank failed", "Razorpay is
 * down" and assert exactly what we sent.
 */
class AutopayTest extends TestCase
{
    use RefreshDatabase;

    private const KEY_SECRET = 'rzp_test_secret';

    private const HOOK_SECRET = 'whsec_s6';

    /** Fake Razorpay state. */
    protected array $rzp = [];

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
        Mail::fake();

        config([
            'services.razorpay.enabled' => true,
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => self::KEY_SECRET,
            'services.razorpay.webhook_secret' => self::HOOK_SECRET,
            'hostelease.owner_self_serve' => true,
            'hostelease.autopay.enabled' => true,
        ]);

        $this->rzp = ['n' => 0, 'order_payments' => [], 'payments' => [], 'charge_fails' => false, 'down' => false];
        Http::fake(function (HttpRequest $r) {
            $path = parse_url($r->url(), PHP_URL_PATH);
            if ($this->rzp['down']) {
                return Http::response(['error' => ['description' => 'Service unavailable']], 503);
            }
            if ($r->method() === 'POST' && $path === '/v1/customers') {
                return Http::response(['id' => 'cust_T'], 200);
            }
            if ($r->method() === 'POST' && $path === '/v1/orders') {
                return Http::response(['id' => 'order_T'.(++$this->rzp['n']), 'amount' => $r['amount'], 'currency' => 'INR', 'receipt' => $r['receipt'] ?? 'r'], 200);
            }
            if ($r->method() === 'POST' && $path === '/v1/payments/create/recurring') {
                return match ($this->rzp['charge_fails']) {
                    true => Http::response(['error' => ['description' => 'Token is not active']], 400),
                    'timeout' => Http::response(['error' => ['description' => 'Gateway timeout']], 504),
                    default => Http::response(['razorpay_payment_id' => 'pay_R'.$this->rzp['n']], 200),
                };
            }
            if ($r->method() === 'GET' && preg_match('#^/v1/orders/([^/]+)/payments$#', $path, $m)) {
                return Http::response(['items' => $this->rzp['order_payments'][$m[1]] ?? []], 200);
            }
            if ($r->method() === 'GET' && preg_match('#^/v1/payments/([^/]+)$#', $path, $m)) {
                return Http::response($this->rzp['payments'][$m[1]] ?? [], isset($this->rzp['payments'][$m[1]]) ? 200 : 404);
            }
            if ($r->method() === 'PUT' && str_ends_with($path, '/cancel')) {
                return Http::response(['status' => 'cancellation_initiated'], 200);
            }

            return Http::response(['error' => ['description' => 'unexpected '.$path]], 404);
        });
    }

    // ── fixtures ────────────────────────────────────────────────────────────

    /** @return array{0:User,1:SubscriptionAccount,2:array<int,int>} */
    protected function owner(int $branches = 2, array $account = [], int $renewsIn = 3): array
    {
        $end = today()->addDays($renewsIn);
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9700000061', 'name' => 'Autopay Owner', 'email' => 'ap@example.test']);
        $ids = [];
        for ($i = 0; $i < $branches; $i++) {
            $ids[] = Hostel::factory()->create([
                'mobile' => '9700000061', 'owner_id' => $owner->id, 'status' => 'active', 'subscription_end' => $end,
            ])->id;
        }
        $owner->hostels()->sync($ids);
        $owner->forceFill(['hostel_id' => $ids[0]])->save();

        $acct = SubscriptionAccount::create(array_merge([
            'owner_id' => $owner->id, 'period' => 'monthly', 'status' => 'active',
            'current_period_start' => $end->copy()->subMonth(), 'current_period_end' => $end,
        ], $account));

        // Paid lines behind the coverage: voiding a failed debit re-derives coverage
        // from the ledger, as it does in production.
        $backing = SubscriptionOrder::create([
            'account_id' => $acct->id, 'period' => 'monthly', 'kind' => 'adjustment', 'quantity' => count($ids),
            'subtotal' => 0, 'discount_total' => 0, 'amount' => 0, 'payment_status' => 'paid', 'collection' => 'offline',
            'remarks' => 'Test fixture: coverage back-fill',
        ]);
        foreach ($ids as $id) {
            SubscriptionOrderLine::create(['order_id' => $backing->id, 'branch_id' => $id, 'amount' => 0, 'start_date' => $end->copy()->subMonth(), 'end_date' => $end]);
        }

        return [$owner, $acct, $ids];
    }

    protected function mandate(SubscriptionAccount $account, array $attrs = []): SubscriptionMandate
    {
        return SubscriptionMandate::create(array_merge([
            'account_id' => $account->id, 'provider' => 'razorpay', 'method' => 'upi', 'customer_id' => 'cust_T',
            'email' => 'ap@example.test', 'contact' => '9700000061', 'token_id' => 'token_T', 'max_amount' => 10000,
            'status' => 'active', 'authorised_at' => now(),
        ], $attrs));
    }

    protected function runJob(): string
    {
        $this->artisan('hostelease:charge-due-autopay')->assertSuccessful();

        return 'ran';
    }

    protected function inFlight(SubscriptionAccount $account): ?SubscriptionOrder
    {
        return app(AutopayService::class)->inFlight($account);
    }

    protected function webhook(array $body)
    {
        $payload = json_encode($body);

        return $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $payload, self::HOOK_SECRET), 'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    protected function orderPaid(SubscriptionOrder $order, int $paise, string $paymentId = 'pay_OK', array $notes = ['type' => 'autopay'])
    {
        return $this->webhook(['event' => 'order.paid', 'payload' => [
            'order' => ['entity' => ['id' => $order->razorpay_order_id, 'notes' => $notes]],
            'payment' => ['entity' => ['id' => $paymentId, 'amount' => $paise, 'order_id' => $order->razorpay_order_id, 'token_id' => 'token_T']],
        ]]);
    }

    protected function paymentFailed(SubscriptionOrder $order, string $paymentId = 'pay_BAD')
    {
        $this->rzp['order_payments'][$order->razorpay_order_id] = [['id' => $paymentId, 'amount' => $order->amountPaise(), 'status' => 'failed']];

        return $this->webhook(['event' => 'payment.failed', 'payload' => [
            'payment' => ['entity' => ['id' => $paymentId, 'order_id' => $order->razorpay_order_id, 'error_description' => 'Insufficient balance']],
        ]]);
    }

    protected function tokenEvent(string $event, string $tokenId = 'token_T')
    {
        return $this->webhook(['event' => $event, 'payload' => ['token' => ['entity' => ['id' => $tokenId, 'recurring_details' => ['status' => 'x', 'failure_reason' => null]]]]]);
    }

    protected function monthlyTotal(SubscriptionAccount $account): float
    {
        return (float) app(AccountBillingService::class)->quoteRenewal($account->fresh(), 'monthly')['total'];
    }

    // =================================================================
    // Switching it on — with a monthly renewal (D-S6-2)
    // =================================================================

    public function test_a_monthly_renewal_with_autopay_opens_a_mandate_checkout_and_the_payment_switches_it_on(): void
    {
        [$owner, $account] = $this->owner(2, renewsIn: 20);

        $res = $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'monthly', 'autopay' => true])
            ->assertOk()->assertJsonPath('mode', 'checkout')
            ->assertJsonPath('razorpay.recurring', '1')->assertJsonPath('razorpay.customer_id', 'cust_T');

        $order = SubscriptionOrder::where('kind', 'renewal')->sole();
        $this->assertSame('checkout', $order->collection->value);
        $this->assertSame($order->razorpay_order_id, $res->json('razorpay.order_id'));

        // The authorisation order: THIS renewal's amount, UPI only, the ₹10,000 ceiling.
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/orders')
            && $r['amount'] === $order->amountPaise() && $r['method'] === 'upi' && $r['customer_id'] === 'cust_T'
            && $r['token']['max_amount'] === 1000000 && $r['token']['frequency'] === 'as_presented');

        $mandate = SubscriptionMandate::sole();
        $this->assertSame('pending', $mandate->status);
        $this->assertSame($order->id, $mandate->setup_order_id);

        // The owner approves in their UPI app: one payment, carrying the new token.
        $this->rzp['payments']['pay_SETUP'] = ['id' => 'pay_SETUP', 'amount' => $order->amountPaise(), 'status' => 'captured', 'order_id' => $order->razorpay_order_id, 'token_id' => 'token_NEW', 'customer_id' => 'cust_T'];
        $this->actingAs($owner)->postJson(route('admin.subscription.confirm'), [
            'razorpay_order_id' => $order->razorpay_order_id, 'razorpay_payment_id' => 'pay_SETUP',
            'razorpay_signature' => hash_hmac('sha256', $order->razorpay_order_id.'|pay_SETUP', self::KEY_SECRET),
        ])->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status->value);
        $mandate->refresh();
        $this->assertSame('active', $mandate->status);
        $this->assertSame('token_NEW', $mandate->token_id);
        $this->assertNotNull($mandate->authorised_at);
    }

    public function test_the_set_up_webhook_switches_it_on_too(): void
    {
        [$owner, $account] = $this->owner(2, renewsIn: 20);
        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'monthly', 'autopay' => true])->assertOk();
        $order = SubscriptionOrder::where('kind', 'renewal')->sole();

        $this->webhook(['event' => 'order.paid', 'payload' => [
            'order' => ['entity' => ['id' => $order->razorpay_order_id, 'notes' => ['type' => 'checkout', 'he_order_id' => (string) $order->id]]],
            'payment' => ['entity' => ['id' => 'pay_W', 'amount' => $order->amountPaise(), 'order_id' => $order->razorpay_order_id, 'token_id' => 'token_W', 'customer_id' => 'cust_T']],
        ]])->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status->value);
        $this->assertSame('active', SubscriptionMandate::sole()->status);
        $this->assertSame('token_W', SubscriptionMandate::sole()->token_id);
    }

    public function test_a_set_up_payment_from_another_customer_does_not_switch_it_on(): void
    {
        [$owner] = $this->owner(2, renewsIn: 20);
        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'monthly', 'autopay' => true])->assertOk();
        $order = SubscriptionOrder::where('kind', 'renewal')->sole();

        app(AutopayService::class)->onSetupPaid($order, 'token_X', 'cust_SOMEONE_ELSE');

        $this->assertSame('pending', SubscriptionMandate::sole()->status);
        $this->assertNull(SubscriptionMandate::sole()->token_id);
    }

    public function test_autopay_is_refused_unless_every_condition_holds(): void
    {
        [$owner, $account] = $this->owner(2, renewsIn: 20);
        $ask = fn (array $extra = []) => $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), array_merge(['charge' => 'renewal', 'period' => 'monthly', 'autopay' => true], $extra));

        // Yearly — monthly plans only.
        $ask(['period' => 'yearly'])->assertStatus(422)->assertJsonPath('message', 'Autopay works on monthly plans.');

        // The switch off.
        config(['hostelease.autopay.enabled' => false]);
        $ask()->assertStatus(422)->assertJsonPath('message', 'Autopay is not available right now.');
        config(['hostelease.autopay.enabled' => true]);

        // No email for the bank's notices.
        $owner->forceFill(['email' => null])->save();
        $ask()->assertStatus(422);
        $owner->forceFill(['email' => 'ap@example.test'])->save();

        // Already on.
        $this->mandate($account);
        $ask()->assertStatus(422)->assertJsonPath('message', 'Autopay is already set up on this account.');

        $this->assertSame(0, SubscriptionOrder::where('kind', 'renewal')->count());
        Http::assertNotSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/orders'));
    }

    public function test_a_monthly_total_above_the_ceiling_cannot_be_put_on_autopay(): void
    {
        [$owner] = $this->owner(11, renewsIn: 20);   // 11 × ₹1,000 = ₹11,000 > ₹10,000
        $this->assertGreaterThan(10000, $this->monthlyTotal(SubscriptionAccount::sole()));

        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'monthly', 'autopay' => true])
            ->assertStatus(422)->assertJsonFragment(['message' => 'Your monthly total of '.hostelease_money(11000).' is above the '.hostelease_money(10000).' autopay limit.']);
        $this->assertSame(0, SubscriptionMandate::count());
    }

    public function test_a_co_admin_cannot_switch_autopay_on_or_off(): void
    {
        [$owner, $account, $ids] = $this->owner(2, renewsIn: 20);
        $co = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9700000062', 'email' => 'co@example.test', 'hostel_id' => $ids[0]]);
        $co->hostels()->sync($ids);

        $this->assertNotNull(app(AutopayService::class)->setupRefusal($account, $co, 'monthly', 2000));

        $this->mandate($account);
        $this->actingAs($co)->post(route('admin.subscription.autopay.cancel'))->assertSessionHas('error');
        $this->assertSame('active', SubscriptionMandate::sole()->status);
        Http::assertNotSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/cancel'));
    }

    public function test_an_abandoned_set_up_order_is_reopened_as_one_and_never_reused_without_autopay(): void
    {
        [$owner, $account] = $this->owner(2, renewsIn: 20);
        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'monthly', 'autopay' => true])->assertOk();
        $setup = SubscriptionOrder::where('kind', 'renewal')->sole();

        // Pay it from Payment due → it is still a mandate order, so it opens as one.
        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'order', 'order_id' => $setup->id])
            ->assertOk()->assertJsonPath('razorpay.order_id', $setup->razorpay_order_id)->assertJsonPath('razorpay.recurring', '1');

        // Renew WITHOUT autopay → that order is replaced, not reused.
        $res = $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'monthly'])->assertOk();
        $this->assertSame('voided', $setup->fresh()->payment_status->value);
        $this->assertNotSame($setup->razorpay_order_id, $res->json('razorpay.order_id'));
        $this->assertNull($res->json('razorpay.recurring'));
    }

    // =================================================================
    // The daily debit
    // =================================================================

    public function test_the_job_raises_one_debit_three_days_before_and_waits_for_the_bank(): void
    {
        [$owner, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);

        $this->runJob();

        $order = $this->inFlight($account);
        $this->assertNotNull($order);
        $this->assertSame('autopay', $order->collection->value);
        $this->assertSame(1, $order->autopay_attempt);
        $this->assertSame($account->current_period_end->toDateString(), $order->autopay_due_on->toDateString());
        $this->assertSame((int) round($this->monthlyTotal($account) * 100), $order->amountPaise());

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/payments/create/recurring')
            && $r['token'] === 'token_T' && $r['customer_id'] === 'cust_T' && $r['amount'] === $order->amountPaise() && $r['order_id'] === $order->razorpay_order_id);
        Mail::assertSent(AutopayMail::class, fn (AutopayMail $m) => $m->kind === 'debiting');

        // Again today, and tomorrow: still one debit — the bank has it.
        $this->runJob();
        $this->travel(1)->days();
        $this->runJob();
        $this->assertSame(1, SubscriptionOrder::where('collection', 'autopay')->count());
    }

    public function test_nothing_is_raised_before_the_window(): void
    {
        [, $account] = $this->owner(2, renewsIn: 5);
        $this->mandate($account);

        $this->runJob();

        $this->assertSame(0, SubscriptionOrder::where('collection', 'autopay')->count());
        Http::assertNothingSent();
    }

    public function test_the_banks_payment_renews_every_branch_for_a_month(): void
    {
        [, $account, $ids] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $this->runJob();
        $order = $this->inFlight($account);
        $before = $account->fresh()->current_period_end->copy();

        $this->orderPaid($order, $order->amountPaise())->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status->value);
        $this->assertSame('autopay', $order->collection->value);
        $this->assertSame('pay_OK', $order->transaction_number);
        $this->assertTrue($account->fresh()->current_period_end->isSameDay($before->copy()->addMonth()));
        foreach ($ids as $id) {
            $this->assertTrue(Hostel::find($id)->subscription_end->isSameDay($before->copy()->addMonth()));
        }

        // The same webhook again changes nothing.
        $this->orderPaid($order, $order->amountPaise())->assertOk();
        $this->assertSame(1, SubscriptionOrder::where('collection', 'autopay')->where('payment_status', 'paid')->count());
        $this->assertTrue($account->fresh()->current_period_end->isSameDay($before->copy()->addMonth()));
    }

    public function test_a_short_payment_is_not_applied(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $this->runJob();
        $order = $this->inFlight($account);
        $before = $account->fresh()->current_period_end->toDateString();

        $this->orderPaid($order, $order->amountPaise() - 100)->assertOk();

        $this->assertNotSame('paid', $order->fresh()->payment_status->value);
        $this->assertSame($before, $account->fresh()->current_period_end->toDateString());
    }

    public function test_a_paid_webhook_for_an_order_we_did_not_raise_is_never_applied(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $before = $account->current_period_end->toDateString();
        $fake = new SubscriptionOrder(['razorpay_order_id' => 'order_NOT_OURS']);

        $this->orderPaid($fake, 200000, 'pay_FORGED', ['type' => 'autopay', 'he_order_id' => '1'])->assertOk();

        $this->assertSame($before, $account->fresh()->current_period_end->toDateString());
        $this->assertTrue(Notification::where('type', 'payment_unapplied')->exists());

        // And an unsigned one is turned away at the door.
        $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], ['HTTP_X-Razorpay-Signature' => 'nope', 'CONTENT_TYPE' => 'application/json'], '{"event":"order.paid"}')->assertStatus(400);
    }

    public function test_a_failed_debit_is_retried_one_day_before_and_one_day_after_then_the_owner_is_asked_to_pay(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $before = $account->current_period_end->toDateString();

        // Attempt 1 (3 days before) fails.
        $this->runJob();
        $this->paymentFailed($this->inFlight($account))->assertOk();
        $this->assertNull($this->inFlight($account));
        $this->assertSame($before, $account->fresh()->current_period_end->toDateString());

        // Not again the same day, nor 2 days before.
        $this->runJob();
        $this->travel(1)->days();
        $this->runJob();
        $this->assertSame(1, SubscriptionOrder::where('collection', 'autopay')->count());

        // Attempt 2 (1 day before) fails.
        $this->travel(1)->days();
        $this->runJob();
        $this->assertSame(2, $this->inFlight($account)->autopay_attempt);
        $this->paymentFailed($this->inFlight($account), 'pay_BAD2');
        Mail::assertNotSent(AutopayMail::class, fn (AutopayMail $m) => $m->kind === 'failed');

        // Attempt 3 (1 day after — in grace) fails → told once, by email and the bell.
        $this->travel(2)->days();
        $this->runJob();
        $this->assertSame(3, $this->inFlight($account)->autopay_attempt);
        $this->paymentFailed($this->inFlight($account), 'pay_BAD3');

        Mail::assertSent(AutopayMail::class, fn (AutopayMail $m) => $m->kind === 'failed');
        $this->assertSame(1, Notification::where('type', 'autopay_failed')->count());

        // No fourth attempt, no second notice.
        $this->travel(1)->days();
        $this->runJob();
        $this->runJob();
        $this->assertSame(3, SubscriptionOrder::where('collection', 'autopay')->count());
        Mail::assertSent(AutopayMail::class, 1 + 1 + 1 + 1);   // 3 × debiting + 1 × failed
        $this->assertSame(1, Notification::where('type', 'autopay_failed')->count());

        // ...and the normal reminders take over: autopay no longer covers it.
        $this->assertNull(app(AutopayService::class)->covers($account->fresh()));
    }

    public function test_a_failure_webhook_is_double_checked_against_razorpay_before_voiding(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $this->runJob();
        $order = $this->inFlight($account);

        // Razorpay says it actually went through → settled, not voided.
        $this->rzp['order_payments'][$order->razorpay_order_id] = [['id' => 'pay_LATE', 'amount' => $order->amountPaise(), 'status' => 'captured']];
        app(AutopayService::class)->debitFailed($order, 'x');
        $this->assertSame('paid', $order->fresh()->payment_status->value);
    }

    public function test_a_failure_webhook_voids_nothing_while_razorpay_is_unreachable(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $this->runJob();
        $order = $this->inFlight($account);

        $this->rzp['down'] = true;
        app(AutopayService::class)->debitFailed($order, 'x');

        $this->assertSame('pending', $order->fresh()->payment_status->value);
        $this->assertNotNull($this->inFlight($account));
    }

    public function test_a_debit_razorpay_refuses_to_start_spends_the_attempt_and_is_voided(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $mandate = $this->mandate($account);
        $this->rzp['charge_fails'] = true;

        $this->runJob();

        $order = SubscriptionOrder::where('collection', 'autopay')->sole();
        $this->assertSame('voided', $order->payment_status->value);
        $this->assertNull($this->inFlight($account));
        $this->assertNotNull($mandate->fresh()->failure_reason);

        // Not retried the same day.
        $this->runJob();
        $this->assertSame(1, SubscriptionOrder::where('collection', 'autopay')->count());
    }

    public function test_an_unclear_answer_from_razorpay_is_never_followed_by_a_second_debit(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $this->rzp['charge_fails'] = 'timeout';

        $this->runJob();

        // Kept with the bank — its Razorpay order stored — and nothing raised beside it.
        $order = $this->inFlight($account);
        $this->assertNotNull($order);
        $this->assertNotNull($order->razorpay_order_id);
        $this->travel(2)->days();   // attempt 2's day
        $this->rzp['charge_fails'] = false;
        $this->runJob();
        $this->assertSame(1, SubscriptionOrder::where('collection', 'autopay')->count());

        // Razorpay later shows it went through → applied.
        $this->rzp['order_payments'][$order->razorpay_order_id] = [['id' => 'pay_LATE', 'amount' => $order->amountPaise(), 'status' => 'captured']];
        $this->travel(1)->days();
        $this->runJob();
        $this->assertSame('paid', $order->fresh()->payment_status->value);
    }

    public function test_an_attempt_recorded_before_a_crash_is_closed_and_blocks_everything_until_then(): void
    {
        [$owner, $account] = $this->owner(2, renewsIn: 3);
        $mandate = $this->mandate($account);
        // As if the run died between recording the attempt and calling Razorpay.
        $order = app(AccountBillingService::class)->renewAccount($account, 'monthly', ['payment_status' => 'pending', 'collection' => 'autopay', 'remarks' => 'x']);
        $order->forceFill(['autopay_due_on' => $account->current_period_end->toDateString(), 'autopay_attempt' => 1])->save();

        // In flight: the owner cannot pay it twice, and the job raises nothing beside it.
        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'monthly'])->assertStatus(422);
        $this->runJob();
        $this->assertSame(1, SubscriptionOrder::where('collection', 'autopay')->count());

        // An hour on it is closed (no Razorpay order = no payment possible) — and the
        // next run, on attempt 2's day, carries on.
        $this->travel(2)->hours();
        $this->runJob();
        $this->assertSame('voided', $order->fresh()->payment_status->value);
        $this->travel(2)->days();
        $this->runJob();
        $this->assertSame(2, $this->inFlight($account)->autopay_attempt);
    }

    public function test_when_razorpay_refuses_every_try_the_owner_is_told_once(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $this->rzp['charge_fails'] = true;

        $this->runJob();                       // try 1 (−3)
        $this->travel(2)->days();
        $this->runJob();                       // try 2 (−1)
        Mail::assertNotSent(AutopayMail::class, fn (AutopayMail $m) => $m->kind === 'failed');
        $this->travel(2)->days();
        $this->runJob();                       // try 3 (+1) — told in THIS run, not a day later

        $this->assertSame(3, SubscriptionOrder::where('collection', 'autopay')->where('payment_status', 'voided')->count());
        Mail::assertSent(AutopayMail::class, fn (AutopayMail $m) => $m->kind === 'failed');
        $this->assertSame(1, Notification::where('type', 'autopay_failed')->count());

        // ...and only once.
        $this->runJob();
        Mail::assertSent(AutopayMail::class, 1);
        $this->assertSame(1, Notification::where('type', 'autopay_failed')->count());
    }

    public function test_the_card_shows_the_next_rung_after_a_failed_try(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $this->runJob();
        $this->paymentFailed($this->inFlight($account));

        $this->assertSame($account->current_period_end->copy()->subDay()->toDateString(), app(AutopayService::class)->state($account->fresh())['next_on']->toDateString());
    }

    public function test_the_same_attempt_cannot_be_recorded_twice(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $this->runJob();

        $this->expectException(QueryException::class);
        $dup = SubscriptionOrder::create([
            'account_id' => $account->id, 'period' => 'monthly', 'kind' => 'renewal', 'quantity' => 2,
            'subtotal' => 0, 'discount_total' => 0, 'amount' => 0, 'payment_status' => 'pending', 'collection' => 'autopay',
        ]);
        $dup->forceFill(['autopay_due_on' => $account->current_period_end->toDateString(), 'autopay_attempt' => 1])->save();
    }

    public function test_a_renewal_above_the_mandate_is_never_debited_and_the_owner_is_told_once(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account, ['max_amount' => 1500]);   // the month is ₹2,000

        $this->runJob();
        $this->runJob();

        $this->assertSame(0, SubscriptionOrder::where('collection', 'autopay')->count());
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/payments/create/recurring'));
        Mail::assertSent(AutopayMail::class, 1);
        Mail::assertSent(AutopayMail::class, fn (AutopayMail $m) => $m->kind === 'over_limit');
        $this->assertSame(1, Notification::where('type', 'autopay_failed')->count());
        $this->assertNull(app(AutopayService::class)->covers($account->fresh()));
    }

    public function test_a_free_renewal_is_recorded_without_touching_the_bank(): void
    {
        [, $account, $ids] = $this->owner(2, renewsIn: 3);
        Hostel::whereIn('id', $ids)->update(['free_renewals' => 1]);
        $this->mandate($account);

        $this->runJob();

        $order = SubscriptionOrder::where('kind', 'renewal')->sole();
        $this->assertSame('paid', $order->payment_status->value);
        $this->assertSame(0.0, (float) $order->amount);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'razorpay'));
    }

    public function test_a_renewal_already_raised_by_the_operator_keeps_autopay_out(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        app(AccountBillingService::class)->renewAccount($account, 'monthly', ['payment_status' => 'pending', 'collection' => 'offline', 'remarks' => 'Proforma']);

        $this->runJob();

        $this->assertSame(0, SubscriptionOrder::where('collection', 'autopay')->count());
    }

    public function test_an_operator_bill_keeps_autopay_out_and_the_reminders_ask_for_it(): void
    {
        [, $account] = $this->owner(2, renewsIn: 7);
        $this->mandate($account);
        $this->assertNull(app(RenewalReminders::class)->dueWindow($account));

        app(AccountBillingService::class)->renewAccount($account, 'monthly', ['payment_status' => 'pending', 'collection' => 'offline', 'remarks' => 'Proforma']);

        $this->assertNull(app(AutopayService::class)->covers($account->fresh()));
        $this->assertNotNull(app(RenewalReminders::class)->dueWindow($account->fresh()));
    }

    public function test_a_checkout_the_owner_walked_away_from_does_not_stop_autopay(): void
    {
        [$owner, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'monthly'])->assertOk();
        $abandoned = SubscriptionOrder::where('collection', 'checkout')->sole();

        // Fresh: they may be paying in that window right now — leave it.
        $this->runJob();
        $this->assertNull($this->inFlight($account));
        $this->assertSame('pending', $abandoned->fresh()->payment_status->value);

        // An hour on, Razorpay shows nothing paid → it is voided and the debit raised.
        $this->travel(2)->hours();
        $this->runJob();
        $this->assertSame('voided', $abandoned->fresh()->payment_status->value);
        $this->assertNotNull($this->inFlight($account));
    }

    public function test_a_walked_away_checkout_that_was_actually_paid_is_applied_not_debited_again(): void
    {
        [$owner, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'monthly'])->assertOk();
        $order = SubscriptionOrder::where('collection', 'checkout')->sole();
        $this->rzp['order_payments'][$order->razorpay_order_id] = [['id' => 'pay_EARLIER', 'amount' => $order->amountPaise(), 'status' => 'captured']];

        $this->travel(2)->hours();
        $this->runJob();

        $this->assertSame('paid', $order->fresh()->payment_status->value);
        $this->assertSame(0, SubscriptionOrder::where('collection', 'autopay')->count());
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/payments/create/recurring'));
    }

    public function test_ineligible_accounts_are_never_debited(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $normal = ['billing_mode' => 'self_serve', 'status' => 'active', 'period' => 'monthly'];

        foreach ([['billing_mode' => 'managed'], ['status' => 'suspended'], ['period' => 'yearly']] as $i => $attrs) {
            $account->forceFill($attrs)->save();
            $this->runJob();
            $this->assertSame(0, SubscriptionOrder::where('collection', 'autopay')->count(), 'case '.$i);
            $account->forceFill($normal)->save();
        }

        // Control: back to normal, it is debited.
        $this->runJob();
        $this->assertSame(1, SubscriptionOrder::where('collection', 'autopay')->count());
    }

    public function test_the_switch_stops_new_debits_but_money_already_taken_is_still_applied(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $this->runJob();
        $order = $this->inFlight($account);

        config(['hostelease.autopay.enabled' => false]);

        // The webhook still settles it.
        $this->orderPaid($order, $order->amountPaise())->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status->value);

        // ...and next month nothing new is raised.
        $this->travelTo($account->fresh()->current_period_end->copy()->subDays(3));
        $this->runJob();
        $this->assertSame(1, SubscriptionOrder::where('collection', 'autopay')->count());
    }

    public function test_a_debit_with_no_answer_is_looked_up_after_two_days(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $this->runJob();
        $order = $this->inFlight($account);

        // Before 48 hours: nobody asks.
        $this->travel(1)->days();
        $this->runJob();
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/payments') && $r->method() === 'GET');

        // After: Razorpay says it was captured → applied, even with the switch off.
        config(['hostelease.autopay.enabled' => false]);
        $this->rzp['order_payments'][$order->razorpay_order_id] = [['id' => 'pay_RECON', 'amount' => $order->amountPaise(), 'status' => 'captured']];
        $this->travel(2)->days();
        $this->runJob();

        $this->assertSame('paid', $order->fresh()->payment_status->value);
        $this->assertSame('pay_RECON', $order->fresh()->transaction_number);
    }

    public function test_a_debit_razorpay_never_created_is_closed_after_four_days(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $this->runJob();
        $order = $this->inFlight($account);

        $this->travel(3)->days();
        $this->runJob();
        $this->assertSame('pending', $order->fresh()->payment_status->value);

        $this->travel(2)->days();
        $this->runJob();
        $this->assertSame('voided', $order->fresh()->payment_status->value);
    }

    // =================================================================
    // L1 — while the bank has it, nothing else takes money for it
    // =================================================================

    public function test_while_a_debit_is_in_flight_the_owner_cannot_pay_it_another_way(): void
    {
        [$owner, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $this->runJob();
        $order = $this->inFlight($account);

        foreach (['monthly', 'yearly'] as $period) {
            $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => $period])
                ->assertStatus(422)->assertJsonFragment(['message' => 'Your automatic payment of '.hostelease_money($order->amount).' is being processed by your bank — it usually completes within a day. Nothing more to pay.']);
        }
        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'order', 'order_id' => $order->id])->assertStatus(422);

        $this->assertSame(1, SubscriptionOrder::where('kind', 'renewal')->count());
        $this->assertSame(1, Http::recorded(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/orders'))->count());

        // The page says so, with no button to press.
        $this->actingAs($owner)->get(route('admin.subscription.index'))->assertOk()
            ->assertSee('Autopay in progress — nothing to pay')
            ->assertSee('Autopay is paying '.hostelease_money($order->amount));
    }

    public function test_while_a_debit_is_in_flight_the_operator_cannot_collect_or_void_it(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $this->runJob();
        $order = $this->inFlight($account);
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->post(route('superadmin.accounts.renew', $account), ['period' => 'monthly'])->assertSessionHas('warning');
        $this->actingAs($admin)->post(route('superadmin.accounts.align', $account), [])->assertRedirect();
        $this->actingAs($admin)->patch(route('superadmin.accounts.orders.accept', [$account, $order]), ['payment_method' => 'cash'])->assertSessionHas('warning');
        $this->actingAs($admin)->post(route('superadmin.accounts.orders.link.issue', $account), ['order_id' => $order->id])->assertSessionHas('warning');
        $this->actingAs($admin)->patch(route('superadmin.accounts.orders.void', $account), ['order_id' => $order->id, 'reason' => 'x'])->assertSessionHas('warning');
        $this->actingAs($admin)->post(route('superadmin.accounts.extend', $account), ['amount' => 5, 'unit' => 'days', 'reason' => 'x'])->assertSessionHas('warning');

        $this->assertSame('pending', $order->fresh()->payment_status->value);
        $this->assertNull($order->fresh()->payment_link_id);
        $this->assertSame(1, SubscriptionOrder::where('kind', 'renewal')->count());

        // The service refuses a link on its own too.
        $this->expectException(\RuntimeException::class);
        app(PaymentLinkService::class)->issue($order);
    }

    public function test_no_payment_link_is_ever_attached_to_an_autopay_charge(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        // Even one not (or no longer) with the bank — defence in depth beside hasOpenCheckout().
        $order = app(AccountBillingService::class)->renewAccount($account, 'monthly', ['payment_status' => 'pending', 'collection' => 'autopay', 'remarks' => 'x']);

        $this->assertFalse($order->canIssueLink());
        $this->expectExceptionMessage('This renewal is being collected by autopay.');
        app(PaymentLinkService::class)->issue($order);
    }

    public function test_an_autopay_paid_event_for_a_charge_that_is_not_an_autopay_debit_goes_to_a_human(): void
    {
        [$owner, $account] = $this->owner(2, renewsIn: 20);
        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'monthly'])->assertOk();
        $checkout = SubscriptionOrder::where('kind', 'renewal')->sole();

        $this->orderPaid($checkout, $checkout->amountPaise(), 'pay_ODD', ['type' => 'autopay'])->assertOk();

        $this->assertSame('pending', $checkout->fresh()->payment_status->value);
        $this->assertSame('checkout', $checkout->fresh()->collection->value);
        $this->assertTrue(Notification::where('type', 'payment_unapplied')->exists());
    }

    public function test_account_360_shows_the_mandate_and_the_attempt_with_no_collect_buttons(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $this->runJob();
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->get(route('superadmin.accounts.show', $account))->assertOk()
            ->assertSee('Autopay debit with the bank — attempt 1')
            ->assertSee('Try 1')
            ->assertSee('Turn off autopay')
            ->assertDontSee('Collect online')
            ->assertDontSee('Accept payment');
    }

    // =================================================================
    // Mandate state — pause, cancel (owner, operator, customer)
    // =================================================================

    public function test_a_paused_mandate_is_not_debited_until_it_is_resumed(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);

        $this->tokenEvent('token.paused')->assertOk();
        $this->assertSame('paused', SubscriptionMandate::sole()->status);
        $this->runJob();
        $this->assertSame(0, SubscriptionOrder::where('collection', 'autopay')->count());

        $this->tokenEvent('token.confirmed')->assertOk();
        $this->assertSame('active', SubscriptionMandate::sole()->status);
        $this->runJob();
        $this->assertSame(1, SubscriptionOrder::where('collection', 'autopay')->count());
    }

    public function test_the_customer_cancelling_in_their_upi_app_stops_autopay(): void
    {
        [, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);

        $this->tokenEvent('token.cancelled')->assertOk();

        $m = SubscriptionMandate::sole();
        $this->assertSame('cancelled', $m->status);
        $this->assertSame('customer', $m->cancelled_by);
        $this->runJob();
        $this->assertSame(0, SubscriptionOrder::where('collection', 'autopay')->count());

        // A token event for a token we never held is ignored.
        $this->tokenEvent('token.confirmed', 'token_STRANGER')->assertOk();
        $this->assertSame('cancelled', $m->fresh()->status);
    }

    public function test_the_owner_turns_it_off_and_the_bank_confirms(): void
    {
        [$owner, $account] = $this->owner(2, renewsIn: 20);
        $this->mandate($account);

        $this->actingAs($owner)->get(route('admin.subscription.index'))->assertOk()->assertSee('Autopay is on');
        $this->actingAs($owner)->post(route('admin.subscription.autopay.cancel'))->assertSessionHas('success');

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/customers/cust_T/tokens/token_T/cancel'));
        $this->assertSame('cancelling', SubscriptionMandate::sole()->status);

        $this->tokenEvent('token.cancelled');
        $m = SubscriptionMandate::sole();
        $this->assertSame('cancelled', $m->status);
        $this->assertSame('owner', $m->cancelled_by);
    }

    public function test_an_idle_mandate_says_so_instead_of_promising_a_debit(): void
    {
        [$owner, $account] = $this->owner(2, renewsIn: 20);
        $this->mandate($account);
        $this->actingAs($owner)->get(route('admin.subscription.index'))->assertSee('Autopay is on')->assertDontSee('Autopay is waiting');

        $account->forceFill(['period' => 'yearly'])->save();
        $this->actingAs($owner)->get(route('admin.subscription.index'))->assertSee('Autopay is waiting')->assertSee('It pays monthly renewals only');

        $account->forceFill(['period' => 'monthly', 'billing_mode' => 'managed'])->save();
        $this->actingAs($owner)->get(route('admin.subscription.index'))->assertSee('Autopay is waiting')->assertDontSee('by UPI on');
    }

    public function test_turning_it_off_works_even_when_razorpay_cannot_cancel_it(): void
    {
        [$owner, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $this->rzp['down'] = true;

        $this->actingAs($owner)->post(route('admin.subscription.autopay.cancel'))->assertSessionHas('success');

        $m = SubscriptionMandate::sole();
        $this->assertSame('cancelled', $m->status);
        $this->assertSame('owner', $m->cancelled_by);
        $this->assertTrue(Notification::where('type', 'autopay_cancel_failed')->exists());

        // ...and nothing is ever raised on it again.
        $this->rzp['down'] = false;
        $this->runJob();
        $this->assertSame(0, SubscriptionOrder::where('collection', 'autopay')->count());
    }

    public function test_a_managed_owner_can_still_turn_autopay_off(): void
    {
        [$owner, $account] = $this->owner(2, renewsIn: 20);
        $account->forceFill(['billing_mode' => 'managed'])->save();
        $this->mandate($account);

        $this->actingAs($owner)->post(route('admin.subscription.autopay.cancel'))->assertSessionHas('success');
        $this->assertSame('cancelling', SubscriptionMandate::sole()->status);
    }

    public function test_the_operator_can_turn_it_off(): void
    {
        [, $account] = $this->owner(2, renewsIn: 20);
        $this->mandate($account);
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->post(route('superadmin.accounts.autopay.cancel', $account))->assertSessionHas('success');
        $this->assertSame('cancelling', SubscriptionMandate::sole()->status);
        $this->assertSame('operator', SubscriptionMandate::sole()->cancelled_by);
    }

    public function test_turning_it_off_does_not_recall_a_debit_already_with_the_bank(): void
    {
        [$owner, $account] = $this->owner(2, renewsIn: 3);
        $this->mandate($account);
        $this->runJob();
        $order = $this->inFlight($account);

        $this->actingAs($owner)->post(route('admin.subscription.autopay.cancel'))->assertSessionHas('success');
        $this->tokenEvent('token.cancelled');

        $this->orderPaid($order, $order->amountPaise())->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status->value);
    }

    // =================================================================
    // Reminders — an autopay customer is not asked to "renew now"
    // =================================================================

    public function test_reminders_stand_down_while_autopay_covers_the_renewal(): void
    {
        [$owner, $account] = $this->owner(2, renewsIn: 7);
        $reminders = app(RenewalReminders::class);
        $this->assertNotNull($reminders->dueWindow($account));

        $this->mandate($account);
        $this->assertSame('on', app(AutopayService::class)->covers($account));
        $this->assertNull($reminders->dueWindow($account));
        $this->assertSame('on', $reminders->context($account)['autopay']);

        $this->actingAs($owner)->get(route('admin.dashboard'))->assertOk()->assertSee('will be paid by autopay');
    }

    public function test_the_owner_page_offers_autopay_only_on_a_monthly_paid_renewal(): void
    {
        [$owner] = $this->owner(2, renewsIn: 20);

        $this->actingAs($owner)->get(route('admin.subscription.index'))->assertOk()
            ->assertSee('Renew automatically every month')
            ->assertSee('autopayOffer: true', false);

        config(['hostelease.autopay.enabled' => false]);
        $this->actingAs($owner)->get(route('admin.subscription.index'))->assertOk()->assertSee('autopayOffer: false', false);
    }
}
