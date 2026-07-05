<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('designation')->nullable();   // Cook, Warden, Cleaner...
            $table->string('mobile', 15)->nullable();
            $table->decimal('monthly_salary', 10, 2)->default(0);
            $table->date('join_date')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('hostel_id');
        });

        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'half_day', 'leave'])->default('present');
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['staff_id', 'date']);
            $table->index(['hostel_id', 'date']);
        });

        Schema::create('staff_salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->date('salary_month');               // first day of the paid month
            $table->decimal('amount', 10, 2);
            $table->date('paid_on');
            $table->string('mode', 40)->default('cash');
            $table->string('reference_number')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['hostel_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_salary_payments');
        Schema::dropIfExists('staff_attendances');
        Schema::dropIfExists('staff');
    }
};
