<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-device capacity — every TimeWatch model holds a different number of users/
 * faces (the TrueFace1000EW is 1,000, others differ), so the "filling up" gauge
 * must read a per-device ceiling, never a hardcoded constant. Nullable: capacity
 * is optional (owner enters it from the model's datasheet, or a future sync reads
 * it from GetDeviceList). When null, the card shows the enrolled count without a
 * gauge rather than measuring against a wrong number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presence_devices', function (Blueprint $table) {
            $table->unsignedInteger('face_capacity')->nullable()->after('face_count');
        });
    }

    public function down(): void
    {
        Schema::table('presence_devices', function (Blueprint $table) {
            $table->dropColumn('face_capacity');
        });
    }
};
