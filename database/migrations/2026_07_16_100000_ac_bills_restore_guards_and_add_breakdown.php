<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W6.3 (owner-approved).
 *
 * Restores what the Jul-08 table recreate silently dropped from the original
 * ac_bills design:
 *  - unique(room_id, bill_month) — without it the same room can be billed
 *    twice for the same month and every occupant gets charged twice. The
 *    controller also checks first so the user gets a sentence, not a 500;
 *    this constraint is the backstop that makes the race impossible.
 *  - softDeletes — destroy() was HARD-deleting the bill while its invoices
 *    soft-deleted, leaving half an audit trail.
 *  - the hostel_id index.
 *
 * Adds split_breakdown: the persisted explanation of the day-ledger split
 * (who was in the room, for which days, bearing what share). Stored, not
 * recomputed — assignments get edited later, and the bill must keep telling
 * the story it was generated with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ac_bills', function (Blueprint $table) {
            $table->softDeletes();
            $table->json('split_breakdown')->nullable();

            $table->unique(['room_id', 'bill_month']);
            $table->index(['hostel_id', 'bill_month']);
        });
    }

    public function down(): void
    {
        Schema::table('ac_bills', function (Blueprint $table) {
            $table->dropUnique(['room_id', 'bill_month']);
            $table->dropIndex(['hostel_id', 'bill_month']);
            $table->dropColumn(['deleted_at', 'split_breakdown']);
        });
    }
};
