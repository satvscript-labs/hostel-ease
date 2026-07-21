<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presence P1 — the identity mapping: one row per enrolled person, polymorphic
 * to Student or Staff (one engine, two audiences kept separate at the view
 * layer). Holds the DERIVED current state so the boards don't re-derive from
 * 100k punches per request; the punch log stays the single source of truth and
 * a rebuild can re-derive this at any time. See 01 §3.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presence_profiles', function (Blueprint $table) {
            $table->id();
            // URL-exposed (enroll / revoke / per-person history, P2–P4).
            $table->char('public_id', 26)->unique();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();

            // Polymorphic owner — Student or Staff.
            $table->morphs('presenceable');

            // The UserID we generate and push to the device (S412 / T18).
            // Unique per hostel — model ids are global, so this holds platform-wide.
            $table->string('device_user_id');
            $table->string('card_number')->nullable();

            // Derived current state (in / out / unknown) + when it last flipped.
            $table->string('state')->default('unknown');
            $table->timestamp('state_changed_at')->nullable();
            // The punch that produced the current state (nullable FK set later to
            // avoid a chicken-and-egg with presence_punches).
            $table->unsignedBigInteger('last_punch_id')->nullable();

            // A missed "out" is inferred but NOT fabricated into the register —
            // the gap is flagged here instead (01 §4).
            $table->boolean('has_missed_punch')->default(false);

            $table->string('enrollment_status')->default('pending');
            $table->timestamp('enrolled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ($table->morphs already indexed presenceable_type/_id.)
            $table->unique(['hostel_id', 'device_user_id']);
            $table->index(['hostel_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presence_profiles');
    }
};
