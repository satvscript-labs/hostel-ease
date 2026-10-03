<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase S2 — operator-initiated collection (Razorpay Payment Links).
 *
 * A payment link is a PENDING order with a URL attached, so everything a link
 * needs lives on the order it collects for. Design + the reasoning for keeping
 * this on the order rather than in a child table: _artifact/saas_billing_autopay
 * /10_S2_DESIGN.md §4. In one line: the business rule is "one live link per
 * charge, ever" (two live links for one renewal is how a customer pays twice),
 * and one nullable column set cannot represent two live links.
 *
 * `manual_discount_id` is not link plumbing — it fixes a latent bug that S2
 * would otherwise expose. A one-shot negotiated discount used to be marked
 * Consumed at order CREATION, which on a pending link burns it before any money
 * arrives. Storing which discount was applied lets consumption move to the
 * moment the order is paid (design §5.2).
 *
 * Every column is nullable with a safe default, so there is nothing to back-fill
 * and `down()` is a clean drop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_orders', function (Blueprint $table) {
            // Razorpay's own `plink_…` id. UNIQUE so a webhook resolves to exactly
            // ONE order and a stale link id can never attach to two. Nullable-unique
            // allows many NULLs on both MySQL and SQLite — the same precedent as
            // `transaction_number` two columns over.
            $table->string('payment_link_id', 64)->nullable()->after('razorpay_order_id');
            $table->string('payment_link_url')->nullable()->after('payment_link_id');

            // The `reference_id` we sent. Attempt 1 is the order's public_id; a
            // re-issue appends `-2`, `-3`, … because Razorpay enforces uniqueness
            // on it and would refuse an identical second link.
            $table->string('payment_link_ref', 64)->nullable()->after('payment_link_url');

            // PaymentLinkStatus — Razorpay's own vocabulary, deliberately NOT the
            // order's payment_status. Indexed: the "open links" tile and the
            // operator's worklist both filter on it.
            $table->string('payment_link_status', 32)->nullable()->after('payment_link_ref');

            $table->timestamp('payment_link_expires_at')->nullable()->after('payment_link_status');

            // How many links this charge has had. Drives the next reference_id and
            // tells the operator at a glance that a customer has been chased twice.
            $table->unsignedTinyInteger('payment_link_attempts')->default(0)->after('payment_link_expires_at');

            // The manual/negotiated discount applied to this charge, so acceptOrder()
            // can consume it when the money lands rather than at quote time.
            $table->unsignedBigInteger('manual_discount_id')->nullable()->after('discount_total');

            $table->unique('payment_link_id', 'subscription_orders_payment_link_id_unique');
            $table->index('payment_link_status', 'subscription_orders_payment_link_status_index');
            $table->index('manual_discount_id', 'subscription_orders_manual_discount_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_orders', function (Blueprint $table) {
            // Index names are given explicitly in up() so dropping them here does not
            // depend on Laravel's auto-naming matching across driver versions.
            $table->dropUnique('subscription_orders_payment_link_id_unique');
            $table->dropIndex('subscription_orders_payment_link_status_index');
            $table->dropIndex('subscription_orders_manual_discount_id_index');

            $table->dropColumn([
                'payment_link_id',
                'payment_link_url',
                'payment_link_ref',
                'payment_link_status',
                'payment_link_expires_at',
                'payment_link_attempts',
                'manual_discount_id',
            ]);
        });
    }
};
