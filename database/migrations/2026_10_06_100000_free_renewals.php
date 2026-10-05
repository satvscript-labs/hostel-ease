<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One renewal date per account (doc 22): a gift no longer adds dates to a single
 * branch — which moved the whole account's renewal date — it makes that branch's next
 * renewal(s) free.
 *
 *  · hostels.free_renewals — how many upcoming renewals of this branch cost ₹0.
 *  · subscription_order_lines.complimentary — this line used one of them. Explicit,
 *    not inferred from "amount = 0": an operator can renew for ₹0 by override, and
 *    that must not eat anyone's gifts.
 *
 * Nothing to back-fill. Existing gifted time past an account's renewal date is folded
 * into free renewals by `hostelease:audit-coverage --fix`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hostels', function (Blueprint $table) {
            $table->unsignedTinyInteger('free_renewals')->default(0)->after('subscription_end');
        });

        Schema::table('subscription_order_lines', function (Blueprint $table) {
            $table->boolean('complimentary')->default(false)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_order_lines', fn (Blueprint $table) => $table->dropColumn('complimentary'));
        Schema::table('hostels', fn (Blueprint $table) => $table->dropColumn('free_renewals'));
    }
};
