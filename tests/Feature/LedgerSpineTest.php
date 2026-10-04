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
        // A trial, through the only path one can now take: a brand-new account's
        // first branch (one free trial per account — owner decision, 2026-10-04).
        $newOwner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9990000777']);
        $firstBranch = Hostel::factory()->create(['mobile' => '9990000777', 'owner_id' => $newOwner->id]);
        $newOwner->hostels()->sync([$firstBranch->id]);
        $this->billing()->recordBranchRenewal($firstBranch, 'trial', ['payment_status' => 'paid']);

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

    public function test_receivables_do_not_hide_an_order_whose_kind_was_never_set(): void
    {
        // `kind NOT IN (…)` is UNKNOWN when kind is NULL, so a bare whereNotIn DROPS
        // such a row — money owed, invisible in the worklist. Found verifying S1.
        [, $branches, ] = $this->account(1);
        $order = $this->billing()->recordBranchRenewal($branches->first(), 'yearly', [
            'amount' => 7777, 'payment_status' => 'pending',
        ]);
        \Illuminate\Support\Facades\DB::table('subscription_orders')->where('id', $order->id)->update(['kind' => null]);

        $this->assertSame(1, SubscriptionOrder::outstanding()->count(), 'A pending order with no kind vanished from receivables.');
        $this->assertSame(7777.0, (float) SubscriptionOrder::outstanding()->sum('amount'));
    }

    public function test_an_account_left_with_no_coverage_does_not_still_report_active(): void
    {
        // Void the only paid order (or cancel the only branch) and the anchor goes
        // null. computeStatus() used to return "whatever it already was", so the
        // Customers list showed a green Active badge for a customer with no coverage
        // at all. The gate was right; the display was lying.
        [, $branches, $account] = $this->account(1);
        $this->assertSame('active', $account->status->value);

        $this->billing()->voidOrder(SubscriptionOrder::paid()->firstOrFail(), 'recorded in error');

        $account = $account->fresh();
        $this->assertNull($account->current_period_end);
        $this->assertSame('expired', $account->status->value, 'An account with no coverage still reports Active.');
        $this->assertFalse($branches->first()->fresh()->isActive());
    }

    public function test_a_fresh_trial_with_no_anchor_is_still_a_trial(): void
    {
        // The other half of that fix: "no anchor" must keep meaning Trial for an
        // account that never had coverage, or signup would read Expired.
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '+919990000099']);
        $account = $this->billing()->accountFor($owner);

        $this->billing()->refreshAccountAnchor($account);

        $this->assertNull($account->fresh()->current_period_end);
        $this->assertSame('trial', $account->fresh()->status->value);
    }

    public function test_account_360_query_count_does_not_grow_with_branch_count(): void
    {
        // NFR-4. The removal impact was computed per branch (plus a pending-orders
        // query each), which cost ~6 queries per branch: 40 at two branches, 96 at
        // ten. Removing ANY live branch has the same effect on the bill, so it is
        // computed once and the pending counts come from one grouped query.
        $counts = [];

        foreach ([2, 10] as $n) {
            [, , $account] = $this->account($n, '+9199900001'.$n);
            $super = User::factory()->superAdmin()->create();

            \Illuminate\Support\Facades\DB::flushQueryLog();
            \Illuminate\Support\Facades\DB::enableQueryLog();
            $this->actingAs($super)->get(route('superadmin.accounts.show', $account->fresh()))->assertOk();
            $counts[$n] = count(\Illuminate\Support\Facades\DB::getQueryLog());
            \Illuminate\Support\Facades\DB::disableQueryLog();
        }

        $this->assertLessThanOrEqual(
            $counts[2] + 2,
            $counts[10],
            "Account 360 ran {$counts[2]} queries for 2 branches and {$counts[10]} for 10 — something is per-branch again.",
        );
    }

    public function test_refreshing_an_anchor_does_not_re_query_the_estate_three_times(): void
    {
        // It did: allBranches() ran three times (here and twice inside drift) plus
        // supportedEnds() twice, for every account on every daily tick.
        [, , $account] = $this->account(3);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->billing()->refreshAccountAnchor($account->fresh());
        $count = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertLessThanOrEqual(6, $count, "refreshAccountAnchor ran {$count} queries for one account — the estate is being re-fetched.");
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
