<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W6.3 metered split (owner-required).
 *
 * The day-ledger split assumed uniform usage across the month — wrong the
 * moment usage isn't flat (30 units before a mid-month join, 5 after: the
 * joiner overpaid). The AC meter reading captured AT the occupancy change
 * turns the month into segments of KNOWN consumption, each with a fixed
 * occupant set.
 *
 * No new table: the readings the split needs exist only at occupancy
 * boundaries, which ARE the assignment rows — and the bill itself anchors
 * month start/end. Nullable on purpose: rows from before this feature (and
 * non-AC rooms) have no reading, and the split degrades to the day-estimate
 * for that boundary, flagged in the breakdown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bed_assignments', function (Blueprint $table) {
            $table->decimal('join_meter_reading', 10, 2)->nullable()->after('leave_date');
            $table->decimal('leave_meter_reading', 10, 2)->nullable()->after('join_meter_reading');
        });
    }

    public function down(): void
    {
        Schema::table('bed_assignments', function (Blueprint $table) {
            $table->dropColumn(['join_meter_reading', 'leave_meter_reading']);
        });
    }
};
