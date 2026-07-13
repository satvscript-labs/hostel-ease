<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_registrations', function (Blueprint $table) {
            $table->string('college')->nullable()->after('occupation_type');
            $table->string('field_of_study')->nullable()->after('college');
            $table->string('aadhaar_file')->nullable()->after('photo');
        });
    }

    public function down(): void
    {
        Schema::table('student_registrations', function (Blueprint $table) {
            $table->dropColumn(['college', 'field_of_study', 'aadhaar_file']);
        });
    }
};
