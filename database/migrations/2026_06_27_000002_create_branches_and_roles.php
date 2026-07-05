<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Branches table (multiple branches per hostel)
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->string('name'); // e.g., "Main Branch", "North Campus"
            $table->string('code', 20)->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['hostel_id', 'name']);
        });

        // Roles table (Super Admin, Hostel Admin, Manager, Accountant, etc.)
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // super_admin, hostel_admin, manager, accountant
            $table->string('display_name'); // "Super Admin", "Hostel Admin", "Manager", "Accountant"
            $table->text('description')->nullable();
            $table->json('permissions')->nullable(); // ['users.create', 'users.edit', 'reports.view', ...]
            $table->timestamps();
        });

        // Add role_id and branch_id to users (nullable for super admin)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropConstrainedForeignId('branch_id');
        });
        Schema::dropIfExists('roles');
        Schema::dropIfExists('branches');
    }
};
