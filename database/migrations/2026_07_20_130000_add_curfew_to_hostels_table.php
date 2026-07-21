<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presence P5 — per-branch curfew WINDOW (owner feedback: a from→to range, not a
 * single time). A student out DURING [curfew_from, curfew_to] (which may cross
 * midnight, e.g. 22:00→06:00) is "late". Both null = no curfew (the default);
 * `curfew_notify` opts into the nightly warden alert. Students only — staff have
 * no curfew. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hostels', function (Blueprint $table) {
            $table->string('curfew_from', 5)->nullable()->after('status'); // 'HH:MM'
            $table->string('curfew_to', 5)->nullable()->after('curfew_from'); // 'HH:MM'
            $table->boolean('curfew_notify')->default(false)->after('curfew_to');
            $table->timestamp('curfew_notified_at')->nullable()->after('curfew_notify'); // dedupe the alert
        });
    }

    public function down(): void
    {
        Schema::table('hostels', function (Blueprint $table) {
            $table->dropColumn(['curfew_from', 'curfew_to', 'curfew_notify', 'curfew_notified_at']);
        });
    }
};
