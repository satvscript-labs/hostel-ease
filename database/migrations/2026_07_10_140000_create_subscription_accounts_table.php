<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — the account spine. One billing account per owner (a hostel_admin
 * User). Holds the single subscription clock (the anchor date) that all of the
 * owner's branches renew against (BRD §6.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('period')->default('yearly');          // BillingPeriod
            $table->date('current_period_start')->nullable();
            $table->date('current_period_end')->nullable()->index(); // the anchor
            $table->string('status')->default('trial')->index();  // AccountStatus
            $table->decimal('unit_price_override', 10, 2)->nullable(); // bespoke deal (BR-6)
            $table->boolean('auto_debit')->default(false);        // future seam (BRD §6, D2)
            $table->string('razorpay_subscription_id')->nullable(); // future seam
            $table->text('notes')->nullable();                    // relationship/deal notes
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_accounts');
    }
};
