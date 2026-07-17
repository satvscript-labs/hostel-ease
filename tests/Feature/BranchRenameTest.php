<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * W9: branch rename from Settings. The guard matters more than the feature —
 * this is owner-only (owner_id), NOT role-based: a co-admin hostel_admin on
 * the same branch must be refused, or renaming becomes a side door into
 * account management.
 */
class BranchRenameTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_account_owner_can_rename_their_branch(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin']);
        $hostel = Hostel::factory()->create(['owner_id' => $owner->id, 'name' => 'Old Name']);
        $owner->update(['hostel_id' => $hostel->id]);
        Tenant::set($hostel->id);

        $this->actingAs($owner)->patch(route('admin.branches.rename', $hostel), [
            'name' => 'Sunrise Boys — Renamed', 'city' => 'Ahmedabad',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('Sunrise Boys — Renamed', $hostel->fresh()->name);
    }

    public function test_a_co_admin_on_the_branch_cannot_rename_it(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin']);
        $hostel = Hostel::factory()->create(['owner_id' => $owner->id, 'name' => 'Keep Me']);
        $coAdmin = User::factory()->create(['role' => 'hostel_admin', 'hostel_id' => $hostel->id]);
        Tenant::set($hostel->id);

        $this->actingAs($coAdmin)->patch(route('admin.branches.rename', $hostel), [
            'name' => 'Hijacked',
        ])->assertNotFound();

        $this->assertSame('Keep Me', $hostel->fresh()->name);
    }

    public function test_another_accounts_owner_cannot_rename_it(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin']);
        $hostel = Hostel::factory()->create(['owner_id' => $owner->id, 'name' => 'Keep Me']);

        $otherOwner = User::factory()->create(['role' => 'hostel_admin']);
        $otherHostel = Hostel::factory()->create(['owner_id' => $otherOwner->id]);
        $otherOwner->update(['hostel_id' => $otherHostel->id]);
        Tenant::set($otherHostel->id);

        $this->actingAs($otherOwner)->patch(route('admin.branches.rename', $hostel), [
            'name' => 'Hijacked',
        ])->assertNotFound();

        $this->assertSame('Keep Me', $hostel->fresh()->name);
    }
}
