<?php

namespace App\Services\Billing;

use App\Enums\AccountStatus;
use App\Enums\BillingPeriod;
use App\Models\Hostel;
use App\Models\Subscription;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionOrderLine;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2 back-fill — rebuilds the account spine from the existing per-branch
 * data, losing no paid time (BRD D4, NFR-6).
 *
 *  - One SubscriptionAccount per owner; anchor = the furthest branch end-date.
 *  - One SubscriptionOrder (+ one line) per legacy `subscriptions` row.
 *
 * Idempotent: accounts keyed on owner_id, orders keyed on legacy_subscription_id,
 * lines keyed on (order, branch). Safe to re-run.
 */
class AccountBackfillService
{
    /**
     * @return array{accounts:int, orders:int, lines:int, skipped:int}
     */
    public function run(): array
    {
        return DB::transaction(function () {
            $accounts = $this->backfillAccounts();
            [$orders, $lines, $skipped] = $this->backfillOrders();

            return compact('accounts', 'orders', 'lines', 'skipped');
        });
    }

    /** One account per owner; anchor = furthest branch end-date (never lose time). */
    protected function backfillAccounts(): int
    {
        $count = 0;

        User::where('role', 'hostel_admin')->cursor()->each(function (User $owner) use (&$count) {
            $branchIds = $owner->accessibleHostelIds();
            if (empty($branchIds)) {
                return;
            }

            $branches = Hostel::whereIn('id', $branchIds)->get();
            if ($branches->isEmpty()) {
                return;
            }

            $anchor = $branches->max('subscription_end');   // Carbon|null — furthest coverage
            $start = $branches->min('subscription_start');

            $latest = Subscription::acrossHostels()
                ->whereIn('hostel_id', $branchIds)
                ->latest('end_date')
                ->first();
            $period = $this->normalizePeriod($latest?->plan);

            $anyActive = $branches->contains(fn (Hostel $b) => $b->isActive());

            SubscriptionAccount::updateOrCreate(
                ['owner_id' => $owner->id],
                [
                    'period' => $period->value,
                    'current_period_start' => $start,
                    'current_period_end' => $anchor,
                    'status' => $this->deriveStatus($anyActive, $period)->value,
                ],
            );
            $count++;
        });

        return $count;
    }

    /** One order + one line per legacy subscription row. */
    protected function backfillOrders(): array
    {
        $orders = 0;
        $lines = 0;
        $skipped = 0;

        Subscription::acrossHostels()->cursor()->each(function (Subscription $sub) use (&$orders, &$lines, &$skipped) {
            $account = $this->accountForHostel($sub->hostel_id);
            if (! $account) {
                $skipped++;

                return;
            }

            $period = $this->normalizePeriod($sub->plan);

            $order = SubscriptionOrder::updateOrCreate(
                ['legacy_subscription_id' => $sub->id],
                [
                    'account_id' => $account->id,
                    'period' => $period->value,
                    'quantity' => 1,
                    'subtotal' => $sub->amount,
                    'discount_total' => 0,
                    'amount' => $sub->amount,
                    'payment_status' => $sub->payment_status,
                    'payment_method' => $sub->payment_method,
                    'transaction_number' => $sub->transaction_number,
                    'razorpay_order_id' => $sub->razorpay_order_id,
                    'remarks' => $sub->remarks,
                ],
            );
            $orders++;

            SubscriptionOrderLine::updateOrCreate(
                ['order_id' => $order->id, 'branch_id' => $sub->hostel_id],
                [
                    'amount' => $sub->amount,
                    'start_date' => $sub->start_date,
                    'end_date' => $sub->end_date,
                ],
            );
            $lines++;
        });

        return [$orders, $lines, $skipped];
    }

    protected function accountForHostel(int $hostelId): ?SubscriptionAccount
    {
        $owner = User::where('role', 'hostel_admin')
            ->whereHas('hostels', fn ($q) => $q->where('hostels.id', $hostelId))
            ->first();

        // Fallback: the owner login shares the hostel's mobile.
        if (! $owner) {
            $hostel = Hostel::find($hostelId);
            $owner = $hostel
                ? User::where('role', 'hostel_admin')->where('mobile', $hostel->mobile)->first()
                : null;
        }

        return $owner ? SubscriptionAccount::firstWhere('owner_id', $owner->id) : null;
    }

    protected function normalizePeriod(?string $plan): BillingPeriod
    {
        return BillingPeriod::tryFrom((string) $plan) ?? BillingPeriod::Yearly;
    }

    protected function deriveStatus(bool $anyActive, BillingPeriod $period): AccountStatus
    {
        if (! $anyActive) {
            return AccountStatus::Expired;
        }

        return $period === BillingPeriod::Trial ? AccountStatus::Trial : AccountStatus::Active;
    }
}
