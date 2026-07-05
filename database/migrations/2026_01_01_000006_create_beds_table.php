<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('bed_number', 20);       // B1, B2...
            $table->enum('status', ['empty', 'occupied', 'reserved', 'maintenance'])
                ->default('empty')->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['hostel_id', 'room_id']);
            $table->unique(['room_id', 'bed_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beds');
    }
};
