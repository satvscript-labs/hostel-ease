<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — the order header. One row per payment/charge on an account,
 * covering N branches (one order = one Razorpay payment = N branch lines).
 * Replaces the per-branch `subscriptions` table's payment role (BRD §6.6, D7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('subscription_accounts')->cascadeOnDelete();
            $table->string('period');                              // BillingPeriod
            $table->unsignedInteger('quantity')->default(1);       // billable branches on this order
            $table->decimal('subtotal', 12, 2)->default(0);        // quantity × unit (× override)
            $table->decimal('discount_total', 12, 2)->default(0);  // total discount applied
            $table->decimal('amount', 12, 2)->default(0);          // final charged amount
            $table->string('payment_status')->default('pending')->index(); // PaymentStatus
            $table->string('payment_method')->nullable();          // PaymentMethod
            $table->string('transaction_number')->nullable()->unique(); // razorpay_payment_id (idempotency)
            $table->string('razorpay_order_id')->nullable()->index();
            $table->text('remarks')->nullable();
            // Traceability back to the legacy per-branch row this was migrated from
            // (also the idempotency key for the back-fill). Null for natively-created orders.
            $table->unsignedBigInteger('legacy_subscription_id')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_orders');
    }
};
