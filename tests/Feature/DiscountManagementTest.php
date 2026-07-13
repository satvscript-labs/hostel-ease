<?php

namespace Tests\Feature;

use App\Models\DiscountRule;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
    }

    public function test_discounts_page_renders(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->get(route('superadmin.discounts.index'))
            ->assertOk()
            ->assertSee('Volume tiers');
    }

    public function test_super_admin_can_manage_volume_tiers(): void
    {
        $super = User::factory()->superAdmin()->create();

        // Create
        $this->actingAs($super)->post(route('superadmin.discounts.rules.store'), [
            'min_quantity' => 5, 'type' => 'percentage', 'value' => 10, 'max_amount' => 3000,
        ])->assertRedirect();
        $this->assertDatabaseHas('discount_rules', ['min_quantity' => 5, 'type' => 'percentage', 'active' => true]);

        $rule = DiscountRule::firstOrFail();

        // Update
        $this->actingAs($super)->put(route('superadmin.discounts.rules.update', $rule), [
            'min_quantity' => 5, 'type' => 'percentage', 'value' => 15,
        ])->assertRedirect();
        $this->assertSame('15.00', (string) $rule->fresh()->value);

        // Toggle off
        $this->actingAs($super)->patch(route('superadmin.discounts.rules.toggle', $rule))->assertRedirect();
        $this->assertFalse($rule->fresh()->active);

        // Delete
        $this->actingAs($super)->delete(route('superadmin.discounts.rules.destroy', $rule))->assertRedirect();
        $this->assertDatabaseMissing('discount_rules', ['id' => $rule->id]);
    }
}
