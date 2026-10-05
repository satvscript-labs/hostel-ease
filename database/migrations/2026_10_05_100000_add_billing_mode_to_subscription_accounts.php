<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-account billing management (App\Enums\BillingMode).
 *
 * Every existing and future account starts as `self_serve` — the owner's decision
 * (2026-10-05). That changes nothing today: the platform-wide switch
 * (HOSTELEASE_OWNER_SELF_SERVE) is still off, and it gates everyone. When it is
 * switched on, the operator marks the customers HostelEase should keep managing.
 *
 * Nothing to back-fill: the default IS the value for every row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_accounts', function (Blueprint $table) {
            // Indexed: the Customers list filters on it.
            $table->string('billing_mode', 20)->default('self_serve')->after('status')->index();
            // When the operator last changed it — shown on Account 360. WHO and WHY
            // are in the activity log, which is where the rest of the account's history is.
            $table->timestamp('billing_mode_changed_at')->nullable()->after('billing_mode');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_accounts', function (Blueprint $table) {
            $table->dropIndex(['billing_mode']);
            $table->dropColumn(['billing_mode', 'billing_mode_changed_at']);
        });
    }
};
