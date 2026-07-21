<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presence P5 — the "on leave / gone home" marker (idea #9.5 companion). A
 * nullable date through which the person is a KNOWN absence, so curfew and
 * stale-state flags are suppressed for them (no false late alerts, 03 §6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presence_profiles', function (Blueprint $table) {
            $table->date('on_leave_until')->nullable()->after('has_missed_punch');
        });
    }

    public function down(): void
    {
        Schema::table('presence_profiles', function (Blueprint $table) {
            $table->dropColumn('on_leave_until');
        });
    }
};
