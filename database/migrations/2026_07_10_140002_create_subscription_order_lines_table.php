<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — the per-branch line of an order. Each line covers one branch for
 * one term; its end_date is the account anchor after this charge (BRD §6.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('subscription_orders')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('hostels')->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();

            $table->index(['order_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_order_lines');
    }
};
