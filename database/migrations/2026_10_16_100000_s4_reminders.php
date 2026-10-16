<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S4 — reminders that land (_artifact/saas_billing_autopay/24_S4_DESIGN.md).
 *
 *  · subscription_reminder_logs gains the CYCLE it belongs to. The old unique key
 *    (account_id, window) meant a window could fire once per account EVER — after the
 *    first cycle no reminder was sent again (R1). The key becomes
 *    (account_id, cycle_end, window). Plus the per-channel result and retry count.
 *  · renewal_reminder_snoozes — "remind me later" on the dashboard card, per user and
 *    per cycle (a renewal starts fresh).
 *  · subscription_accounts.last_nudged_at — the operator's manual WhatsApp nudge, so a
 *    second operator does not nudge the same customer twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_reminder_logs', function (Blueprint $table) {
            $table->date('cycle_end')->nullable()->after('account_id');
            $table->unsignedTinyInteger('attempts')->default(1)->after('status');
            $table->json('detail')->nullable()->after('attempts');
        });

        // Rows written before S4 belong to some cycle we can no longer tell; the
        // current anchor is the safe guess (it only ever suppresses a duplicate).
        DB::table('subscription_reminder_logs')->whereNull('cycle_end')->orderBy('id')->each(function ($row) {
            $anchor = DB::table('subscription_accounts')->where('id', $row->account_id)->value('current_period_end');
            DB::table('subscription_reminder_logs')->where('id', $row->id)
                ->update(['cycle_end' => $anchor ? substr((string) $anchor, 0, 10) : '1970-01-01']);
        });

        // ADD the new unique index BEFORE dropping the old one: on MySQL the foreign
        // key on account_id needs an index that starts with account_id, and the old
        // composite unique was it. The new one starts with account_id too.
        Schema::table('subscription_reminder_logs', function (Blueprint $table) {
            $table->unique(['account_id', 'cycle_end', 'window'], 'reminder_logs_account_cycle_window_unique');
        });
        Schema::table('subscription_reminder_logs', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'window']);
        });

        Schema::create('renewal_reminder_snoozes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('subscription_accounts')->cascadeOnDelete();
            $table->date('cycle_end');
            $table->date('until');
            $table->timestamps();

            $table->unique(['user_id', 'account_id', 'cycle_end']);
        });

        Schema::table('subscription_accounts', function (Blueprint $table) {
            $table->timestamp('last_nudged_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_accounts', function (Blueprint $table) {
            $table->dropColumn('last_nudged_at');
        });

        Schema::dropIfExists('renewal_reminder_snoozes');

        Schema::table('subscription_reminder_logs', function (Blueprint $table) {
            $table->unique(['account_id', 'window']);
        });
        Schema::table('subscription_reminder_logs', function (Blueprint $table) {
            $table->dropUnique('reminder_logs_account_cycle_window_unique');
            $table->dropColumn(['cycle_end', 'attempts', 'detail']);
        });
    }
};
