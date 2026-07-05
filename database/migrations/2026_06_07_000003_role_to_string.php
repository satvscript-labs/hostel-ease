<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Widen users.role from enum to a string so sub-user roles (manager,
        // accountant, warden, viewer) can be stored.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('hostel_admin')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'hostel_admin'])->default('hostel_admin')->change();
        });
    }
};
