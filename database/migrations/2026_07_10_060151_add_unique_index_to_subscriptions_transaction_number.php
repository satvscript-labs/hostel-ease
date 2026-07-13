<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Makes the payment-id idempotency check in BranchManagerController::verify()
     * and WebhookController::razorpay() DB-enforced, not just check-then-act —
     * two concurrent deliveries for the same Razorpay payment can no longer both
     * insert a Subscription row. NULL values (offline/pending records with no
     * transaction number) are unaffected — SQLite and MySQL both allow multiple
     * NULLs under a unique index.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unique('transaction_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['transaction_number']);
        });
    }
};
