<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Public-ID hardening (U0): give students an opaque ULID route key so profile
 * URLs stop being guessable sequential integers. Purely additive — the integer
 * `id` PK and every foreign key are untouched. See
 * _artifact/public_id_hardening/00_plan.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Add nullable so it can land on a populated production table.
        Schema::table('students', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable()->after('id');
        });

        // 2) Backfill EVERY existing row (soft-deleted included) with a fresh
        //    ULID. Raw DB so no global TenantScope / SoftDelete scope hides rows.
        DB::table('students')->select('id')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('students')->where('id', $row->id)->update([
                    'public_id' => (string) Str::ulid(),
                ]);
            }
        });

        // 3) Now that no NULLs remain, enforce uniqueness.
        Schema::table('students', function (Blueprint $table) {
            $table->unique('public_id');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
