<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add joining_date to student_registrations
        Schema::table('student_registrations', function (Blueprint $table) {
            $table->date('joining_date')->nullable()->after('occupation_type');
        });

        // 2. Add fee settings to students
        Schema::table('students', function (Blueprint $table) {
            $table->string('room_preference')->nullable()->after('join_date'); // AC, Non-AC
            $table->string('sharing_preference')->nullable()->after('room_preference'); // Single, Double, Triple, Quad
            $table->decimal('fee_amount', 10, 2)->nullable()->after('sharing_preference');
            $table->string('fee_frequency')->nullable()->after('fee_amount'); // monthly, semester, yearly
        });

        // 3. Drop fee columns from bed_assignments
        Schema::table('bed_assignments', function (Blueprint $table) {
            $table->dropColumn(['fee_amount', 'fee_frequency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_registrations', function (Blueprint $table) {
            $table->dropColumn('joining_date');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'room_preference',
                'sharing_preference',
                'fee_amount',
                'fee_frequency',
            ]);
        });

        Schema::table('bed_assignments', function (Blueprint $table) {
            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->string('fee_frequency', 20)->default('monthly');
        });
    }
};
