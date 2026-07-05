<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->string('plan')->default('1_year');
            $table->date('start_date');
            $table->date('end_date')->index();
            $table->decimal('amount', 10, 2)->default(0);
            $table->enum('payment_status', ['paid', 'pending', 'failed'])->default('pending')->index();
            $table->enum('payment_method', ['cash', 'upi', 'cheque', 'rtgs', 'online'])->nullable();
            $table->string('transaction_number')->nullable();
            $table->text('remarks')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
