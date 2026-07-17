<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Hostel;
use App\Models\PaymentMode;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payment modes are load-bearing for four modules (collections, expenses,
 * salaries, deposits validate against active() modes). These tests pin the
 * W6.4 guards: referenced modes deactivate instead of deleting, and the last
 * active mode can never be removed — zero active modes would brick every
 * money form in the app at once.
 */
class PaymentModeTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hostel = Hostel::factory()->create();
        $this->admin = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);
        $this->actingAs($this->admin);
    }

    protected function mode(string $name, string $code, bool $active = true): PaymentMode
    {
        return PaymentMode::create(['hostel_id' => $this->hostel->id, 'name' => $name, 'code' => $code,
            'is_active' => $active, 'requires_reference' => false,
            'sort_order' => (int) PaymentMode::max('sort_order') + 1]);
    }

    public function test_mode_can_be_added_with_a_slug_code(): void
    {
        $this->mode('Cash', 'cash');

        $this->post(route('admin.payment-modes.store'), ['name' => 'PhonePe Business'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('payment_modes', ['name' => 'PhonePe Business', 'code' => 'phonepe_business', 'is_active' => 1]);
    }

    public function test_unreferenced_mode_can_be_deleted(): void
    {
        $this->mode('Cash', 'cash');
        $unused = $this->mode('Never Used', 'never_used');

        $this->delete(route('admin.payment-modes.destroy', $unused))->assertSessionHas('success');

        $this->assertDatabaseMissing('payment_modes', ['id' => $unused->id]);
    }

    /** Owner decision: history keeps its labels — referenced modes deactivate, never delete. */
    public function test_referenced_mode_cannot_be_deleted(): void
    {
        $this->mode('Cash', 'cash');
        $upi = $this->mode('UPI', 'upi');

        Expense::create(['hostel_id' => $this->hostel->id, 'category' => 'other', 'title' => 'Paid via UPI',
            'amount' => 100, 'expense_date' => now(), 'mode' => 'upi']);

        $this->delete(route('admin.payment-modes.destroy', $upi))->assertSessionHas('error');

        $this->assertDatabaseHas('payment_modes', ['id' => $upi->id]);
    }

    /** Zero active modes = no money can be recorded anywhere. Never allowed. */
    public function test_the_last_active_mode_cannot_be_deleted_or_deactivated(): void
    {
        $cash = $this->mode('Cash', 'cash');
        $this->mode('Old Mode', 'old_mode', active: false);

        $this->delete(route('admin.payment-modes.destroy', $cash))->assertSessionHas('error');
        $this->assertDatabaseHas('payment_modes', ['id' => $cash->id]);

        $this->put(route('admin.payment-modes.update', $cash), ['name' => 'Cash', 'is_active' => 0])
            ->assertSessionHas('error');
        $this->assertTrue($cash->fresh()->is_active);
    }

    public function test_deactivating_is_fine_while_another_active_mode_exists(): void
    {
        $this->mode('Cash', 'cash');
        $upi = $this->mode('UPI', 'upi');

        $this->put(route('admin.payment-modes.update', $upi), ['name' => 'UPI', 'is_active' => 0])
            ->assertSessionHas('success');

        $this->assertFalse($upi->fresh()->is_active);
    }

    public function test_index_reports_usage_counts(): void
    {
        $cash = $this->mode('Cash', 'cash');
        Expense::create(['hostel_id' => $this->hostel->id, 'category' => 'other', 'title' => 'A',
            'amount' => 100, 'expense_date' => now(), 'mode' => 'cash']);
        Expense::create(['hostel_id' => $this->hostel->id, 'category' => 'other', 'title' => 'B',
            'amount' => 200, 'expense_date' => now(), 'mode' => 'cash']);

        $usage = $this->get(route('admin.payment-modes.index'))->assertOk()->viewData('usage');

        $this->assertSame(2, $usage[$cash->id]);
    }
}
