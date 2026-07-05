<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->foreignId('floor_id')->constrained('floors')->cascadeOnDelete();
            $table->string('room_number', 50);
            $table->enum('room_type', ['ac', 'non_ac'])->default('non_ac');
            $table->unsignedTinyInteger('sharing')->default(1);     // beds per room
            $table->decimal('rent', 10, 2)->default(0);             // rent per student
            $table->softDeletes();
            $table->timestamps();

            $table->index(['hostel_id', 'floor_id']);
            $table->unique(['hostel_id', 'room_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
