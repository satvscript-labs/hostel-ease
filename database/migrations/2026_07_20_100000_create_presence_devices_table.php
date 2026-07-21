<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presence P1 — a physical gate unit (TrueFace / iDMS). Tenant-scoped: a device
 * belongs to exactly one branch. See _artifact/presence_module/01_module_plan.md §3.1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presence_devices', function (Blueprint $table) {
            $table->id();
            // URL-exposed (device management, P2) — opaque route key from day one
            // (development_standards §1.1).
            $table->char('public_id', 26)->unique();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();

            // The iDMS DeviceID / SerialNumber, e.g. TW60000324000187. Unique
            // across the platform — a physical unit reports to exactly one iDMS.
            $table->string('serial_number')->unique();
            $table->string('name');

            // How this unit's punches get a direction (entry/exit/toggle).
            $table->string('direction_mode')->default('toggle');
            $table->boolean('is_active')->default(true);

            // Health cache, refreshed from GetDeviceList by the sync command.
            $table->string('device_status')->default('unknown');
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_log_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedInteger('enrolled_count')->default(0);
            $table->unsignedInteger('face_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['hostel_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presence_devices');
    }
};
