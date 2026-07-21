<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Public-ID hardening (U2, financial): opaque ULID route key for security
 * deposits. Additive; soft-deleted rows backfilled. See
 * _artifact/public_id_hardening/00_plan.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_deposits', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable()->after('id');
        });

        DB::table('security_deposits')->select('id')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('security_deposits')->where('id', $row->id)->update(['public_id' => (string) Str::ulid()]);
            }
        });

        Schema::table('security_deposits', function (Blueprint $table) {
            $table->unique('public_id');
        });
    }

    public function down(): void
    {
        Schema::table('security_deposits', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
