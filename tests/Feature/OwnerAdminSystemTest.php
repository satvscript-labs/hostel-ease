<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\HostelService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4 item 14 — the admin/owner/branch-access invariants. One explicit owner per
 * branch (hostels.owner_id), the pivot as the access authority, and guards so
 * the system cannot drift back into the two-sources-of-truth bug.
 */
class OwnerAdminSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
    }

    public function test_provisioning_sets_the_explicit_owner_and_pivot_access(): void
    {
        $result = app(HostelService::class)->provision([
            'name' => 'First Branch', 'owner_name' => 'Owner One', 'mobile' => '+919111100001',
            'plan' => 'yearly', 'status' => 'active',
        ]);

        $hostel = $result['hostel']->fresh();
        $admin = $result['admin'];

        $this->assertSame($admin->id, $hostel->owner_id);
        $this->assertSame($hostel->id, $admin->fresh()->hostel_id);
        $this->assertTrue($admin->hostels()->where('hostels.id', $hostel->id)->exists());
    }

    public function test_second_branch_links_to_the_same_owner_and_shows_in_both_admin_lists(): void
    {
        $first = app(HostelService::class)->provision([
            'name' => 'Branch A', 'owner_name' => 'Owner Two', 'mobile' => '+919111100002',
            'plan' => 'yearly', 'status' => 'active',
        ]);
        $second = app(HostelService::class)->provision([
            'name' => 'Branch B', 'owner_name' => 'Owner Two', 'mobile' => '+919111100002',
            'plan' => 'yearly', 'status' => 'active',
        ]);

        $owner = $first['admin'];
        $this->assertSame($owner->id, $second['hostel']->fresh()->owner_id);

        // THE reported bug: the owner must appear in the admin list of EVERY
        // branch, not just the primary (admins() now reads the pivot).
        $this->assertTrue($first['hostel']->admins()->where('users.id', $owner->id)->exists());
        $this->assertTrue($second['hostel']->admins()->where('users.id', $owner->id)->exists());
    }

    public function test_owner_resolution_prefers_the_explicit_fk_over_a_lower_id_co_admin(): void
    {
        // Co-admin created FIRST (lower id) with pivot access — the old
        // "first admin in the pivot" resolver would have picked them as owner
        // and billed off the wrong user.
        $coAdmin = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '+919111100003']);
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '+919111100004']);
        $hostel = Hostel::factory()->create(['mobile' => '+919111100004', 'owner_id' => $owner->id]);
        $coAdmin->hostels()->sync([$hostel->id]);
        $owner->hostels()->sync([$hostel->id]);

        $resolved = app(AccountBillingService::class)->ownerForBranch($hostel);

        $this->assertSame($owner->id, $resolved->id);
    }

    public function test_owner_resolution_self_heals_legacy_rows_onto_the_fk(): void
    {
        // A pre-migration row: no owner_id, but the mobile identity matches.
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '+919111100005']);
        $hostel = Hostel::factory()->create(['mobile' => '+919111100005', 'owner_id' => null]);

        $resolved = app(AccountBillingService::class)->ownerForBranch($hostel);

        $this->assertSame($owner->id, $resolved->id);
        $this->assertSame($owner->id, $hostel->fresh()->owner_id); // persisted — system converges
    }

    public function test_the_owner_login_cannot_be_disabled(): void
    {
        $super = User::factory()->superAdmin()->create();
        $result = app(HostelService::class)->provision([
            'name' => 'Guarded Branch', 'owner_name' => 'Owner Three', 'mobile' => '+919111100006',
            'plan' => 'yearly', 'status' => 'active',
        ]);
        $owner = $result['admin'];

        $this->actingAs($super)->patch(route('superadmin.admins.toggle', $owner))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertTrue($owner->fresh()->is_active); // still enabled

        // A plain co-admin CAN still be disabled.
        $coAdmin = User::factory()->create(['role' => 'hostel_admin', 'hostel_id' => $result['hostel']->id]);
        $this->actingAs($super)->patch(route('superadmin.admins.toggle', $coAdmin))->assertRedirect();
        $this->assertFalse($coAdmin->fresh()->is_active);
    }

    public function test_branch_access_sync_can_never_strip_an_owned_branch(): void
    {
        $super = User::factory()->superAdmin()->create();
        $a = app(HostelService::class)->provision([
            'name' => 'Owned A', 'owner_name' => 'Owner Four', 'mobile' => '+919111100007',
            'plan' => 'yearly', 'status' => 'active',
        ]);
        $b = app(HostelService::class)->provision([
            'name' => 'Owned B', 'owner_name' => 'Owner Four', 'mobile' => '+919111100007',
            'plan' => 'yearly', 'status' => 'active',
        ]);
        $owner = $a['admin'];

        // Try to strip ALL access (empty set) — owned branches must survive.
        $this->actingAs($super)->put(route('superadmin.admins.branches', $owner), ['hostels' => []])
            ->assertRedirect();

        $ids = $owner->fresh()->hostels()->pluck('hostels.id')->all();
        $this->assertContains($a['hostel']->id, $ids);
        $this->assertContains($b['hostel']->id, $ids);
    }

    public function test_editing_a_hostel_mobile_moves_the_owner_login_and_sibling_branches(): void
    {
        $super = User::factory()->superAdmin()->create();
        $a = app(HostelService::class)->provision([
            'name' => 'Sib A', 'owner_name' => 'Owner Five', 'mobile' => '+919111100008',
            'plan' => 'yearly', 'status' => 'active',
        ]);
        $b = app(HostelService::class)->provision([
            'name' => 'Sib B', 'owner_name' => 'Owner Five', 'mobile' => '+919111100008',
            'plan' => 'yearly', 'status' => 'active',
        ]);

        $this->actingAs($super)->put(route('superadmin.hostels.update', $a['hostel']), [
            'name' => 'Sib A', 'owner_name' => 'Owner Five', 'mobile' => '9222200008',
            'status' => 'active',
            'subscription_start' => now()->toDateString(),
            'subscription_end' => now()->addYear()->toDateString(),
        ])->assertRedirect();

        // Login username AND every sibling branch follow — nothing orphans.
        $this->assertSame('+919222200008', $a['admin']->fresh()->mobile);
        $this->assertSame('+919222200008', $a['hostel']->fresh()->mobile);
        $this->assertSame('+919222200008', $b['hostel']->fresh()->mobile);
    }

    public function test_editing_a_hostel_mobile_to_another_logins_number_is_rejected(): void
    {
        $super = User::factory()->superAdmin()->create();
        User::factory()->create(['role' => 'hostel_admin', 'mobile' => '+919333300001']); // someone else
        $a = app(HostelService::class)->provision([
            'name' => 'Coll A', 'owner_name' => 'Owner Six', 'mobile' => '+919111100009',
            'plan' => 'yearly', 'status' => 'active',
        ]);

        $this->actingAs($super)->put(route('superadmin.hostels.update', $a['hostel']), [
            'name' => 'Coll A', 'owner_name' => 'Owner Six', 'mobile' => '9333300001',
            'status' => 'active',
            'subscription_start' => now()->toDateString(),
            'subscription_end' => now()->addYear()->toDateString(),
        ])->assertSessionHasErrors('mobile');

        $this->assertSame('+919111100009', $a['hostel']->fresh()->mobile); // unchanged
    }

    public function test_co_admin_creation_normalises_the_mobile_to_login_form(): void
    {
        $super = User::factory()->superAdmin()->create();
        $hostel = Hostel::factory()->create();

        $this->actingAs($super)->post(route('superadmin.admins.store'), [
            'hostel_id' => $hostel->id, 'name' => 'Co Admin', 'mobile' => '9444400001',
        ])->assertRedirect();

        // Stored in the same +91 form every login uses — a bare-digit co-admin
        // could never sign in (login normalises to +91...).
        $this->assertDatabaseHas('users', ['mobile' => '+919444400001', 'role' => 'hostel_admin']);
    }
}
