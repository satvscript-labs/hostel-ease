<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('owner_name');
            $table->string('mobile', 20);
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('gst_number', 20)->nullable();
            $table->date('subscription_start')->nullable();
            $table->date('subscription_end')->nullable()->index();
            $table->enum('status', ['active', 'expired', 'suspended'])->default('active')->index();
            $table->string('logo')->nullable();
            $table->json('settings')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostels');
    }
};
