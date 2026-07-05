<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ac_bill_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->foreignId('ac_bill_id')->constrained('ac_bills')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->enum('status', ['paid', 'partial', 'due'])->default('due')->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['hostel_id', 'student_id']);
            $table->unique(['ac_bill_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ac_bill_students');
    }
};
