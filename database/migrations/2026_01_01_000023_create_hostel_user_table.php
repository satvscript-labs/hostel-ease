<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostel_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['hostel_id', 'user_id']);
        });

        // Backfill: every hostel admin can access their current primary hostel.
        DB::table('users')->where('role', 'hostel_admin')->whereNotNull('hostel_id')->orderBy('id')
            ->each(function ($user) {
                DB::table('hostel_user')->insertOrIgnore([
                    'hostel_id' => $user->hostel_id,
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostel_user');
    }
};
