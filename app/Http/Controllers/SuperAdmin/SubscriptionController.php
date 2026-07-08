<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\Subscription;
use App\Services\ActivityLogger;
use App\Services\BranchBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(
        protected BranchBillingService $billing,
        protected ActivityLogger $logger,
    ) {
    }

    public function index(Request $request): View
    {
        // List all branches for per-branch billing
        $branches = Hostel::orderBy('name')
            ->get()
            ->map(function (Hostel $branch) {
                $end = $branch->subscription_end;
                return [
                    'branch' => $branch,
                    'yearly' => $this->billing->unitPrice('yearly'),
                    'monthly' => $this->billing->unitPrice('monthly'),
                    'end' => $end,
                    'active' => $branch->isActive(),
                ];
            })
            ->values();

        $subscriptions = Subscription::with('hostel')
            ->when($request->filled('status'), fn ($q) => $q->where('payment_status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(15);

        $summary = [
            'total' => (float) Subscription::where('payment_status', 'paid')->sum('amount'),
            'pending' => (float) Subscription::where('payment_status', 'pending')->sum('amount'),
            'active_branches' => $branches->where('active', true)->count(),
            'expired_branches' => $branches->where('active', false)->count(),
        ];

        // Flat map (branch_id => pricing) for the modal's auto-calc JavaScript.
        $branchesJson = $branches->mapWithKeys(fn ($b) => [
            $b['branch']->id => [
                'yearly' => $b['yearly'],
                'monthly' => $b['monthly'],
            ],
        ]);

        // Flat map (subscription id => fields) for the edit modal's JavaScript.
        $subsJson = collect($subscriptions->items())->mapWithKeys(fn ($s) => [
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

        return view('superadmin.subscriptions.index', compact('branches', 'subscriptions', 'summary', 'branchesJson', 'subsJson'));
    }

    /** Record an offline (cash/UPI/cheque) renewal for a specific branch. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:hostels,id'],
            'period' => ['required', Rule::in(['yearly', 'monthly', 'trial'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_status' => ['required', Rule::in(['paid', 'pending', 'failed'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'upi', 'cheque', 'rtgs', 'online', 'comp'])],
            'transaction_number' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $branch = Hostel::findOrFail($data['branch_id']);

        $subscription = $this->billing->renewBranch($branch, $data['period'], [
            'amount' => $data['amount'],
            'payment_status' => $data['payment_status'],
            'payment_method' => $data['payment_method'] ?? null,
            'transaction_number' => $data['transaction_number'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]);

        $this->logger->log(
            'subscription.create',
            "Branch renewal for {$branch->name} ({$data['period']}) — ".hostelease_money($data['amount']),
            $subscription,
        );

        $verb = $data['payment_status'] === 'paid' ? 'recorded and branch activated' : 'recorded';

        return back()->with('success', "Subscription {$verb} — {$branch->name} valid until {$subscription->end_date->format('d M Y')}.");
    }

    /** Edit an existing subscription record (amount, method, dates, status…). */
    public function update(Request $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(['yearly', 'monthly', 'trial'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_status' => ['required', Rule::in(['paid', 'pending', 'failed'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'upi', 'cheque', 'rtgs', 'online', 'comp'])],
            'transaction_number' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $subscription->update($data);

        // Accepting/keeping it paid extends the branch to this date.
        if ($data['payment_status'] === 'paid') {
            $this->billing->syncBranchToSubscription($subscription->fresh());
        }

        $this->logger->log('subscription.update', "Edited subscription #{$subscription->id} ({$data['payment_status']})", $subscription);

        return back()->with('success', 'Subscription record updated.');
    }

    /** One-click accept a pending offline payment: mark paid + extend branch. */
    public function accept(Subscription $subscription): RedirectResponse
    {
        $subscription->update(['payment_status' => 'paid']);
        $this->billing->syncBranchToSubscription($subscription->fresh());

        $this->logger->log('subscription.paid', 'Accepted payment '.hostelease_money($subscription->amount)." · {$subscription->hostel->name}", $subscription);

        return back()->with('success', 'Payment accepted — branch extended to '.$subscription->end_date->format('d M Y').'.');
    }

    public function destroy(Subscription $subscription): RedirectResponse
    {
        $subscription->delete();

        return back()->with('success', 'Subscription record removed.');
    }
}
