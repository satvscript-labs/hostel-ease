<?php

namespace App\Services;

use App\Models\Hostel;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Support\Tenant;

/**
 * Generates the dashboard alert feed. Alerts are deduplicated by a stable
 * signature so an unread alert for the same subject is refreshed in place
 * rather than duplicated; alerts that no longer apply are cleared.
 */
class NotificationService
{
    /**
     * Create or refresh an unread alert identified by (hostel, type, sig).
     */
    public function push(?int $hostelId, string $type, string $sig, string $title, ?string $message = null, string $level = 'info'): Notification
    {
        $existing = Notification::where('hostel_id', $hostelId)
            ->where('type', $type)
            ->whereNull('read_at')
            ->where('data->sig', $sig)
            ->first();

        $payload = ['title' => $title, 'message' => $message, 'level' => $level, 'data' => ['sig' => $sig]];

        if ($existing) {
            $existing->update($payload);

            return $existing;
        }

        return Notification::create(array_merge($payload, ['hostel_id' => $hostelId, 'type' => $type]));
    }

    /**
     * Remove any unread alert of (hostel, type, sig) — used when a condition clears.
     */
    public function clear(?int $hostelId, string $type, string $sig): void
    {
        Notification::where('hostel_id', $hostelId)->where('type', $type)
            ->whereNull('read_at')->where('data->sig', $sig)->delete();
    }

    /**
     * Build alerts for a single hostel admin context.
     */
    public function generateForHostel(Hostel $hostel): void
    {
        Tenant::set($hostel->id);

        // Subscription due
        $days = $hostel->daysUntilExpiry();
        if ($days !== null && $days <= 30) {
            $this->push($hostel->id, 'subscription_due', 'sub',
                'Subscription expiring',
                "Your subscription "
                .($days < 0 ? 'has expired' : "expires in {$days} day(s)").
                ". Please renew.",
                $days <= 7 ? 'danger' : 'warning');
        } else {
            $this->clear($hostel->id, 'subscription_due', 'sub');
        }

        // Students leaving within 7 days
        $leaving = Student::leavingWithin(7)->count();
        $this->toggle($leaving > 0, $hostel->id, 'leaving_soon', 'leaving7',
            'Students leaving soon', "{$leaving} student(s) leaving within 7 days.", 'warning');

        // Pending fees (fee + rent + other invoices, excluding AC)
        $feeDue = (float) Invoice::where('type', '!=', 'ac')->where('status', '!=', 'paid')->sum('balance');
        $this->toggle($feeDue > 0, $hostel->id, 'fee_pending', 'fees',
            'Pending fees', hostelease_money($feeDue).' outstanding across students.', 'warning');

        // Pending AC bills
        $acDue = (float) Invoice::where('type', 'ac')->where('status', '!=', 'paid')->sum('balance');
        $this->toggle($acDue > 0, $hostel->id, 'ac_pending', 'ac',
            'Pending AC bills', hostelease_money($acDue).' AC dues outstanding.', 'info');

        // Document expiry within 30 days
        $expiring = StudentDocument::expiringWithin(30)->count();
        $this->toggle($expiring > 0, $hostel->id, 'doc_expiry', 'docs',
            'Documents expiring', "{$expiring} document(s) expiring within 30 days.", 'warning');

        // Payment promises that have come due (promise_date reached, still unpaid)
        $promiseDue = $this->promisesDueCount();
        $this->toggle($promiseDue > 0, $hostel->id, 'promise_due', 'promise',
            'Payment promises due', "{$promiseDue} student(s) promised to pay by today or earlier.", 'danger');

        Tenant::clear();
    }

    /**
     * Build renewal alerts for the Super Admin (hostel_id = null feed).
     */
    public function generateForSuperAdmin(): void
    {
        foreach (Hostel::expiringWithin(30)->get() as $hostel) {
            $days = $hostel->daysUntilExpiry();
            $this->push(null, 'renewal_due', 'hostel:'.$hostel->id,
                "Renewal due — {$hostel->name}",
                "Subscription "
                .($days < 0 ? 'expired' : "expires in {$days} day(s)").
                ' ('.optional($hostel->subscription_end)->format('d M Y').').',
                $days <= 7 ? 'danger' : 'warning');
        }
    }

    /**
     * Count obligations whose promise_date has arrived and are still unpaid (current tenant).
     */
    protected function promisesDueCount(): int
    {
        $today = now()->toDateString();

        return Invoice::whereNotNull('promise_date')
            ->whereDate('promise_date', '<=', $today)
            ->where('status', '!=', 'paid')
            ->count();
    }

    protected function toggle(bool $condition, ?int $hostelId, string $type, string $sig, string $title, string $message, string $level): void
    {
        $condition
            ? $this->push($hostelId, $type, $sig, $title, $message, $level)
            : $this->clear($hostelId, $type, $sig);
    }
}

