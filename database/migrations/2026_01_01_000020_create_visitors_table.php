<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('name');
            $table->string('mobile', 20)->nullable();
            $table->string('purpose')->nullable();
            $table->string('id_proof')->nullable();          // e.g. "Aadhaar 1234"
            $table->dateTime('check_in');
            $table->dateTime('check_out')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['hostel_id', 'check_in']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
