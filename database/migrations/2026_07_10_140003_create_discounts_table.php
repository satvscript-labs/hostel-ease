<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — manual/negotiated discounts attached to an account (or a specific
 * branch's share). Recurrence covers the three the brief asked for:
 * one-time, next-renewal, permanent (BRD §6.7, BR-23…BR-29).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('subscription_accounts')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('hostels')->nullOnDelete(); // optional reach
            $table->string('recurrence');                 // DiscountRecurrence
            $table->string('type');                       // DiscountType
            $table->decimal('value', 12, 2);              // percent or rupees
            $table->decimal('max_amount', 12, 2)->nullable(); // cap for percentage
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('reason');                     // negotiation context (required)
            $table->string('status')->default('active')->index(); // DiscountStatus
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
