<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presence S1 — how the Connector reaches a device (01 §3.1, 10 §9).
 *
 * Under the retired iDMS model the middleware knew how to reach devices, so we
 * only stored a serial. Under the SDK model the Connector logs in to each unit
 * directly (Dahua NetSDK, TCP :37777), so the connection details live with the
 * device row.
 *
 * All nullable: a device registered before the Connector exists (or one that
 * auto-registers, where we learn its address when it dials in) is still valid.
 * `password` is encrypted at rest and never leaves the server (model $hidden).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presence_devices', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('serial_number');   // 45 = INET6 max
            $table->unsignedSmallInteger('port')->default(37777)->after('ip_address');
            $table->string('username')->nullable()->after('port');
            $table->text('password')->nullable()->after('username');                // encrypted cast → ciphertext
            $table->string('connection_mode')->default('tcp')->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('presence_devices', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'port', 'username', 'password', 'connection_mode']);
        });
    }
};
