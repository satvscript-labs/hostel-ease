<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-hostel public registration token (encoded in the link/QR).
        Schema::table('hostels', function (Blueprint $table) {
            $table->string('registration_token', 40)->nullable()->unique()->after('status');
        });

        Schema::create('student_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('mobile', 10);
            $table->string('father_mobile', 10)->nullable();
            $table->string('mother_mobile', 10)->nullable();
            $table->string('aadhaar', 12)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('occupation_type')->default('student');
            $table->string('photo')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['hostel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_registrations');
        Schema::table('hostels', function (Blueprint $table) {
            $table->dropColumn('registration_token');
        });
    }
};
