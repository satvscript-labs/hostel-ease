<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('type', ['aadhaar', 'photo', 'agreement', 'other'])->default('other');
            $table->string('title')->nullable();
            $table->string('file_path');
            $table->date('expiry_date')->nullable()->index();
            $table->boolean('is_signed')->default(false);   // e-sign / agreement
            $table->softDeletes();
            $table->timestamps();

            $table->index(['hostel_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_documents');
    }
};
