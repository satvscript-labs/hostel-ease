<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presence P1 — the immutable in/out register: every punch, in and out, exactly
 * as the device reported it. Never carries a fabricated event (missed-out is a
 * flag on the profile, not a fake row here — 01 §3.4/§4).
 *
 * Idempotency: unique (device, device_user_id, punched_at) so re-polled/re-
 * delivered events upsert into nothing and the overlap window is free.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presence_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('presence_device_id')->constrained()->cascadeOnDelete();

            // Nullable: an unmatched device UserID still lands here and is
            // quarantined (never dropped) until an admin binds it to a person.
            $table->foreignId('presence_profile_id')->nullable()->constrained()->nullOnDelete();

            // Raw id from the device, kept even when matched (audit).
            $table->string('device_user_id');
            $table->timestamp('punched_at');
            $table->string('direction')->default('unknown');
            $table->string('verify_mode')->nullable(); // Face / Card / Password
            $table->string('source')->default('device');
            $table->string('note')->nullable();

            $table->timestamps();

            // The idempotency guard.
            $table->unique(['presence_device_id', 'device_user_id', 'punched_at'], 'presence_punch_dedupe');
            $table->index(['presence_profile_id', 'punched_at']);
            $table->index(['hostel_id', 'punched_at']);
        });

        // Deferred FK for the profile's "last state-producing punch".
        Schema::table('presence_profiles', function (Blueprint $table) {
            $table->foreign('last_punch_id')->references('id')->on('presence_punches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('presence_profiles', function (Blueprint $table) {
            $table->dropForeign(['last_punch_id']);
        });
        Schema::dropIfExists('presence_punches');
    }
};
