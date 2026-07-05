<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Yearly" fees are stored as semester_fees rows tagged period_type='yearly'
 * (a lump annual fee), alongside the existing per-semester rows. The unique
 * key is widened to include period_type so Year 1 and Semester 1 can coexist.
 *
 * Written idempotently: a prior partial run may already have added the column,
 * so each step is guarded and safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('semester_fees', 'period_type')) {
            Schema::table('semester_fees', function (Blueprint $table) {
                $table->string('period_type', 20)->default('semester')->after('semester');
            });
        }

        // Drop the old (student_id, semester) unique if it's still there.
        try {
            Schema::table('semester_fees', function (Blueprint $table) {
                $table->dropUnique('semester_fees_student_id_semester_unique');
            });
        } catch (\Throwable $e) {
            // Already dropped — ignore.
        }

        // Add the widened unique if it isn't already present.
        try {
            Schema::table('semester_fees', function (Blueprint $table) {
                $table->unique(['student_id', 'period_type', 'semester']);
            });
        } catch (\Throwable $e) {
            // Already present — ignore.
        }
    }

    public function down(): void
    {
        try {
            Schema::table('semester_fees', function (Blueprint $table) {
                $table->dropUnique(['student_id', 'period_type', 'semester']);
            });
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            Schema::table('semester_fees', function (Blueprint $table) {
                $table->unique(['student_id', 'semester']);
            });
        } catch (\Throwable $e) {
            // ignore
        }

        if (Schema::hasColumn('semester_fees', 'period_type')) {
            Schema::table('semester_fees', function (Blueprint $table) {
                $table->dropColumn('period_type');
            });
        }
    }
};
