<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — idempotency guard for the daily lifecycle/reminder command.
 * One row per (account, window) so a reminder — upcoming, grace, or expired —
 * is ever attempted once, however many times the daily job runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('subscription_accounts')->cascadeOnDelete();
            $table->string('window'); // e.g. due_30, due_15, due_7, due_0, grace_start, expired
            $table->string('channel')->default('email');
            $table->string('status'); // sent | skipped_no_email
            $table->timestamps();

            $table->unique(['account_id', 'window']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_reminder_logs');
    }
};
