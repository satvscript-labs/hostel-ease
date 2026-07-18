<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionOrderLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * W12: the Super Admin subscription-invoice PDF — the platform billing a
 * customer for their branches. Boundary (owner/staff/guest refused) is already
 * covered by SuperAdminBoundaryTest's dynamic GET sweep; here we prove the PDF
 * actually renders, is scoped to the right account, and numbers stably.
 */
class SubscriptionInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected SubscriptionAccount $account;
    protected SubscriptionOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'hostel_id' => null]);

        $owner = User::factory()->create(['role' => 'hostel_admin', 'name' => 'Ramesh Patel', 'email' => 'ramesh@example.com']);
        $branch = Hostel::factory()->create(['name' => 'Sunrise Boys', 'owner_id' => $owner->id]);
        $owner->update(['hostel_id' => $branch->id]);

        $this->account = SubscriptionAccount::create([
            'owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'auto_debit' => false,
        ]);

        $this->order = SubscriptionOrder::create([
            'account_id' => $this->account->id, 'period' => 'yearly', 'quantity' => 1,
            'subtotal' => 10000, 'discount_total' => 2000, 'amount' => 8000,
            'payment_status' => 'paid', 'payment_method' => 'online', 'transaction_number' => 'pay_TEST123',
        ]);

        SubscriptionOrderLine::create([
            'order_id' => $this->order->id, 'branch_id' => $branch->id, 'amount' => 8000,
            'start_date' => now(), 'end_date' => now()->addYear(),
        ]);
    }

    protected function url(SubscriptionAccount $a = null, SubscriptionOrder $o = null): string
    {
        return route('superadmin.accounts.orders.invoice', [$a ?? $this->account, $o ?? $this->order]);
    }

    public function test_the_super_admin_downloads_a_pdf_invoice(): void
    {
        $res = $this->actingAs($this->superAdmin)->get($this->url())
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // Real PDF bytes, and the download carries the invoice-number filename.
        $this->assertStringStartsWith('%PDF', (string) $res->getContent());
        $this->assertStringContainsString($this->order->invoiceNumber().'.pdf', (string) $res->headers->get('content-disposition'));
    }

    public function test_the_invoice_number_is_stable_and_formatted(): void
    {
        $expected = 'HE-'.$this->order->created_at->format('Y').'-'.str_pad((string) $this->order->id, 5, '0', STR_PAD_LEFT);
        $this->assertSame($expected, $this->order->invoiceNumber());
    }

    /** An order from ANOTHER account must 404 on this account's URL — the pair
     *  is bound together so one customer's invoice can't be pulled under
     *  another's account id. */
    public function test_a_mismatched_account_and_order_is_not_found(): void
    {
        $otherOwner = User::factory()->create(['role' => 'hostel_admin']);
        $otherAccount = SubscriptionAccount::create([
            'owner_id' => $otherOwner->id, 'period' => 'yearly', 'status' => 'active', 'auto_debit' => false,
        ]);

        $this->actingAs($this->superAdmin)->get($this->url($otherAccount, $this->order))->assertNotFound();
    }

    /** A pending order still renders — as a proforma, not a tax invoice. */
    public function test_a_pending_order_renders_a_proforma(): void
    {
        $this->order->update(['payment_status' => 'pending']);

        $this->actingAs($this->superAdmin)->get($this->url())
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertTrue($this->order->fresh()->isBillable());
    }
}
