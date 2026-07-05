<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bed_assignments', function (Blueprint $table) {
            $table->decimal('fee_amount', 10, 2)->default(0)->after('monthly_rent');
            $table->enum('fee_frequency', ['monthly', 'semester', 'yearly'])
                ->default('semester')->after('fee_amount');
        });

        // Backfill existing rows from the old monthly_rent value.
        DB::table('bed_assignments')->update([
            'fee_amount' => DB::raw('monthly_rent'),
        ]);
    }

    public function down(): void
    {
        Schema::table('bed_assignments', function (Blueprint $table) {
            $table->dropColumn(['fee_amount', 'fee_frequency']);
        });
    }
};
