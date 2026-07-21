<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Public-ID hardening (U4, platform): opaque ULID route key for hostels
 * (branch switcher, branch rename, super-admin hostel pages).
 *
 * NOTE: this changes the ROUTE key only. `hostels.id` remains the tenant
 * identifier used by TenantScope, session `active_hostel_id` and every
 * `hostel_id` foreign key — none of which are touched. Branch switching was
 * already authorised by membership (`canAccessHostel`), not by the id being
 * unguessable; this just removes enumeration.
 * See _artifact/public_id_hardening/00_plan.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hostels', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable()->after('id');
        });

        DB::table('hostels')->select('id')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('hostels')->where('id', $row->id)->update(['public_id' => (string) Str::ulid()]);
            }
        });

        Schema::table('hostels', function (Blueprint $table) {
            $table->unique('public_id');
        });
    }

    public function down(): void
    {
        Schema::table('hostels', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
