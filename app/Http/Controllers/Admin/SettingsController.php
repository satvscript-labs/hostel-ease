<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\BranchBillingService;
use App\Services\RazorpayService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Owner Settings hub: Profile · Users & Roles · My Branches (P4 item 15 —
 * synced with the Super Admin systems: pivot-based access, explicit owner,
 * account-level anchor, and the owner_self_serve production lock).
 */
class SettingsController extends Controller
{
    public function __construct(
        protected BranchBillingService $billing,
        protected AccountBillingService $accountBilling,
        protected RazorpayService $razorpay,
    ) {
    }

    public function index(Request $request): View
    {
        // fresh(): the profile card reads columns (last_login_at, created_at)
        // that an in-memory auth model may not carry under strict mode.
        $owner = $request->user()->fresh() ?? $request->user();
        $accessibleIds = $owner->accessibleHostelIds();

        // The account owner(s) of these branches — hidden from the team list so
        // co-admins never see the owner and the owner never sees themselves here
        // (P4 item 16). Everyone else on the branches IS shown.
        $ownerIds = Hostel::whereIn('id', $accessibleIds)->pluck('owner_id')->filter()->unique()->all();
        $viewerIsOwner = in_array($owner->id, $ownerIds, true);

        // Every login on the owner's branches EXCEPT the account owner: co-admins
        // (hostel_admin, super-admin-granted) shown read-only + staff the owner
        // manages. Access is the item-14 pivot (or primary branch), so a member
        // created while another branch was active still appears.
        $users = User::with('hostels:id,name')
            ->where(function ($q) {
                $q->whereIn('role', array_keys(config('hostelease.staff_roles')))
                    ->orWhere('role', 'hostel_admin');
            })
            ->whereNotIn('id', $ownerIds)
            ->where(function ($q) use ($accessibleIds) {
                $q->whereIn('hostel_id', $accessibleIds)
                    ->orWhereHas('hostels', fn ($h) => $h->whereIn('hostels.id', $accessibleIds));
            })
            ->orderByRaw("CASE WHEN role = 'hostel_admin' THEN 0 ELSE 1 END") // admins first
            ->orderBy('name')
            ->get();

        $myBranches = Hostel::whereIn('id', $accessibleIds)->orderBy('name')->get();

        // Branch card micro-stats (W9). Raw DB, NOT the models: Bed/Student
        // carry the tenant global scope, which silently filters every count to
        // the ACTIVE branch — the other cards would all read 0.
        $bedStats = \Illuminate\Support\Facades\DB::table('beds')
            ->whereIn('hostel_id', $accessibleIds)
            ->selectRaw("hostel_id, COUNT(*) total, SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) occupied")
            ->groupBy('hostel_id')->get()->keyBy('hostel_id');
        $studentStats = \Illuminate\Support\Facades\DB::table('students')
            ->whereIn('hostel_id', $accessibleIds)->whereNull('deleted_at')
            ->where('status', 'active')
            ->selectRaw('hostel_id, COUNT(*) n')
            ->groupBy('hostel_id')->pluck('n', 'hostel_id');

        // Account context — the one anchor every branch renews against.
        // accountForViewer(), not accountFor(): $owner here is really the VIEWER
        // (see $viewerIsOwner above), and a co-admin must be shown the owner's
        // account rather than silently given one of their own.
        $account = $this->accountBilling->accountForViewer($owner);

        return view('admin.settings.index', [
            'owner' => $owner,
            'viewerIsOwner' => $viewerIsOwner,
            'users' => $users,
            'roles' => config('hostelease.staff_roles'),
            'roleAccess' => config('hostelease.role_access'),
            'userBranches' => $myBranches,
            'myBranches' => $myBranches,
            'bedStats' => $bedStats,
            'studentStats' => $studentStats,
            'account' => $account,
            'activeHostelId' => Tenant::id(),
            'razorpayEnabled' => $this->razorpay->isConfigured(),
            'selfServe' => (bool) config('hostelease.owner_self_serve'),
            'monthlyPrice' => $this->billing->unitPrice('monthly'),
            'yearlyPrice' => $this->billing->unitPrice('yearly'),
        ]);
    }
}
