<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S5 (_artifact/saas_billing_autopay/28_S5_DESIGN.md §5): WHEN an order was paid.
 *
 * Cash-by-month was being read from created_at — a link sent on 28 Sep and paid on
 * 3 Oct counted as September, a proforma accepted three weeks later counted on the day
 * it was raised. paid_at is set once, by SubscriptionOrder, the first time it is saved
 * as paid.
 *
 * Back-fill: paid (and refunded) orders get their created_at — exact for the offline
 * and instant payments that make up nearly all of them. Voided orders are left null:
 * no metric reads a voided order, so guessing whether one had been paid buys nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_orders', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('payment_status')->index();
        });

        DB::table('subscription_orders')
            ->whereIn('payment_status', ['paid', 'refunded'])
            ->whereNull('paid_at')
            ->update(['paid_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('subscription_orders', function (Blueprint $table) {
            $table->dropIndex(['paid_at']);
            $table->dropColumn('paid_at');
        });
    }
};
