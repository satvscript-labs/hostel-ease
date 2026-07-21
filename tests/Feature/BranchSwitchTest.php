<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The branch switcher is the highest-traffic Hostel URL, and it had no coverage
 * before the public-id hardening (U4) touched it.
 *
 * The point worth pinning: a hostel is NOT protected by its id being secret —
 * `Hostel` is the tenant itself, so `TenantScope` cannot scope it. The real
 * boundary is the explicit `canAccessHostel()` membership check in
 * BranchController. These tests assert that boundary holds even when the caller
 * knows a perfectly valid opaque id for someone else's branch.
 */
class BranchSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
    }

    public function test_an_owner_switches_to_their_own_branch_by_opaque_id(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin']);
        $a = Hostel::factory()->create();
        $b = Hostel::factory()->create();
        $owner->hostels()->sync([$a->id, $b->id]);
        $owner->update(['hostel_id' => $a->id]);

        $url = route('branch.switch', $b);
        $this->assertStringContainsString($b->public_id, $url);
        $this->assertStringNotContainsString('/'.$b->id.'/', $url);

        $this->actingAs($owner)->get($url)->assertRedirect(route('dashboard'));
        $this->assertSame($b->id, session('active_hostel_id'));
    }

    /** A valid opaque id for a branch you don't belong to is still refused. */
    public function test_a_foreign_branch_is_refused_even_with_a_valid_public_id(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin']);
        $mine = Hostel::factory()->create();
        $owner->hostels()->sync([$mine->id]);
        $owner->update(['hostel_id' => $mine->id]);

        $someoneElses = Hostel::factory()->create();

        // A real, resolvable ULID — membership is what stops it, not secrecy.
        $this->actingAs($owner)
            ->get(route('branch.switch', $someoneElses))
            ->assertForbidden();

        $this->assertNotSame($someoneElses->id, session('active_hostel_id'));
    }

    /** The old enumerable form is gone: a sequential integer resolves to nothing. */
    public function test_the_integer_branch_url_no_longer_resolves(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin']);
        $hostel = Hostel::factory()->create();
        $owner->hostels()->sync([$hostel->id]);
        $owner->update(['hostel_id' => $hostel->id]);

        $this->actingAs($owner)->get('/branch/'.$hostel->id.'/switch')->assertNotFound();
    }
}
