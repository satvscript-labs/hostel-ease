<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Public-ID hardening (U4, platform): opaque ULID route key for volume-discount
 * rules (edit/toggle/delete URLs, built in JS on the Discounts page).
 * See _artifact/public_id_hardening/00_plan.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_rules', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable()->after('id');
        });

        DB::table('discount_rules')->select('id')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('discount_rules')->where('id', $row->id)->update(['public_id' => (string) Str::ulid()]);
            }
        });

        Schema::table('discount_rules', function (Blueprint $table) {
            $table->unique('public_id');
        });
    }

    public function down(): void
    {
        Schema::table('discount_rules', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
