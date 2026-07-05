<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\SubscriptionBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionBillingService $billing,
        protected ActivityLogger $logger,
    ) {
    }

    public function index(Request $request): View
    {
        // Per-account (owner) billing overview. A "branch" is a hostel; an
        // account is a hostel_admin owner and every branch they hold.
        $accounts = User::where('role', 'hostel_admin')
            ->orderBy('name')
            ->get()
            ->map(function (User $owner) {
                $branches = $this->billing->branchCount($owner);
                $free = $this->billing->freeBranches($branches);
                $payable = max(0, $branches - $free);
                $end = $this->billing->currentEnd($owner);

                return [
                    'owner' => $owner,
                    'branches' => $branches,
                    'free' => $free,
                    'payable' => $payable,
                    'yearly' => $payable * $this->billing->unitPrice('yearly'),
                    'monthly' => $payable * $this->billing->unitPrice('monthly'),
                    'end' => $end,
                    'active' => $end && ! $end->isPast(),
                ];
            })
            ->values();

        $subscriptions = Subscription::with('hostel')
            ->when($request->filled('status'), fn ($q) => $q->where('payment_status', $request->status))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $summary = [
            'total' => (float) $subscriptions->where('payment_status', 'paid')->sum('amount'),
            'pending' => (float) $subscriptions->where('payment_status', 'pending')->sum('amount'),
            'active_accounts' => $accounts->where('active', true)->count(),
            'expired_accounts' => $accounts->where('active', false)->count(),
        ];

        // Flat map (owner_id => pricing) for the modal's auto-calc JavaScript.
        $accountsJson = $accounts->mapWithKeys(fn ($a) => [
            $a['owner']->id => [
                'yearly' => $a['yearly'],
                'monthly' => $a['monthly'],
                'payable' => $a['payable'],
                'branches' => $a['branches'],
            ],
        ]);

        // Flat map (subscription id => fields) for the edit modal's JavaScript.
        $subsJson = $subscriptions->mapWithKeys(fn ($s) => [
            $s->id => [
                'plan' => $s->plan,
                'amount' => $s->amount,
                'start_date' => $s->start_date->format('Y-m-d'),
                'end_date' => $s->end_date->format('Y-m-d'),
                'payment_status' => $s->payment_status,
                'payment_method' => $s->payment_method,
                'transaction_number' => $s->transaction_number,
                'remarks' => $s->remarks,
            ],
        ]);

        return view('superadmin.subscriptions.index', compact('accounts', 'subscriptions', 'summary', 'accountsJson', 'subsJson'));
    }

    /** Record an offline (cash/UPI/cheque) renewal for a whole account. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'owner_id' => ['required', 'exists:users,id'],
            'period' => ['required', Rule::in(['yearly', 'monthly'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_status' => ['required', Rule::in(['paid', 'pending', 'failed'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'upi', 'cheque', 'rtgs', 'online'])],
            'transaction_number' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $owner = User::where('role', 'hostel_admin')->findOrFail($data['owner_id']);

        $subscription = $this->billing->renewOwner($owner, $data['period'], [
            'amount' => $data['amount'],
            'payment_status' => $data['payment_status'],
            'payment_method' => $data['payment_method'] ?? null,
            'transaction_number' => $data['transaction_number'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]);

        $this->logger->log(
            'subscription.create',
            "Account renewal for {$owner->name} ({$data['period']}) — ".hsms_money($data['amount']),
            $subscription,
        );

        $verb = $data['payment_status'] === 'paid' ? 'recorded and all branches extended' : 'recorded';

        return back()->with('success', "Subscription {$verb} — {$owner->name} until {$subscription->end_date->format('d M Y')}.");
    }

    /** Edit an existing subscription record (amount, method, dates, status…). */
    public function update(Request $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(['yearly', 'monthly'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_status' => ['required', Rule::in(['paid', 'pending', 'failed'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'upi', 'cheque', 'rtgs', 'online'])],
            'transaction_number' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $subscription->update($data);

        // Accepting/keeping it paid extends the owner's branches to this date.
        if ($data['payment_status'] === 'paid') {
            $this->billing->syncBranchesToSubscription($subscription->fresh());
        }

        $this->logger->log('subscription.update', "Edited subscription #{$subscription->id} ({$data['payment_status']})", $subscription);

        return back()->with('success', 'Subscription record updated.');
    }

    /** One-click accept a pending offline payment: mark paid + extend branches. */
    public function accept(Subscription $subscription): RedirectResponse
    {
        $subscription->update(['payment_status' => 'paid']);
        $this->billing->syncBranchesToSubscription($subscription->fresh());

        $owner = $this->billing->ownerFor($subscription);
        $this->logger->log('subscription.paid', 'Accepted payment '.hsms_money($subscription->amount).($owner ? " · {$owner->name}" : ''), $subscription);

        return back()->with('success', 'Payment accepted — all branches extended to '.$subscription->end_date->format('d M Y').'.');
    }

    public function destroy(Subscription $subscription): RedirectResponse
    {
        $subscription->delete();

        return back()->with('success', 'Subscription record removed.');
    }
}
