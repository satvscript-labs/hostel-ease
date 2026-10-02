<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase S1 — three things, in one migration because the third depends on the first.
 *
 *  1. subscription_orders gains `kind` (what the charge was) and `collection` (how
 *     the money arrived), both back-filled for existing rows.
 *  2. hostels gains the branch-cancellation columns (D11): an owner-side REQUEST
 *     and an operator-side CONFIRMATION, deliberately separate from `status`, which
 *     already carries operator-hold and lapsed-payment meanings.
 *  3. COVERAGE BACK-FILL. S1 makes branch coverage a projection over paid order
 *     lines. Some branches hold coverage no line supports — self-signup trials
 *     (RegisterController wrote hostels.subscription_end inline) and legacy rows.
 *     Switching to the projection without this would NULL their coverage and lock
 *     out working tenants. So each one gets a ₹0 `adjustment` order carrying its
 *     existing dates, after which the projection is exact and the S0 audit's
 *     check 2 is empty.
 *
 * Idempotent throughout: re-running adds nothing and changes nothing.
 */
return new class extends Migration
{
    private const BACKFILL_REMARK = 'Back-filled at S1 from hostels.subscription_end';

    public function up(): void
    {
        Schema::table('subscription_orders', function (Blueprint $table) {
            // Nullable + indexed; the back-fill below fills every existing row and
            // the service always sets them from here on.
            $table->string('kind')->nullable()->after('period')->index();
            $table->string('collection')->nullable()->after('payment_method')->index();
        });

        Schema::table('hostels', function (Blueprint $table) {
            // The owner ASKS (D11 §5: the owner requests, the operator decides).
            $table->timestamp('cancellation_requested_at')->nullable()->after('status');
            $table->string('cancellation_requested_reason')->nullable()->after('cancellation_requested_at');
            // The operator CONFIRMS. Indexed because it is in the billing-quantity
            // filter, which runs on every quote.
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_requested_reason')->index();
            $table->string('cancellation_reason')->nullable()->after('cancelled_at');
        });

        $this->backfillOrderKinds();
        $this->backfillUnsupportedCoverage();
    }

    /**
     * Derive kind/collection for orders written before the columns existed.
     *
     *  · comp       → payment_method 'comp' (a ₹0 operator gift)
     *  · trial      → period 'trial'
     *  · add_branch → a single-line order on an account that has other orders
     *                 (a first order on a new account is the purchase)
     *  · renewal    → everything else
     *  · collection → 'checkout' when Razorpay handled it, else 'offline'
     */
    private function backfillOrderKinds(): void
    {
        DB::table('subscription_orders')->whereNull('kind')->orderBy('id')->chunkById(200, function ($orders) {
            foreach ($orders as $order) {
                $lineCount = DB::table('subscription_order_lines')->where('order_id', $order->id)->count();
                $earlier = DB::table('subscription_orders')
                    ->where('account_id', $order->account_id)
                    ->where('id', '<', $order->id)
                    ->whereNull('deleted_at')
                    ->exists();

                $kind = match (true) {
                    $order->payment_method === 'comp' => 'comp',
                    $order->period === 'trial' => 'trial',
                    $lineCount === 1 && $earlier => 'add_branch',
                    ! $earlier => 'purchase',
                    default => 'renewal',
                };

                DB::table('subscription_orders')->where('id', $order->id)->update([
                    'kind' => $kind,
                    'collection' => $order->razorpay_order_id ? 'checkout' : 'offline',
                ]);
            }
        });
    }

    /**
     * Give every branch whose coverage exceeds its paid order lines an adjustment
     * order that accounts for the difference, so the S1 projection preserves it.
     */
    private function backfillUnsupportedCoverage(): void
    {
        $hostels = DB::table('hostels')->whereNotNull('subscription_end')->whereNull('deleted_at')->get();

        foreach ($hostels as $hostel) {
            // What the ledger already supports for this branch.
            $supported = DB::table('subscription_order_lines')
                ->join('subscription_orders', 'subscription_orders.id', '=', 'subscription_order_lines.order_id')
                ->where('subscription_order_lines.branch_id', $hostel->id)
                ->where('subscription_orders.payment_status', 'paid')
                ->whereNull('subscription_orders.deleted_at')
                ->max('subscription_order_lines.end_date');

            if ($supported !== null && $supported >= $hostel->subscription_end) {
                continue;   // already fully backed by the ledger
            }

            $account = $this->accountIdFor($hostel);
            if (! $account) {
                // No resolvable owner/account (a legacy orphan). Nothing to hang an
                // order off; AccountBillingService::ownerForBranch() self-heals these
                // on first touch, and the audit keeps reporting it until then.
                continue;
            }

            // Idempotency: one back-fill order per branch, ever.
            $exists = DB::table('subscription_orders')
                ->join('subscription_order_lines', 'subscription_orders.id', '=', 'subscription_order_lines.order_id')
                ->where('subscription_order_lines.branch_id', $hostel->id)
                ->where('subscription_orders.remarks', self::BACKFILL_REMARK)
                ->exists();

            if ($exists) {
                continue;
            }

            $orderId = DB::table('subscription_orders')->insertGetId([
                'account_id' => $account,
                'period' => 'trial',
                'kind' => 'adjustment',
                'quantity' => 1,
                'subtotal' => 0,
                'discount_total' => 0,
                'amount' => 0,
                'payment_status' => 'paid',       // it granted real coverage, so it must project
                'payment_method' => null,
                'collection' => 'offline',
                'remarks' => self::BACKFILL_REMARK,
                'public_id' => (string) \Illuminate\Support\Str::ulid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('subscription_order_lines')->insert([
                'order_id' => $orderId,
                'branch_id' => $hostel->id,
                'amount' => 0,
                'start_date' => $hostel->subscription_start ?? $hostel->created_at ?? now()->toDateString(),
                'end_date' => $hostel->subscription_end,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** The subscription account a branch belongs to, by the explicit owner FK then the pivot. */
    private function accountIdFor(object $hostel): ?int
    {
        $ownerId = $hostel->owner_id
            ?: DB::table('hostel_user')
                ->join('users', 'users.id', '=', 'hostel_user.user_id')
                ->where('hostel_user.hostel_id', $hostel->id)
                ->where('users.role', 'hostel_admin')
                ->orderBy('users.id')
                ->value('users.id');

        if (! $ownerId) {
            return null;
        }

        $accountId = DB::table('subscription_accounts')->where('owner_id', $ownerId)->value('id');

        if ($accountId) {
            return $accountId;
        }

        return DB::table('subscription_accounts')->insertGetId([
            'owner_id' => $ownerId,
            'period' => 'yearly',
            'status' => 'active',
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('subscription_order_lines')
            ->whereIn('order_id', DB::table('subscription_orders')->where('remarks', self::BACKFILL_REMARK)->pluck('id'))
            ->delete();
        DB::table('subscription_orders')->where('remarks', self::BACKFILL_REMARK)->delete();

        Schema::table('hostels', function (Blueprint $table) {
            $table->dropIndex(['cancelled_at']);
            $table->dropColumn([
                'cancellation_requested_at',
                'cancellation_requested_reason',
                'cancelled_at',
                'cancellation_reason',
            ]);
        });

        Schema::table('subscription_orders', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropIndex(['collection']);
            $table->dropColumn(['kind', 'collection']);
        });
    }
};
