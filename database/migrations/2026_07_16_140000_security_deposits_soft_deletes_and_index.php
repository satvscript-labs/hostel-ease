<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W6.4: security_deposits hardening.
 *
 * SoftDeletes for parity with every other money table (nothing money-shaped
 * hard-deletes in this app), and the hostel/status index the list page's
 * filter + stats queries walk. The far bigger W6.4 deposit fix — the missing
 * tenant scope — is model-level (BelongsToHostel on SecurityDeposit), not
 * schema: hostel_id always existed, it just wasn't enforced on reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_deposits', function (Blueprint $table) {
            $table->softDeletes();
            $table->index(['hostel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('security_deposits', function (Blueprint $table) {
            $table->dropIndex(['hostel_id', 'status']);
            $table->dropSoftDeletes();
        });
    }
};
