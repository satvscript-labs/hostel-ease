<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->nullable()->constrained('hostels')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('type');          // renewal_due, fee_pending, ac_bill, vacancy, leaving_soon...
            $table->string('title');
            $table->text('message')->nullable();
            $table->json('data')->nullable();
            $table->string('level', 20)->default('info');   // info, warning, danger, success
            $table->timestamp('read_at')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['hostel_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
