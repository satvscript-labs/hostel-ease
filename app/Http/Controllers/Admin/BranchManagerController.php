<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\Subscription;
use App\Services\ActivityLogger;
use App\Services\BranchBillingService;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Illuminate\Http\RedirectResponse;

/**
 * Branch Manager & Subscription Controller.
 * Handles the "My Branches" hub, adding new branches, and per-branch subscriptions.
 */
class BranchManagerController extends Controller
{
    public function __construct(
        protected BranchBillingService $billing,
        protected RazorpayService $razorpay,
        protected ActivityLogger $logger,
    ) {
    }

    public function index(Request $request): View
    {
        $owner = $request->user();
        
        // Load branches explicitly belonging to this user
        // Using accessibleHostelIds to ensure we catch primary and pivots
        $branches = Hostel::whereIn('id', $owner->accessibleHostelIds())->get();

        return view('admin.branches.index', [
            'owner' => $owner,
            'branches' => $branches,
            'razorpayEnabled' => $this->razorpay->isConfigured(),
            'monthlyPrice' => $this->billing->unitPrice('monthly'),
            'yearlyPrice' => $this->billing->unitPrice('yearly'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
        ]);

        $owner = $request->user();

        // Create the new Branch
        $branch = Hostel::create([
            'name' => $data['name'],
            'owner_name' => $owner->name,
            'mobile' => $owner->mobile,
            'email' => $owner->email,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'status' => 'active',
            'subscription_start' => now(),
            'subscription_end' => now()->addDays(14),
        ]);

        // Attach to user
        if ($owner->hostel_id !== null) {
            $owner->hostels()->attach($branch->id);
        } else {
            $owner->update(['hostel_id' => $branch->id]);
        }

        $this->logger->log('branch.created', "New branch created: {$branch->name}");

        return redirect()->route('admin.branches.index')->with('success', 'Branch created successfully! Your 14-day free trial has started.');
    }

    public function createOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'period' => ['required', Rule::in(['yearly', 'monthly'])],
        ]);

        $owner = $request->user();
        
        if (! $owner->canAccessHostel((int) $data['branch_id'])) {
            return response()->json(['message' => 'Unauthorized branch access.'], 403);
        }

        $branch = Hostel::findOrFail($data['branch_id']);

        if (! $this->razorpay->isConfigured()) {
            return response()->json(['message' => 'Online payment is not available right now.'], 503);
        }

        $quote = $this->billing->quote($branch, $data['period']);

        if ($quote['amount_paise'] < 100) {
            return response()->json(['message' => 'There is nothing payable for this branch.'], 422);
        }

        try {
            $order = $this->razorpay->createOrder(
                $quote['amount_paise'],
                'hostelease_'.$branch->id.'_'.now()->timestamp,
                [
                    'branch_id' => (string) $branch->id,
                    'owner_id' => (string) $owner->id,
                    'period' => $data['period'],
                ],
            );
        } catch (RuntimeException $e) {
            $status = $e->getCode() === 401 ? 401 : 500;
            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json([
            'key' => $this->razorpay->keyId(),
            'order_id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'period' => $data['period'],
            'name' => config('app.name'),
            'description' => ucfirst($data['period']).' subscription for '.$branch->name,
            'prefill' => [
                'name' => $owner->name,
                'email' => $owner->email,
                'contact' => $owner->mobile,
            ],
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
            'period' => ['required', Rule::in(['yearly', 'monthly'])],
            'branch_id' => ['required', 'integer'],
        ]);

        $owner = $request->user();

        if (! $owner->canAccessHostel((int) $data['branch_id'])) {
            return response()->json(['message' => 'Unauthorized branch access.'], 403);
        }

        $branch = Hostel::findOrFail($data['branch_id']);

        if (! $this->razorpay->verifySignature($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'])) {
            return response()->json(['message' => 'Payment verification failed. You have not been charged.'], 400);
        }

        // Idempotent check
        if (! Subscription::where('transaction_number', $data['razorpay_payment_id'])->exists()) {
            $quote = $this->billing->quote($branch, $data['period']);

            $subscription = $this->billing->renewBranch($branch, $data['period'], [
                'amount' => $quote['amount'],
                'payment_status' => 'paid',
                'payment_method' => 'online',
                'transaction_number' => $data['razorpay_payment_id'],
                'remarks' => 'Razorpay order '.$data['razorpay_order_id'].' · Branch: '.$branch->name,
            ]);

            $this->logger->log(
                'subscription.paid',
                "Online {$data['period']} renewal for {$branch->name} — ".hostelease_money($quote['amount']),
                $subscription,
            );
        }

        return response()->json([
            'message' => 'Payment successful — branch subscription activated.',
            'redirect' => route('admin.branches.index'),
        ]);
    }
}
