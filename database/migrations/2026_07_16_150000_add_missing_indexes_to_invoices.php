<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W6 close-out DB audit: `invoices` had NO indexes. Not one.
 *
 * Every sibling money table has them (payments: hostel_id+student_id,
 * hostel_id+paid_on; expenses: hostel_id+expense_date; ac_bills:
 * hostel_id+bill_month) — invoices, the money hub's central table and the
 * fastest-growing one in the app (every student × every month × forever),
 * got none when the 2026_07_06 unification dropped semester_fees /
 * monthly_rents / ac_bill_students and rebuilt everything into it.
 *
 * EXPLAIN QUERY PLAN confirmed the cost before this: the Finance Board's own
 * list query reported `SCAN invoices`, and the student profile added
 * `USE TEMP B-TREE FOR ORDER BY`. Harmless at 30 demo rows; a table scan per
 * page view on a hostel with three years of history is not.
 *
 * Chosen from the queries that actually run, not speculation:
 *   hostel_id+status      Finance Board list filter + dashboard dues
 *   hostel_id+student_id  student profile invoices (mirrors payments')
 *   hostel_id+type        the covered-until MAX(id) groupwise subquery —
 *                         the heaviest query on the New Invoice modal
 *   hostel_id+due_date    aging / overdue
 *   ac_bill_id            an AC bill's invoice set (withCount/withSum)
 *
 * Additive only — no data touched, no behaviour changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['hostel_id', 'status']);
            $table->index(['hostel_id', 'student_id']);
            $table->index(['hostel_id', 'type']);
            $table->index(['hostel_id', 'due_date']);
            $table->index('ac_bill_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['hostel_id', 'status']);
            $table->dropIndex(['hostel_id', 'student_id']);
            $table->dropIndex(['hostel_id', 'type']);
            $table->dropIndex(['hostel_id', 'due_date']);
            $table->dropIndex(['ac_bill_id']);
        });
    }
};
