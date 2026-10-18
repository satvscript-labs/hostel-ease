<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S6 — autopay (_artifact/saas_billing_autopay/31_S6_DESIGN.md §5).
 *
 *  · subscription_mandates — one UPI Autopay mandate per account (Razorpay holds the
 *    mandate; we hold its ids and state). Status is driven by Razorpay's token.* webhooks.
 *  · subscription_orders.autopay_due_on / autopay_attempt — which renewal date and which
 *    attempt an autopay debit is for. Unique together with the account, so the daily job
 *    can never raise the same attempt twice (L2). NULL for every non-autopay order (and
 *    NULLs never collide in a unique index, on SQLite or MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_mandates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('subscription_accounts')->cascadeOnDelete();
            $table->string('provider', 20)->default('razorpay');
            $table->string('method', 20)->default('upi');
            $table->string('customer_id', 40)->nullable();     // cust_…
            // Razorpay needs an email and a phone on every recurring debit: the ones the
            // owner set the mandate up with, so a later profile edit cannot break debits.
            $table->string('email', 150)->nullable();
            $table->string('contact', 20)->nullable();
            $table->string('token_id', 40)->nullable()->unique(); // token_… — once known
            $table->decimal('max_amount', 12, 2);
            // pending | active | paused | cancelling | cancelled | rejected
            $table->string('status', 20)->default('pending')->index();
            // Our renewal order whose payment authorises the mandate (D-S6-2).
            $table->foreignId('setup_order_id')->nullable()->constrained('subscription_orders')->nullOnDelete();
            $table->timestamp('authorised_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by', 20)->nullable();   // owner | customer | operator | system
            $table->string('failure_reason')->nullable();
            // The last renewal date for which the owner was told autopay could not
            // take the money — so that notice goes out once, not every day.
            $table->date('failure_notified_for')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('subscription_orders', function (Blueprint $table) {
            $table->date('autopay_due_on')->nullable()->after('collection');
            $table->unsignedTinyInteger('autopay_attempt')->nullable()->after('autopay_due_on');
            $table->unique(['account_id', 'autopay_due_on', 'autopay_attempt'], 'orders_autopay_attempt_unique');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_orders', function (Blueprint $table) {
            $table->dropUnique('orders_autopay_attempt_unique');
            $table->dropColumn(['autopay_due_on', 'autopay_attempt']);
        });
        Schema::dropIfExists('subscription_mandates');
    }
};
