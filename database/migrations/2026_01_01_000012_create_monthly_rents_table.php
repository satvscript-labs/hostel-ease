<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_rents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('rent_month');                  // first day of the billed month
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('balance', 10, 2)->default(0);
            $table->enum('status', ['paid', 'partial', 'due'])->default('due')->index();
            $table->date('due_date')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['hostel_id', 'student_id']);
            $table->unique(['student_id', 'rent_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_rents');
    }
};
