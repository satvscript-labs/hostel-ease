<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allow custom, admin-defined payment modes (was an ENUM limited to 4 values).
        Schema::table('payments', function (Blueprint $table) {
            $table->string('mode', 40)->default('cash')->change();
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('mode', 40)->default('cash')->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('mode', ['cash', 'upi', 'cheque', 'rtgs', 'online'])->default('cash')->change();
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->enum('mode', ['cash', 'upi', 'cheque', 'rtgs', 'online'])->default('cash')->change();
        });
    }
};
