<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_modes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->string('name');                         // e.g. "PhonePe", "Bank Transfer"
            $table->string('code', 40);                     // slug used on payments.mode
            $table->boolean('requires_reference')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hostel_id', 'code']);
        });

        // Seed the four defaults for every existing hostel.
        $defaults = [
            ['code' => 'cash', 'name' => 'Cash', 'requires_reference' => 0],
            ['code' => 'upi', 'name' => 'UPI', 'requires_reference' => 0],
            ['code' => 'cheque', 'name' => 'Cheque', 'requires_reference' => 1],
            ['code' => 'rtgs', 'name' => 'RTGS / NEFT', 'requires_reference' => 1],
        ];
        foreach (DB::table('hostels')->pluck('id') as $hostelId) {
            foreach ($defaults as $i => $d) {
                DB::table('payment_modes')->insertOrIgnore([
                    'hostel_id' => $hostelId,
                    'name' => $d['name'],
                    'code' => $d['code'],
                    'requires_reference' => $d['requires_reference'],
                    'sort_order' => $i,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_modes');
    }
};
