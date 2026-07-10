<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureActiveSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_sub_user_is_locked_out_when_branch_subscription_has_expired(): void
    {
        $hostel = Hostel::factory()->expired()->create();
        $manager = User::factory()->create(['hostel_id' => $hostel->id, 'role' => 'manager']);

        $this->actingAs($manager)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('subscription.expired'));
    }

    public function test_staff_sub_user_can_access_the_dashboard_on_an_active_branch(): void
    {
        $hostel = Hostel::factory()->create(); // active by default
        $manager = User::factory()->create(['hostel_id' => $hostel->id, 'role' => 'manager']);

        $this->actingAs($manager)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }
}
