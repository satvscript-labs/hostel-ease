<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->string('name');
            $table->string('photo')->nullable();
            $table->string('mobile', 20);
            $table->string('father_mobile', 20)->nullable();
            $table->string('mother_mobile', 20)->nullable();
            $table->string('guardian_mobile', 20)->nullable();
            $table->string('aadhaar', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->enum('occupation_type', ['student', 'working'])->default('student')->index();
            $table->date('join_date')->nullable();
            $table->date('leave_date')->nullable()->index();
            $table->enum('status', ['active', 'left'])->default('active')->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['hostel_id', 'status']);
            $table->index(['hostel_id', 'mobile']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
