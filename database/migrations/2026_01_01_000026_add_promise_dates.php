<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['semester_fees', 'monthly_rents', 'ac_bill_students'] as $tbl) {
            Schema::table($tbl, function (Blueprint $table) {
                $table->date('promise_date')->nullable()->after('status')->index();
                $table->string('promise_note')->nullable()->after('promise_date');
            });
        }
    }

    public function down(): void
    {
        foreach (['semester_fees', 'monthly_rents', 'ac_bill_students'] as $tbl) {
            Schema::table($tbl, function (Blueprint $table) {
                $table->dropColumn(['promise_date', 'promise_note']);
            });
        }
    }
};
