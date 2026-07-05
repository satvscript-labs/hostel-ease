<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            // Optional links to the obligation being settled.
            $table->nullableMorphs('payable');           // semester_fee / monthly_rent / ac_bill_student
            $table->string('receipt_number')->unique();
            $table->decimal('amount', 10, 2);
            $table->enum('payment_type', ['full', 'partial', 'advance'])->default('full');
            $table->enum('mode', ['cash', 'upi', 'cheque', 'rtgs', 'online'])->default('cash');
            $table->string('reference_number')->nullable();   // cheque / rtgs / txn id
            $table->date('paid_on');
            $table->text('remarks')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['hostel_id', 'student_id']);
            $table->index(['hostel_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
