<?php

namespace Tests\Feature;

use App\Enums\OrderKind;
use App\Models\Hostel;
use App\Models\Subscription;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionOrderLine;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\Billing\CoverageMirror;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S1: one ledger, one writer.
 *
 * `subscription_orders` + lines are the only record of money, branch coverage is a
 * projection over them, and the legacy `subscriptions` table is never written again
 * (decision D8). These tests pin the properties that make that claim true.
 */
class LedgerSpineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
    }

    private function billing(): AccountBillingService
    {
        return app(AccountBillingService::class);
    }

    /** @return array{0: User, 1: \Illuminate\Support\Collection<int, Hostel>, 2: SubscriptionAccount} */
    private function account(int $branches = 3, string $mobile = '+919990000001'): array
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => $mobile]);
        $made = collect(range(1, $branches))->map(fn ($i) => Hostel::factory()->create([
            'name' => "Spine {$i}", 'mobile' => $mobile, 'owner_id' => $owner->id,
            'status' => 'active', 'subscription_end' => null,
        ]));
        $owner->hostels()->sync($made->pluck('id')->all());

        foreach ($made as $branch) {
            $this->billing()->recordBranchRenewal($branch, 'yearly', [
                'amount' => 10000, 'payment_status' => 'paid', 'payment_method' => 'cash',
            ]);
        }

        $account = SubscriptionAccount::where('owner_id', $owner->id)->firstOrFail();
        $this->billing()->refreshAccountAnchor($account);

        return [$owner, $made->map->fresh(), $account->fresh()];
    }

    /** Coverage equals the projection: MAX(end_date) over the branch's paid lines. */
    private function assertMirrorsMatchLedger(\Illuminate\Support\Collection $branches): void
    {
        foreach ($branches as $branch) {
            $ledger = SubscriptionOrderLine::where('branch_id', $branch->id)
                ->whereHas('order', fn ($q) => $q->where('payment_status', 'paid'))
                ->max('end_date');

            $this->assertNotNull($ledger, "{$branch->name} holds coverage no paid line supports.");
            $this->assertSame(
                \Illuminate\Support\Carbon::parse($ledger)->toDateString(),
                $branch->fresh()->subscription_end?->toDateString(),
                "{$branch->name}: the mirror and the ledger disagree.",
            );
        }
    }

    public function test_every_operation_leaves_the_mirror_equal_to_the_ledger(): void
    {
        [, $branches, $account] = $this->account(3);
        $this->assertMirrorsMatchLedger($branches);

        // Renew all.
        $this->billing()->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'paid', 'payment_method' => 'cash']);
        $this->assertMirrorsMatchLedger($branches);

        // Add a behind branch to the cycle.
        $late = Hostel::factory()->create([
            'name' => 'Spine late', 'mobile' => $branches->first()->mobile,
            'owner_id' => $account->owner_id, 'status' => 'active', 'subscription_end' => null,
        ]);
        $account->owner->hostels()->syncWithoutDetaching([$late->id]);
        $this->billing()->addBranch($account->fresh(), $late, ['payment_status' => 'paid', 'payment_method' => 'cash']);
        $this->assertMirrorsMatchLedger($branches->concat([$late]));

        // Align, comp, and a bare trial.
        $behind = $branches->first();
        $behind->forceFill(['subscription_end' => now()->addDays(5)])->save();
        $this->billing()->align($account->fresh(), ['payment_status' => 'paid', 'payment_method' => 'cash']);
        $this->billing()->comp($account->fresh(), 'monthly', 2, [$branches->last()->id], 'goodwill');
        $this->assertMirrorsMatchLedger($branches->concat([$late]));
    }

    public function test_the_sync_is_idempotent(): void
    {
        [, $branches, $account] = $this->account(2);
        $mirror = app(CoverageMirror::class);

        // The charges already synced on the way out, so every further sync must be a
        // no-op — that is what makes it safe to run after every op and on a schedule.
        $this->assertSame([], $mirror->sync($account), 'A sync moved something on an already-consistent ledger.');
        $this->assertSame([], $mirror->sync($account));
        $this->assertSame([], $mirror->drift($account), 'Drift reported on a consistent ledger.');
        $this->assertMirrorsMatchLedger($branches);
    }

    public function test_no_path_writes_the_legacy_subscriptions_table(): void
    {
        [, $branches, $account] = $this->account(2);

        $this->billing()->renewAccount($account->fresh(), 'monthly', ['payment_status' => 'paid', 'payment_method' => 'upi']);
        $this->billing()->comp($account->fresh(), 'yearly', 1, [$branches->first()->id], 'gift');
        $this->billing()->align($account->fresh(), ['payment_status' => 'paid']);
        $this->billing()->recordBranchRenewal($branches->last(), 'trial', ['payment_status' => 'paid']);

        $this->assertSame(0, Subscription::count(), 'Something still writes the retired legacy ledger (D8).');
    }

    public function test_an_operator_hand_edit_becomes_an_adjustment_order_and_survives_a_sync(): void
    {
        [, $branches, $account] = $this->account(1);
        $branch = $branches->first();
        $newEnd = now()->addYears(2)->startOfDay();

        $order = $this->billing()->adjustCoverage($branch, $newEnd, 'goodwill after the outage');

        $this->assertSame(OrderKind::Adjustment, $order->kind);
        $this->assertSame(0.0, (float) $order->amount);
        $this->assertSame($newEnd->toDateString(), $branch->fresh()->subscription_end->toDateString());

        // The point of minting an order: the projection agrees, so the next sync
        // keeps the hand-moved date instead of reverting it.
        app(CoverageMirror::class)->sync($account->fresh(), allowShorten: true);
        $this->assertSame($newEnd->toDateString(), $branch->fresh()->subscription_end->toDateString());
        $this->assertMirrorsMatchLedger($branches);
    }

    public function test_line_amounts_sum_exactly_to_the_order_total(): void
    {
        // F12: an equal rounded share left Σ lines ≠ order amount (three branches on
        // ₹20,000 gave ₹20,000.01). Largest-remainder allocation fixes it.
        [, , $account] = $this->account(3);

        $order = $this->billing()->renewAccount($account->fresh(), 'yearly', [
            'amount' => 20000, 'payment_status' => 'paid', 'payment_method' => 'cash',
        ]);

        $this->assertSame(
            (float) $order->amount,
            (float) $order->lines()->sum('amount'),
            'The order lines do not sum to the order total.',
        );
        $this->assertSame(3, $order->lines()->count());
    }

    public function test_a_charge_records_its_kind_and_collection_channel(): void
    {
        [, $branches, $account] = $this->account(1);

        $renewal = $this->billing()->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'paid', 'payment_method' => 'cash']);
        $this->assertSame(OrderKind::Renewal, $renewal->kind);
        $this->assertSame('offline', $renewal->collection->value);

        $comp = $this->billing()->comp($account->fresh(), 'yearly', 1, [$branches->first()->id], 'gift');
        $this->assertSame(OrderKind::Comp, $comp->kind);

        // An online charge is stamped by the presence of a Razorpay order id.
        $online = $this->billing()->renewAccount($account->fresh(), 'yearly', [
            'payment_status' => 'paid', 'payment_method' => 'online', 'razorpay_order_id' => 'order_x1',
        ]);
        $this->assertSame('checkout', $online->collection->value);
    }

    public function test_receivables_count_only_what_is_actually_owed(): void
    {
        [, $branches, $account] = $this->account(1);

        // Owed.
        $this->billing()->recordBranchRenewal($branches->first(), 'yearly', [
            'amount' => 10000, 'payment_status' => 'pending', 'payment_method' => 'cash',
        ]);
        // Not owed: a ₹0 gift and a ₹0 correction, even if either were left pending.
        $this->billing()->comp($account->fresh(), 'yearly', 1, [$branches->first()->id], 'gift');
        $this->billing()->adjustCoverage($branches->first()->fresh(), now()->addYears(3), 'correction');

        $this->assertSame(10000.0, (float) SubscriptionOrder::outstanding()->sum('amount'));
        $this->assertSame(1, SubscriptionOrder::outstanding()->count());
    }

    public function test_a_branch_renewed_through_the_account_appears_in_its_own_history(): void
    {
        // F4: the hostel profile read the legacy table, which consolidated renewals
        // never wrote — so a branch renewed via Account 360 showed an empty history.
        [, $branches, $account] = $this->account(2);
        $super = User::factory()->superAdmin()->create();

        $this->billing()->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'paid', 'payment_method' => 'cash']);

        $this->actingAs($super)->get(route('superadmin.hostels.show', $branches->first()))
            ->assertOk()
            ->assertDontSee('No billing history');
    }

    public function test_self_signup_grants_its_trial_through_the_ledger(): void
    {
        // S1 item 17: signup was the last path writing coverage directly, so its
        // trial existed nowhere the ledger could see (and the audit flagged it
        // forever as "entitled with no paid record").
        $this->post('/register', [
            'name' => 'Signup Owner',
            'hostel_name' => 'Signup Hostel',
            'mobile' => '9995550001',
            'password' => 'secret123',
        ])->assertRedirect(route('dashboard'));

        $hostel = Hostel::where('name', 'Signup Hostel')->firstOrFail();

        $this->assertSame(now()->addDays(14)->toDateString(), $hostel->subscription_end->toDateString());
        $this->assertTrue($hostel->isActive());

        $order = SubscriptionOrder::firstOrFail();
        $this->assertSame(OrderKind::Trial, $order->kind);
        $this->assertSame(0.0, (float) $order->amount);
        $this->assertSame($hostel->id, $order->lines()->first()->branch_id);
        $this->assertSame(0, Subscription::count());
    }

    public function test_the_owner_subscription_page_does_not_write_on_a_get(): void
    {
        // F10: index() used to call refreshAccountAnchor() on every page view, racing
        // the daily tick and making a read endpoint non-idempotent.
        [$owner, , $account] = $this->account(2);

        $before = $account->fresh()->only(['current_period_start', 'current_period_end', 'status', 'updated_at']);

        $this->actingAs($owner)->get(route('admin.subscription.index'))->assertOk();

        $this->assertEquals($before, $account->fresh()->only(['current_period_start', 'current_period_end', 'status', 'updated_at']));
    }

    public function test_the_legacy_archive_is_read_only(): void
    {
        $super = User::factory()->superAdmin()->create();

        // The index survives as a historical archive (and a test asserts it stays
        // reachable), but its mutating routes are gone with the capability moved to
        // Account 360.
        $this->actingAs($super)->get(route('superadmin.subscriptions.index'))->assertOk();

        foreach (['superadmin.subscriptions.store', 'superadmin.subscriptions.update', 'superadmin.subscriptions.accept', 'superadmin.subscriptions.destroy'] as $name) {
            $this->assertFalse(
                \Illuminate\Support\Facades\Route::has($name),
                "{$name} still exists — the legacy page can still write the retired ledger.",
            );
        }
    }
}
