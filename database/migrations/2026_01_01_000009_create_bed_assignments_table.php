<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bed_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->foreignId('bed_id')->constrained('beds')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('join_date');
            $table->date('leave_date')->nullable();
            $table->decimal('monthly_rent', 10, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->text('remarks')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['hostel_id', 'bed_id']);
            $table->index(['hostel_id', 'student_id']);
            // Only one active assignment per bed is enforced in the service layer,
            // but this partial-style guard helps surface accidental duplicates.
            $table->index(['bed_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bed_assignments');
    }
};
