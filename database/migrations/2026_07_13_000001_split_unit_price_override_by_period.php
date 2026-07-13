<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A single unit_price_override was applied to BOTH yearly and monthly renewals,
 * so a bespoke yearly rate wrongly became the monthly rate too (P4 item 8.2).
 * Split it into per-period columns; the existing value carries over as the
 * yearly custom rate (overrides were priced at yearly scale, e.g. ₹8,000).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_accounts', function (Blueprint $table) {
            $table->decimal('unit_price_override_yearly', 10, 2)->nullable()->after('status');
            $table->decimal('unit_price_override_monthly', 10, 2)->nullable()->after('unit_price_override_yearly');
        });

        DB::table('subscription_accounts')
            ->whereNotNull('unit_price_override')
            ->update(['unit_price_override_yearly' => DB::raw('unit_price_override')]);

        Schema::table('subscription_accounts', function (Blueprint $table) {
            $table->dropColumn('unit_price_override');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_accounts', function (Blueprint $table) {
            $table->decimal('unit_price_override', 10, 2)->nullable()->after('status');
        });

        DB::table('subscription_accounts')
            ->whereNotNull('unit_price_override_yearly')
            ->update(['unit_price_override' => DB::raw('unit_price_override_yearly')]);

        Schema::table('subscription_accounts', function (Blueprint $table) {
            $table->dropColumn(['unit_price_override_yearly', 'unit_price_override_monthly']);
        });
    }
};
