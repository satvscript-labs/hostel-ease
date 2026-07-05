<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->nullable()->constrained('hostels')->cascadeOnDelete();
            $table->string('group')->default('general');
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['hostel_id', 'group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
