<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presence P4 — a MANUAL correction punch (source=manual) has no device: it's a
 * real admin statement ("they went out at 19:00"), not a gate scan. So the
 * device FK must be nullable. Device punches still set it; the idempotency
 * unique index (device, device_user_id, punched_at) is unaffected — NULLs are
 * distinct there, which is right (manual punches aren't deduped).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presence_punches', function (Blueprint $table) {
            $table->foreignId('presence_device_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('presence_punches', function (Blueprint $table) {
            $table->foreignId('presence_device_id')->nullable(false)->change();
        });
    }
};
