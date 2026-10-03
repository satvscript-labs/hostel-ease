<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Branch removal (D11): the owner ASKS, the operator DECIDES, and a cancelled branch
 * keeps the coverage it paid for.
 *
 * One test per numbered case in _artifact/saas_billing_autopay/07_S1_DESIGN.md §6.
 * The two that matter most are the rules the whole feature rests on:
 *
 *   R1 — the billing quantity filter is `cancelled_at IS NULL` and NOTHING else.
 *        Adding `status = 'active'` looks natural and is a revenue bug: an expired
 *        or suspended account's branches are not `active`, so it would quote them
 *        for ZERO branches and their renewal would cost ₹0.
 *   R2 — entitlement never looks at cancellation, so a cancelled branch works until
 *        its coverage ends with no special case anywhere.
 */
class BranchRemovalTest extends TestCase
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

    /**
     * An owner with N branches, each covered a year out through the ledger.
     *
     * @return array{0: User,1: \Illuminate\Support\Collection<int, Hostel>, 2: SubscriptionAccount}
     */
    private function owner(int $branches = 2, string $mobile = '+919880000001'): array
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => $mobile, 'name' => 'Removal Owner']);
        $made = collect(range(1, $branches))->map(fn ($i) => Hostel::factory()->create([
            'name' => "Branch {$i}", 'mobile' => $mobile, 'owner_id' => $owner->id,
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

    // -----------------------------------------------------------------
    // Cases 1-3 — the request lifecycle
    // -----------------------------------------------------------------

    public function test_case_1_an_owner_request_changes_nothing_about_billing(): void
    {
        [$owner, $branches, $account] = $this->owner();
        $branch = $branches->first();

        $this->actingAs($owner)
            ->post(route('admin.branches.request-removal'), ['branch_id' => $branch->id, 'reason' => 'closing this property'])
            ->assertRedirect();

        $branch->refresh();
        $this->assertTrue($branch->hasRemovalRequest());
        $this->assertFalse($branch->isCancelled());
        $this->assertSame('removal_requested', $branch->cancellationState());

        // Still billed, still entitled — asking is not cancelling.
        $this->assertSame(2, $this->billing()->includedBranches($account)->count());
        $this->assertSame(20000.0, (float) $this->billing()->quoteRenewal($account, 'yearly')['subtotal']);
        $this->assertTrue($branch->isActive());

        // And the operator is told.
        $this->assertDatabaseHas('notifications', ['type' => 'branch_removal_request', 'hostel_id' => null]);
    }

    public function test_case_2_the_owner_can_withdraw_and_it_leaves_no_trace(): void
    {
        [$owner, $branches] = $this->owner();
        $branch = $branches->first();

        $this->actingAs($owner)->post(route('admin.branches.request-removal'), ['branch_id' => $branch->id, 'reason' => 'maybe'])->assertRedirect();
        $this->actingAs($owner)->delete(route('admin.branches.withdraw-removal', $branch))->assertRedirect();

        $branch->refresh();
        $this->assertSame('live', $branch->cancellationState());
        $this->assertNull($branch->cancellation_requested_reason);
        // clear() soft-deletes, so the row survives but leaves the feed. Asserting on
        // `deleted_at => null` is what actually proves it was cleared — and it is what
        // caught `where('hostel_id', null)` never matching the Super Admin feed.
        $this->assertDatabaseMissing('notifications', ['type' => 'branch_removal_request', 'deleted_at' => null]);
    }

    public function test_case_3_the_operator_can_decline_and_the_owner_is_told(): void
    {
        [$owner, $branches, $account] = $this->owner();
        $branch = $branches->first();
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($owner)->post(route('admin.branches.request-removal'), ['branch_id' => $branch->id, 'reason' => 'too expensive']);

        $this->actingAs($super)
            ->post(route('superadmin.accounts.branches.decline-removal', $account), ['branch_id' => $branch->id, 'note' => 'agreed a discount instead'])
            ->assertRedirect();

        $this->assertSame('live', $branch->fresh()->cancellationState());
        // clear() soft-deletes, so the row survives but leaves the feed. Asserting on
        // `deleted_at => null` is what actually proves it was cleared — and it is what
        // caught `where('hostel_id', null)` never matching the Super Admin feed.
        $this->assertDatabaseMissing('notifications', ['type' => 'branch_removal_request', 'deleted_at' => null]);
        // The ask does not just vanish — the owner's own feed says what happened.
        $this->assertDatabaseHas('notifications', ['type' => 'branch_removal', 'hostel_id' => $branch->id]);
    }

    // -----------------------------------------------------------------
    // Cases 4, 10 — confirming removal
    // -----------------------------------------------------------------

    public function test_case_4_confirming_drops_it_from_the_quantity_but_not_from_service(): void
    {
        [, $branches, $account] = $this->owner();
        $branch = $branches->first();
        $coveredTo = $branch->subscription_end->toDateString();

        $this->assertTrue($this->billing()->cancelBranch($branch, 'owner closing the property'));
        $branch->refresh();

        // R2: still working, still covered to the day it paid for.
        $this->assertTrue($branch->isActive(), 'A cancelled branch lost access immediately — it paid for this time.');
        $this->assertSame($coveredTo, $branch->subscription_end->toDateString());

        // R1: out of the quantity, so the next renewal is cheaper straight away.
        $this->assertSame(1, $this->billing()->includedBranches($account->fresh())->count());
        $this->assertSame(10000.0, (float) $this->billing()->quoteRenewal($account->fresh(), 'yearly')['subtotal']);
        $this->assertSame('cancelled', $branch->cancellationState());
    }

    public function test_case_10_a_renewal_skips_the_cancelled_branch_entirely(): void
    {
        [, $branches, $account] = $this->owner();
        $cancelled = $branches->first();
        $kept = $branches->last();

        $this->billing()->cancelBranch($cancelled, 'leaving');
        $cancelledEnd = $cancelled->fresh()->subscription_end->toDateString();

        $order = $this->billing()->renewAccount($account->fresh(), 'yearly', [
            'payment_status' => 'paid', 'payment_method' => 'cash',
        ]);

        $this->assertSame(1, $order->quantity);
        $this->assertSame(1, $order->lines()->count());
        $this->assertSame($kept->id, $order->lines()->first()->branch_id);

        // No new coverage for the leaver, and none taken away either.
        $this->assertSame($cancelledEnd, $cancelled->fresh()->subscription_end->toDateString());
        $this->assertTrue($kept->fresh()->subscription_end->greaterThan($cancelled->fresh()->subscription_end));
    }

    // -----------------------------------------------------------------
    // Cases 6, 7 — restoring
    // -----------------------------------------------------------------

    public function test_case_6_restoring_while_coverage_is_live_puts_it_back(): void
    {
        [, $branches, $account] = $this->owner();
        $branch = $branches->first();

        $this->billing()->cancelBranch($branch, 'changed their mind later');
        $result = $this->billing()->restoreBranch($branch->fresh());

        $this->assertTrue($result['restored']);
        $this->assertFalse($result['coverageLapsed']);
        $this->assertSame(2, $this->billing()->includedBranches($account->fresh())->count());
        $this->assertSame('live', $branch->fresh()->cancellationState());
    }

    public function test_case_7_restoring_after_coverage_lapsed_grants_nothing(): void
    {
        [, $branches] = $this->owner(1);
        $branch = $branches->first();

        $this->billing()->cancelBranch($branch, 'left');
        // Time passes and the run-out coverage expires.
        $this->travelTo(now()->addYears(2));

        $result = $this->billing()->restoreBranch($branch->fresh());

        $this->assertTrue($result['restored']);
        $this->assertTrue($result['coverageLapsed'], 'Restoring must report that coverage has lapsed, not quietly imply access.');
        $this->assertFalse($branch->fresh()->isActive(), 'Restoring granted coverage — it is not a back door to free time.');

        $this->travelBack();
    }

    // -----------------------------------------------------------------
    // Case 8 — the cancelled branch holds the furthest date
    // -----------------------------------------------------------------

    public function test_case_8_a_leaving_branch_does_not_hold_the_anchor_open(): void
    {
        [, $branches, $account] = $this->owner(2);
        $leaving = $branches->first();
        $staying = $branches->last();

        // Give the leaver extra run-out time, so it is the furthest-dated branch.
        $this->billing()->comp($account->fresh(), 'yearly', 1, [$leaving->id], 'goodwill run-out');
        $this->assertTrue($leaving->fresh()->subscription_end->greaterThan($staying->fresh()->subscription_end));

        $this->billing()->cancelBranch($leaving->fresh(), 'leaving');
        $account = $account->fresh();

        // The anchor follows the branches that are STAYING...
        $this->assertSame(
            $staying->fresh()->subscription_end->toDateString(),
            $account->current_period_end->toDateString(),
            'The account anchor is still being held open by a branch that is leaving.',
        );
        // ...and the leaver keeps every day it was given.
        $this->assertTrue($leaving->fresh()->subscription_end->greaterThan($account->current_period_end));
    }

    // -----------------------------------------------------------------
    // Case 9 — the account closes
    // -----------------------------------------------------------------

    public function test_case_9_renewing_an_account_with_nothing_billable_is_refused(): void
    {
        [, $branches, $account] = $this->owner(1);

        $this->billing()->cancelBranch($branches->first(), 'closing the business');

        $this->expectException(\RuntimeException::class);
        $this->billing()->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'paid']);
    }

    public function test_case_9_the_operator_sees_a_refusal_not_a_zero_rupee_order(): void
    {
        [, $branches, $account] = $this->owner(1);
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->post(route('superadmin.accounts.branches.cancel', $account), ['branch_id' => $branches->first()->id, 'reason' => 'closing the business'])->assertRedirect();

        $anchorBefore = $account->fresh()->current_period_end?->toDateString();
        $ordersBefore = SubscriptionOrder::count();

        $this->actingAs($super)->post(route('superadmin.accounts.renew', $account), [
            'period' => 'yearly', 'payment_method' => 'cash',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame($ordersBefore, SubscriptionOrder::count(), 'A ₹0 order was written for an account with nothing to bill.');
        $this->assertSame($anchorBefore, $account->fresh()->current_period_end?->toDateString(), 'The anchor moved on an empty renewal.');
        $this->assertDatabaseHas('notifications', ['type' => 'account_closing', 'hostel_id' => null]);
    }

    // -----------------------------------------------------------------
    // R1 — the filter that must never test `status`
    // -----------------------------------------------------------------

    public function test_rule_r1_an_expired_account_still_quotes_its_remaining_branches(): void
    {
        // THE bug this rule exists to prevent. Both branches are expired (so neither
        // is `status = active`) and one is cancelled. A status-based quantity filter
        // would return ZERO branches here and quote the renewal at ₹0.
        [, $branches, $account] = $this->owner(2);
        $branches->each(fn (Hostel $b) => $b->forceFill(['status' => 'expired', 'subscription_end' => now()->subMonth()])->save());
        $this->billing()->cancelBranch($branches->first()->fresh(), 'gone');

        $account = $account->fresh();
        $quote = $this->billing()->quoteRenewal($account, 'yearly');

        $this->assertSame(1, $quote['quantity'], 'An expired account quoted the wrong number of branches — the quantity filter is testing status.');
        $this->assertSame(10000.0, (float) $quote['subtotal']);
    }

    public function test_rule_r1_a_suspended_account_still_quotes_its_branches(): void
    {
        [, , $account] = $this->owner(3);

        $this->billing()->suspend($account->fresh(), 'payment dispute');

        $quote = $this->billing()->quoteRenewal($account->fresh(), 'yearly');
        $this->assertSame(3, $quote['quantity']);
        $this->assertSame(30000.0, (float) $quote['subtotal']);
    }

    // -----------------------------------------------------------------
    // Cases 11-19 — the rest
    // -----------------------------------------------------------------

    public function test_case_11_the_impact_preview_flags_a_lost_volume_tier(): void
    {
        [, $branches, $account] = $this->owner(3);

        // A tier that only applies at 3+ branches: dropping to 2 loses it, so each
        // REMAINING branch gets more expensive. That is what has to be warned about.
        \App\Models\DiscountRule::create(['min_quantity' => 3, 'type' => 'percentage', 'value' => 20, 'active' => true]);

        $impact = $this->billing()->removalImpact($account->fresh(), $branches->first());

        $this->assertSame(3, $impact['quantity_now']);
        $this->assertSame(2, $impact['quantity_after']);
        $this->assertSame(24000.0, $impact['total_now']);   // 30000 − 20%
        $this->assertSame(20000.0, $impact['total_after']);  // no tier at 2
        $this->assertTrue($impact['tier_lost'], 'A lost volume tier was not flagged — the remaining branches silently cost more.');
        $this->assertGreaterThan($impact['per_branch_now'], $impact['per_branch_after']);
        $this->assertFalse($impact['closes_account']);
    }

    public function test_case_11_the_impact_preview_flags_the_account_closing(): void
    {
        [, $branches, $account] = $this->owner(1);

        $impact = $this->billing()->removalImpact($account, $branches->first());

        $this->assertSame(0, $impact['quantity_after']);
        $this->assertTrue($impact['closes_account']);
        $this->assertFalse($impact['tier_lost']);   // nothing remains to get dearer
    }

    public function test_case_12_a_cancelled_branch_is_not_offered_for_alignment(): void
    {
        [, $branches, $account] = $this->owner(2);
        $behind = $branches->first();

        // Put it behind the anchor, then cancel it.
        $behind->forceFill(['subscription_end' => now()->addMonth()])->save();
        $this->billing()->cancelBranch($behind->fresh(), 'leaving');

        $align = $this->billing()->quoteAlign($account->fresh());

        $this->assertSame(0, $align['count'], 'Align offered to top up a branch that is leaving.');
    }

    public function test_case_13_a_cancelled_branch_can_still_be_comped(): void
    {
        [, $branches, $account] = $this->owner(2);
        $leaving = $branches->first();
        $this->billing()->cancelBranch($leaving, 'leaving');
        $endBefore = $leaving->fresh()->subscription_end;

        $this->billing()->comp($account->fresh(), 'monthly', 2, [$leaving->id], 'two months on us');

        $this->assertTrue($leaving->fresh()->subscription_end->greaterThan($endBefore));
        // Gifted time does not put it back in the bill.
        $this->assertSame(1, $this->billing()->includedBranches($account->fresh())->count());
    }

    public function test_case_14_suspension_and_cancellation_are_independent(): void
    {
        [, $branches, $account] = $this->owner(2);

        $this->billing()->suspend($account->fresh(), 'dispute');
        $this->billing()->cancelBranch($branches->first()->fresh(), 'leaving');

        $this->assertSame('suspended', $account->fresh()->status->value, 'Cancelling a branch cleared a manual suspension.');
        $this->assertTrue($branches->first()->fresh()->isCancelled());

        $this->billing()->restoreBranch($branches->first()->fresh());
        $this->assertSame('suspended', $account->fresh()->status->value, 'Restoring a branch lifted a manual suspension.');
    }

    public function test_case_15_cancel_and_restore_are_idempotent(): void
    {
        [, $branches] = $this->owner(2);
        $branch = $branches->first();

        $this->assertTrue($this->billing()->cancelBranch($branch, 'first'));
        $cancelledAt = $branch->fresh()->cancelled_at;

        $this->assertFalse($this->billing()->cancelBranch($branch->fresh(), 'second'), 'A second cancel was treated as new.');
        $this->assertEquals($cancelledAt, $branch->fresh()->cancelled_at, 'A second cancel moved the cancellation date.');

        $this->assertTrue($this->billing()->restoreBranch($branch->fresh())['restored']);
        $this->assertFalse($this->billing()->restoreBranch($branch->fresh())['restored']);
    }

    public function test_case_16_a_co_admin_cannot_request_removal(): void
    {
        [, $branches] = $this->owner(2);
        $branch = $branches->first();

        // A co-admin shares the hostel_admin ROLE, which is exactly why the gate has
        // to be the owner FK and not the role.
        $coAdmin = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '+919880009999']);
        $coAdmin->hostels()->sync([$branch->id]);

        $this->actingAs($coAdmin)
            ->post(route('admin.branches.request-removal'), ['branch_id' => $branch->id, 'reason' => 'not mine to ask'])
            ->assertNotFound();

        $this->assertSame('live', $branch->fresh()->cancellationState());
    }

    public function test_case_16_another_accounts_owner_cannot_request_removal(): void
    {
        [, $branches] = $this->owner(1, '+919880000011');
        [$stranger] = $this->owner(1, '+919880000022');

        $this->actingAs($stranger)
            ->post(route('admin.branches.request-removal'), ['branch_id' => $branches->first()->id, 'reason' => 'nope'])
            ->assertNotFound();
    }

    public function test_case_17_only_a_super_admin_may_cancel(): void
    {
        [$owner, $branches, $account] = $this->owner(2);

        // `role:super_admin` bounces rather than 403s (EnsureUserRole redirects back
        // with an error) — what matters is that nothing was cancelled.
        $this->actingAs($owner)
            ->post(route('superadmin.accounts.branches.cancel', $account), ['branch_id' => $branches->first()->id, 'reason' => 'me cancelling myself'])
            ->assertRedirect();

        $this->assertFalse($branches->first()->fresh()->isCancelled(), 'An owner cancelled their own branch — removal is operator-only (D11).');
    }

    public function test_case_18_requesting_twice_does_not_duplicate_anything(): void
    {
        [$owner, $branches] = $this->owner(2);
        $branch = $branches->first();

        $this->actingAs($owner)->post(route('admin.branches.request-removal'), ['branch_id' => $branch->id, 'reason' => 'first ask']);
        $requestedAt = $branch->fresh()->cancellation_requested_at;

        $this->actingAs($owner)->post(route('admin.branches.request-removal'), ['branch_id' => $branch->id, 'reason' => 'second ask'])
            ->assertRedirect()->assertSessionHas('info');

        $this->assertEquals($requestedAt, $branch->fresh()->cancellation_requested_at);
        $this->assertSame('first ask', $branch->fresh()->cancellation_requested_reason);
        $this->assertSame(1, \App\Models\Notification::where('type', 'branch_removal_request')->count());
    }

    public function test_case_19_a_pending_order_on_the_branch_is_surfaced(): void
    {
        [, $branches, $account] = $this->owner(2);
        $branch = $branches->first();

        // An unpaid charge sitting against this branch.
        $this->billing()->recordBranchRenewal($branch, 'yearly', [
            'amount' => 10000, 'payment_status' => 'pending', 'payment_method' => 'cash',
        ]);

        $pending = $this->billing()->pendingOrdersForBranch($branch->fresh());
        $this->assertCount(1, $pending);

        // A ₹0 grant is never "owed", so it must not show up as one.
        $this->billing()->comp($account->fresh(), 'yearly', 1, [$branch->id], 'gift');
        $this->assertCount(1, $this->billing()->pendingOrdersForBranch($branch->fresh()));
    }

    /**
     * Both surfaces must RENDER every removal state. Worth asserting because the
     * modals build their URLs from the branch's opaque public_id in Alpine — PHPUnit
     * does not run that JS, so a broken payload would show up here as a missing key
     * rather than as a silently dead button (the trap in `development_standards` §1.1).
     */
    public function test_both_surfaces_render_every_removal_state(): void
    {
        [$owner, $branches, $account] = $this->owner(3);
        $super = User::factory()->superAdmin()->create();

        $this->billing()->requestRemoval($branches->get(0), 'thinking about it');
        $this->billing()->cancelBranch($branches->get(1), 'confirmed');

        $this->actingAs($super)->get(route('superadmin.accounts.show', $account))
            ->assertOk()
            ->assertSee('Removal asked')
            ->assertSee('Cancelled')
            ->assertSee('Confirm removal')
            ->assertSee('Restore')
            // The impact map and the opaque-id template the modals build URLs from.
            ->assertSee('tier_lost', false)
            ->assertSee($branches->get(0)->public_id, false);

        $this->actingAs($owner)->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertSee('Removal asked')
            ->assertSee('Closing')
            ->assertSee('Request removal')
            ->assertSee('Withdraw')
            ->assertSee($branches->get(2)->public_id, false);
    }

    /**
     * THE TEST THAT WAS MISSING. The three modal actions shipped broken and every
     * other test passed: they were declared as `<x-he-modal ::action="...">`, which
     * puts `:action` in $attributes instead of filling the component's $action prop,
     * so the component rendered a <div> — no form, no CSRF, no method. The buttons
     * did nothing, silently, exactly as `development_standards.md` §1.1 rule 2 warns.
     *
     * So: assert the markup actually submits. A rendered <form> with the right action
     * and the posted integer id — none of which a "page renders" assertion can see.
     */
    public function test_the_removal_and_void_modals_are_real_forms_posting_to_the_right_routes(): void
    {
        [$owner, $branches, $account] = $this->owner(2);
        $branch = $branches->first();
        $super = User::factory()->superAdmin()->create();

        // A pending order so the Void control is rendered too.
        $this->billing()->recordBranchRenewal($branch, 'yearly', ['amount' => 10000, 'payment_status' => 'pending']);
        $this->billing()->requestRemoval($branch->fresh(), 'please remove');

        $html = $this->actingAs($super)->get(route('superadmin.accounts.show', $account))->assertOk()->getContent();

        foreach ([
            route('superadmin.accounts.branches.cancel', $account),
            route('superadmin.accounts.branches.decline-removal', $account),
            route('superadmin.accounts.orders.void', $account),
        ] as $action) {
            $this->assertMatchesRegularExpression(
                '/<form[^>]*action="'.preg_quote($action, '/').'"/',
                $html,
                "No <form> posts to {$action} — the modal cannot submit (an ::action on x-he-modal renders a div).",
            );
        }

        // The targets ride as posted integers, bound by Alpine.
        $this->assertStringContainsString('name="branch_id" :value="cancelBranchId"', $html);
        $this->assertStringContainsString('name="branch_id" :value="declineBranchId"', $html);
        $this->assertStringContainsString('name="order_id" :value="voidOrderId"', $html);
        // PATCH is spoofed by the component, not hand-written into the body.
        $this->assertStringContainsString('name="_method" value="PATCH"', $html);

        // Owner side: the same shape.
        $ownerHtml = $this->actingAs($owner)->get(route('admin.subscription.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/<form[^>]*action="'.preg_quote(route('admin.branches.request-removal'), '/').'"/',
            $ownerHtml,
            'The owner removal-request modal is not a form posting to the request route.',
        );
        $this->assertStringContainsString('name="branch_id" :value="removeBranchId"', $ownerHtml);
    }

    /** A crafted id must not reach another customer's branch or order. */
    public function test_a_posted_id_cannot_reach_another_accounts_branch_or_order(): void
    {
        [, , $account] = $this->owner(1, '+919880000031');
        [, $otherBranches, $otherAccount] = $this->owner(1, '+919880000032');
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->post(route('superadmin.accounts.branches.cancel', $account), [
            'branch_id' => $otherBranches->first()->id, 'reason' => 'wrong customer',
        ])->assertNotFound();

        $this->assertFalse($otherBranches->first()->fresh()->isCancelled());

        $foreignOrder = $otherAccount->orders()->firstOrFail();
        $this->actingAs($super)->patch(route('superadmin.accounts.orders.void', $account), [
            'order_id' => $foreignOrder->id, 'reason' => 'wrong customer',
        ])->assertNotFound();

        $this->assertSame('paid', $foreignOrder->fresh()->payment_status->value);
    }

    public function test_a_co_admin_sees_no_removal_controls(): void
    {
        [, $branches] = $this->owner(2);
        $coAdmin = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '+919880008888']);
        $coAdmin->hostels()->sync($branches->pluck('id')->all());

        $this->actingAs($coAdmin)->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertDontSee('Request removal');
    }

    public function test_case_21_every_step_is_audited(): void
    {
        [$owner, $branches, $account] = $this->owner(2);
        $branch = $branches->first();
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($owner)->post(route('admin.branches.request-removal'), ['branch_id' => $branch->id, 'reason' => 'closing']);
        $this->actingAs($super)->post(route('superadmin.accounts.branches.cancel', $account), ['branch_id' => $branch->id, 'reason' => 'confirmed with the owner']);
        $this->actingAs($super)->post(route('superadmin.accounts.branches.restore', [$account, $branch]));

        foreach (['branch.removal_requested', 'subscription.update'] as $action) {
            $this->assertDatabaseHas('activity_logs', ['action' => $action, 'subject_id' => $branch->id]);
        }
    }
}
