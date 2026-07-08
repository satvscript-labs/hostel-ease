<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\User;
use App\Services\BranchBillingService;
use App\Services\RazorpayService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        protected BranchBillingService $billing,
        protected RazorpayService $razorpay,
    ) {
    }

    public function index(Request $request): View
    {
        $owner = $request->user();
        $hostelId = Tenant::id();

        // 1. Data for Users & Roles Tab (matching original UserController index)
        try {
            $users = User::with('hostels')
                ->where('hostel_id', $hostelId)
                ->whereIn('role', array_keys(config('hostelease.staff_roles')))
                ->orderBy('name')
                ->get();
        } catch (\Exception $e) {
            $users = collect();
        }

        try {
            $userBranches = Hostel::whereIn('id', $owner->accessibleHostelIds())
                ->orderBy('name')
                ->get();
        } catch (\Exception $e) {
            $userBranches = collect();
        }

        // 2. Data for Branches & Subscriptions Tab (matching original BranchManagerController index)
        try {
            $myBranches = Hostel::whereIn('id', $owner->accessibleHostelIds())->get();
        } catch (\Exception $e) {
            $myBranches = collect();
        }

        return view('admin.settings.index', [
            // Users data
            'users' => $users,
            'roles' => config('hostelease.staff_roles'),
            'userBranches' => $userBranches,

            // Branches data
            'owner' => $owner,
            'myBranches' => $myBranches,
            'razorpayEnabled' => $this->razorpay->isConfigured(),
            'monthlyPrice' => $this->billing->unitPrice('monthly'),
            'yearlyPrice' => $this->billing->unitPrice('yearly'),
        ]);
    }
}
