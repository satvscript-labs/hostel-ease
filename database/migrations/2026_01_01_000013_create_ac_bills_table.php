<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ac_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->date('bill_month');                  // first day of billed month
            $table->decimal('previous_unit', 10, 2)->default(0);
            $table->decimal('current_unit', 10, 2)->default(0);
            $table->decimal('unit_price', 8, 2)->default(0);
            $table->decimal('total_units', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->enum('distribution', ['equal', 'selected'])->default('equal');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['hostel_id', 'room_id']);
            $table->unique(['room_id', 'bill_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ac_bills');
    }
};
