<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('ac_bill_id')->nullable()->after('type')
                ->constrained('ac_bills')->nullOnDelete();
        });

        // Backfill existing AC invoices that were only linked by title string
        // (e.g. "AC Bill #12 - Jul 2026 (Room 101)") so no history is lost.
        DB::table('invoices')->where('type', 'ac')->whereNull('ac_bill_id')
            ->orderBy('id')->chunkById(200, function ($invoices) {
                foreach ($invoices as $invoice) {
                    if (preg_match('/AC Bill #(\d+) -/', (string) $invoice->title, $m)) {
                        DB::table('invoices')->where('id', $invoice->id)
                            ->update(['ac_bill_id' => (int) $m[1]]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ac_bill_id');
        });
    }
};
