<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop legacy tables
        Schema::dropIfExists('ac_bill_students');
        Schema::dropIfExists('ac_bills');
        Schema::dropIfExists('semester_fees');
        Schema::dropIfExists('monthly_rents');

        // Modify payments to remove polymorphic fields
        Schema::table('payments', function (Blueprint $table) {
            $table->dropMorphs('payable');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            
            $table->string('type', 20)->default('fee'); // 'fee', 'rent', 'ac', 'other'
            $table->string('title');
            
            $table->decimal('amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('balance', 10, 2)->virtualAs('amount - paid_amount');
            
            $table->string('status', 20)->default('pending'); // 'paid', 'partial', 'pending'
            
            $table->date('due_date')->nullable();
            $table->date('promise_date')->nullable();
            $table->string('promise_note')->nullable();
            
            $table->boolean('is_generated_by_system')->default(false);
            
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoice_payment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payment');
        Schema::dropIfExists('invoices');

        Schema::table('payments', function (Blueprint $table) {
            $table->string('payable_type')->nullable();
            $table->unsignedBigInteger('payable_id')->nullable();
            $table->index(['payable_type', 'payable_id']);
        });
    }
};
