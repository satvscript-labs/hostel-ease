<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Hostel;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\HostelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __construct(
        protected HostelService $hostels,
        protected ActivityLogger $logger,
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        // Normalise to the +91 login form BEFORE validating uniqueness — a bare
        // 10-digit mobile here created co-admin logins that could never sign in
        // (login normalises to +91...) and dodged the unique check against
        // existing +91 rows (P4 item 14).
        if ($request->filled('mobile')) {
            $request->merge(['mobile' => '+91'.substr(preg_replace('/\D+/', '', $request->input('mobile')), -10)]);
        }

        $data = $request->validate([
            'hostel_id' => ['required', 'exists:hostels,id'],
            'name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'regex:/^\+91\d{10}$/', Rule::unique('users', 'mobile')->whereNull('deleted_at')],
            'email' => ['nullable', 'email', 'max:150'],
            'branches' => ['nullable', 'array'],
            'branches.*' => ['integer', 'exists:hostels,id'],
        ], [
            'mobile.regex' => 'Enter a valid 10-digit mobile number.',
            'mobile.unique' => 'This mobile number is already used by another login.',
        ]);

        $password = str(str()->random(4))->upper()->toString() . random_int(1000, 9999);

        $admin = User::create([
            'hostel_id' => $data['hostel_id'],
            'name' => $data['name'],
            'mobile' => $data['mobile'],
            'email' => $data['email'] ?? null,
            'password' => Hash::make($password),
            'role' => 'hostel_admin',
            'is_active' => true,
        ]);

        $ids = collect($data['branches'] ?? [])->push($data['hostel_id'])->filter()->unique()->all();
        $admin->hostels()->sync($ids);
        
        $this->logger->log('admin.create', "Added admin {$admin->name}", $admin);

        return back()
            ->with('credentials', ['mobile' => $admin->mobile, 'password' => $password])
            ->with('success', 'Admin created. Share the generated credentials below.');
    }

    public function toggle(User $admin): RedirectResponse
    {
        abort_unless($admin->isHostelAdmin(), 403);

        // Invariant (P4 item 14): every branch keeps a working owner login.
        // The owner can never be disabled — suspend the ACCOUNT instead.
        if ($admin->is_active && $admin->isOwner()) {
            return back()->with('error', "{$admin->name} is the owner login for their branches and cannot be disabled. Suspend the account from the customer's Account 360 instead.");
        }

        $admin->update(['is_active' => ! $admin->is_active]);
        $this->logger->log('admin.toggle', ($admin->is_active ? 'Enabled' : 'Disabled')." admin {$admin->name}", $admin);

        return back()->with('success', 'Admin '.($admin->is_active ? 'enabled' : 'disabled').'.');
    }

    public function resetPassword(User $admin): RedirectResponse
    {
        abort_unless($admin->isHostelAdmin(), 403);

        $password = $this->hostels->resetPassword($admin);
        $this->logger->log('admin.reset', "Reset password for {$admin->name}", $admin);

        return back()
            ->with('credentials', ['mobile' => $admin->mobile, 'password' => $password])
            ->with('success', 'Password reset. Share the new credentials below.');
    }

    /**
     * Set which branches (hostels) this admin can access and switch between.
     */
    public function branches(Request $request, User $admin): RedirectResponse
    {
        abort_unless($admin->isHostelAdmin(), 403);

        $data = $request->validate([
            'hostels' => ['array'],
            'hostels.*' => ['integer', 'exists:hostels,id'],
        ]);

        // Always keep the admin's primary hostel AND every branch they OWN in
        // the set — an owner can never lose access to their own branches.
        $ids = collect($data['hostels'] ?? [])
            ->push($admin->hostel_id)
            ->merge($admin->ownedHostels()->pluck('id'))
            ->filter()->unique()->values()->all();
        $admin->hostels()->sync($ids);

        $this->logger->log('admin.branches', "Updated branch access for {$admin->name}", $admin);

        return back()->with('success', 'Branch access updated.');
    }

    /**
     * Login history + activity logs feed (Super Admin oversight).
     */
    public function activity(Request $request): View
    {
        $logs = ActivityLog::with(['user', 'hostel'])
            ->when($request->filled('hostel'), fn ($q) => $q->where('hostel_id', $request->integer('hostel')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->action.'%'))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $hostels = Hostel::orderBy('name')->get(['id', 'name']);

        return view('superadmin.activity', compact('logs', 'hostels'));
    }
}
