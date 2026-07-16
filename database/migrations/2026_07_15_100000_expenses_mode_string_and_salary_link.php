<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W6.2 (owner-approved):
 *
 * 1. expenses.mode: enum → string(40). The enum hardcoded a payment-mode
 *    vocabulary (cash/upi/cheque/rtgs/online — 'online' was never even
 *    offered by any UI) that predates the tenant-configurable payment_modes
 *    table. Expenses now validate against that table like student payments
 *    do, so a hostel that adds "PhonePe" can spend through it too. string(40)
 *    matches payment_modes.code exactly.
 *
 * 2. expenses.staff_salary_payment_id: the link that makes salaries visible
 *    to the P&L. Paying a salary auto-creates a linked expense; deleting the
 *    salary entry removes it. UNIQUE because one salary payment is one
 *    expense — a duplicate link would double-count the same money, which is
 *    the exact bug this column exists to prevent. nullOnDelete is the
 *    DB-level backstop only; the controller deletes the pair explicitly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('mode', 40)->default('cash')->change();

            $table->foreignId('staff_salary_payment_id')
                ->nullable()
                ->unique()
                ->constrained('staff_salary_payments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_salary_payment_id');

            // Not restored to an enum: rows may legitimately hold owner-defined
            // mode codes by now, and re-imposing the old CHECK would make the
            // table reject its own data.
            $table->string('mode', 40)->default('cash')->change();
        });
    }
};
