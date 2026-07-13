<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the Razorpay order id as a first-class, indexed column instead of
     * burying it in free-text `remarks`. Needed for reconciliation against the
     * Razorpay dashboard and for server-side amount verification (BR-15, BR-21).
     *
     * Phase 2 of the subscription overhaul reshapes this table into an order
     * header + per-branch lines; until then this column lives here so the
     * current per-branch paths are reconciliable.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('razorpay_order_id')->nullable()->index()->after('transaction_number');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['razorpay_order_id']);
            $table->dropColumn('razorpay_order_id');
        });
    }
};
