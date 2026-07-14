<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4 item 15 — owner panel synced with the Super Admin systems: the settings
 * hub (Profile · Users & Roles · My Branches), the owner_self_serve production
 * lock, and the item-14 invariants on owner-side branch creation.
 */
class OwnerSettingsSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
    }

    protected function owner(int $branches = 2): User
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9800000001']);
        $ids = [];
        for ($i = 0; $i < $branches; $i++) {
            $ids[] = Hostel::factory()->create([
                'mobile' => '9800000001', 'owner_id' => $owner->id,
                'status' => 'active', 'subscription_end' => now()->addMonths(6),
            ])->id;
        }
        $owner->hostels()->sync($ids);
        $owner->forceFill(['hostel_id' => $ids[0]])->save();
        SubscriptionAccount::create(['owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addMonths(6)]);

        return $owner;
    }

    public function test_settings_hub_renders_all_three_tabs_with_the_lock(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Profile')
            ->assertSee('Users &amp; Roles', false)
            ->assertSee('My Branches')
            ->assertSee('Billing is managed by HostelEase support')  // lock banner
            ->assertSee('profile', false);                            // profile tab wiring
    }

    public function test_owner_can_update_their_basic_profile_but_not_the_login_mobile(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->put(route('profile.update'), [
            'name' => 'Renamed Owner', 'email' => 'owner@new.example',
            'mobile' => '+919999999999', // must be ignored — not fillable via this route
        ])->assertRedirect();

        $owner->refresh();
        $this->assertSame('Renamed Owner', $owner->name);
        $this->assertSame('owner@new.example', $owner->email);
        $this->assertSame('9800000001', $owner->mobile); // unchanged
    }

    public function test_locked_branch_creation_is_refused_and_creates_nothing(): void
    {
        $owner = $this->owner();
        $before = Hostel::count();

        $this->actingAs($owner)->post(route('admin.branches.store'), ['name' => 'Sneaky Wing'])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame($before, Hostel::count());

        // The payment endpoints are locked too, regardless of Razorpay config.
        $this->actingAs($owner)->postJson(route('admin.branches.order'), ['branch_id' => 1, 'period' => 'yearly'])->assertStatus(503);
        $this->actingAs($owner)->postJson(route('admin.subscription.renew-order'), ['period' => 'yearly'])->assertStatus(503);
        $this->actingAs($owner)->postJson(route('admin.subscription.add-branch-order'), ['name' => 'X'])->assertStatus(503);
    }

    public function test_unlocked_branch_creation_keeps_the_item_14_invariants(): void
    {
        config(['hostelease.owner_self_serve' => true]);
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('admin.branches.store'), ['name' => 'Legit Wing', 'city' => 'Surat'])
            ->assertRedirect()->assertSessionHas('success');

        $branch = Hostel::where('name', 'Legit Wing')->firstOrFail();
        $this->assertSame($owner->id, $branch->owner_id);                                   // explicit owner
        $this->assertTrue($owner->hostels()->where('hostels.id', $branch->id)->exists());   // pivot access
        $this->assertNotNull($branch->subscription_end);                                    // trial clock started
    }

    public function test_staff_created_on_another_branch_is_still_manageable(): void
    {
        // Old bug: authorizeUser required staff.hostel_id === the ACTIVE tenant,
        // so a manager created while branch B was active 403'd from branch A.
        $owner = $this->owner(2);
        [$a, $b] = $owner->hostels()->orderBy('hostels.id')->pluck('hostels.id')->all();

        $staff = User::factory()->create(['role' => 'manager', 'hostel_id' => $b, 'mobile' => '9800000002']);
        $staff->hostels()->sync([$b]);

        // Owner operating with branch A active (their primary).
        $this->actingAs($owner)->put(route('admin.users.update', $staff), [
            'name' => 'Shifted Manager', 'role' => 'manager', 'branches' => [$a, $b], 'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame('Shifted Manager', $staff->fresh()->name);
        $this->assertEqualsCanonicalizing([$a, $b], $staff->hostels()->pluck('hostels.id')->all());
    }

    public function test_staff_branch_assignment_cannot_escape_the_owners_branches(): void
    {
        $owner = $this->owner(1);
        $mine = $owner->hostels()->first()->id;
        $foreign = Hostel::factory()->create(['mobile' => '9811111111']); // someone else's

        $this->actingAs($owner)->post(route('admin.users.store'), [
            'name' => 'Scoped Staff', 'mobile' => '9800000003', 'role' => 'manager',
            'branches' => [$mine, $foreign->id],
        ])->assertRedirect();

        $staff = User::where('mobile', '+919800000003')->firstOrFail();
        $ids = $staff->hostels()->pluck('hostels.id')->all();
        $this->assertContains($mine, $ids);
        $this->assertNotContains($foreign->id, $ids); // foreign branch silently dropped
    }

    public function test_change_password_page_renders_the_premium_ui(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('profile.password'))
            ->assertOk()
            ->assertSee('pw-hero', false)
            ->assertSee('pw-meter', false);
    }

    /* ── Item 16: team list shows co-admins, hides the owner ── */

    public function test_team_list_shows_co_admins_and_staff_but_hides_the_account_owner(): void
    {
        $owner = $this->owner(1);
        $branchId = $owner->hostels()->first()->id;

        // A co-admin (super-admin-granted) + a staff member on the same branch.
        $coAdmin = User::factory()->create(['role' => 'hostel_admin', 'name' => 'Co Admin', 'hostel_id' => $branchId, 'mobile' => '9800001111']);
        $coAdmin->hostels()->sync([$branchId]);
        $staff = User::factory()->create(['role' => 'manager', 'name' => 'Staff Manager', 'hostel_id' => $branchId, 'mobile' => '9800002222']);
        $staff->hostels()->sync([$branchId]);

        $this->actingAs($owner)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Co Admin')          // co-admin now visible
            ->assertSee('Staff Manager');    // staff visible
        // (The owner's own name still shows in the Profile tab — that they're
        // excluded from the LIST is proven by the co-admin-viewer test below,
        // where the owner is a different person and must not appear at all.)
    }

    public function test_a_co_admin_viewing_the_page_does_not_see_the_owner_but_sees_peers(): void
    {
        $owner = $this->owner(1);
        $branchId = $owner->hostels()->first()->id;

        $viewer = User::factory()->create(['role' => 'hostel_admin', 'name' => 'Viewer Admin', 'hostel_id' => $branchId, 'mobile' => '9800003333']);
        $viewer->hostels()->sync([$branchId]);
        $peer = User::factory()->create(['role' => 'hostel_admin', 'name' => 'Peer Admin', 'hostel_id' => $branchId, 'mobile' => '9800004444']);
        $peer->hostels()->sync([$branchId]);

        $this->actingAs($viewer)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Peer Admin')        // sees other admins
            ->assertDontSee($owner->name);   // never the owner (the item-16 hierarchy)
    }

    public function test_owner_can_disable_and_reset_a_co_admin_but_not_edit_or_delete_them(): void
    {
        $owner = $this->owner(1);
        $branchId = $owner->hostels()->first()->id;
        $coAdmin = User::factory()->create(['role' => 'hostel_admin', 'hostel_id' => $branchId, 'mobile' => '9800005555', 'is_active' => true]);
        $coAdmin->hostels()->sync([$branchId]);

        // Disable + reset allowed.
        $this->actingAs($owner)->patch(route('admin.users.toggle', $coAdmin))->assertRedirect();
        $this->assertFalse($coAdmin->fresh()->is_active);
        $this->actingAs($owner)->patch(route('admin.users.reset', $coAdmin))->assertRedirect()->assertSessionHas('credentials');

        // Edit (demote via staff modal) + delete are refused.
        $this->actingAs($owner)->put(route('admin.users.update', $coAdmin), [
            'name' => 'Hacked', 'role' => 'manager', 'branches' => [$branchId],
        ])->assertForbidden();
        $this->actingAs($owner)->delete(route('admin.users.destroy', $coAdmin))->assertForbidden();
    }

    public function test_the_account_owner_can_never_be_toggled_or_reset_from_the_owner_panel(): void
    {
        $owner = $this->owner(1);
        // Even a co-admin cannot act on the owner.
        $branchId = $owner->hostels()->first()->id;
        $coAdmin = User::factory()->create(['role' => 'hostel_admin', 'hostel_id' => $branchId, 'mobile' => '9800006666']);
        $coAdmin->hostels()->sync([$branchId]);

        $this->actingAs($coAdmin)->patch(route('admin.users.toggle', $owner))->assertForbidden();
        $this->actingAs($coAdmin)->patch(route('admin.users.reset', $owner))->assertForbidden();
        $this->assertTrue($owner->fresh()->is_active);
    }
}
