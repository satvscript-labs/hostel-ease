<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "Yearly" is being retired — only Monthly and Semester remain. Convert any
     * existing yearly assignments to Semester so their fee handling is consistent.
     * The enum already permits 'semester'; we leave the column definition as-is to
     * avoid a risky cross-database enum alter.
     */
    public function up(): void
    {
        DB::table('bed_assignments')
            ->where('fee_frequency', 'yearly')
            ->update(['fee_frequency' => 'semester']);
    }

    public function down(): void
    {
        // No-op: we don't restore the retired 'yearly' value.
    }
};
