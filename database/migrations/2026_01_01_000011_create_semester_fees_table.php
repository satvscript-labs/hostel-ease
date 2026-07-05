<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semester_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->unsignedTinyInteger('semester');     // 1..8
            $table->decimal('total_fee', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('balance', 10, 2)->default(0);
            $table->enum('status', ['paid', 'partial', 'pending'])->default('pending')->index();
            $table->date('due_date')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['hostel_id', 'student_id']);
            $table->unique(['student_id', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semester_fees');
    }
};
