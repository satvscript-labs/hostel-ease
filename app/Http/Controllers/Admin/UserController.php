<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }



    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'regex:/^\+91\d{10}$|^\d{10}$/', Rule::unique('users', 'mobile')->whereNull('deleted_at')],
            'role' => ['required', Rule::in(array_keys(config('hostelease.staff_roles')))],
            'branches' => ['required', 'array', 'min:1'],
            'branches.*' => ['integer', 'exists:hostels,id'],
        ]);

        // Only branches THIS owner can access may be assigned (P4 item 15 —
        // previously any hostel id passed validation).
        $branchIds = $this->allowedBranchIds($request, $data['branches']);
        abort_unless(count($branchIds) > 0, 422);

        $digits = substr(preg_replace('/\D+/', '', $data['mobile']), -10);
        $mobile = '+91' . $digits;

        $password = Str::upper(Str::random(3)).random_int(10000, 99999);
        $user = User::create([
            'hostel_id' => Tenant::id(),
            'name' => $data['name'],
            'mobile' => $mobile,
            'password' => Hash::make($password),
            'role' => $data['role'],
            'is_active' => true,
        ]);

        $user->hostels()->sync($branchIds);
        $this->logger->log('user.create', "Added {$data['role']} {$user->name}", $user);

        return back()->with('active_tab', 'users')->with('credentials', ['mobile' => $user->mobile, 'password' => $password])
            ->with('success', 'User created — share the login below.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($user);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'role' => ['required', Rule::in(array_keys(config('hostelease.staff_roles')))],
            'branches' => ['required', 'array', 'min:1'],
            'branches.*' => ['integer', 'exists:hostels,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $user->update([
            'name' => $data['name'],
            'role' => $data['role'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $branchIds = $this->allowedBranchIds($request, $data['branches']);
        abort_unless(count($branchIds) > 0, 422);
        $user->hostels()->sync($branchIds);

        return back()->with('active_tab', 'users')->with('success', 'User updated.');
    }

    /** Enable/disable a login. Co-admins allowed (operational control); the
     *  account owner never (P4 item 14/16). */
    public function toggle(User $user): RedirectResponse
    {
        $this->authorizeManage($user, adminAllowed: true);
        $user->update(['is_active' => ! $user->is_active]);
        $this->logger->log('user.toggle', ($user->is_active ? 'Enabled' : 'Disabled')." {$user->name}", $user);

        return back()->with('active_tab', 'users')->with('success', 'User '.($user->is_active ? 'enabled' : 'disabled').'.');
    }

    public function resetPassword(User $user): RedirectResponse
    {
        // Co-admins get operational control (reset), but never the owner.
        $this->authorizeManage($user, adminAllowed: true);
        $password = Str::upper(Str::random(3)).random_int(10000, 99999);
        $user->update(['password' => Hash::make($password)]);

        return back()->with('active_tab', 'users')->with('credentials', ['mobile' => $user->mobile, 'password' => $password])
            ->with('success', 'Password reset — share the new login below.');
    }

    public function destroy(User $user): RedirectResponse
    {
        // Deleting is staff-only — co-admins are removed via the Super Admin.
        $this->authorizeManage($user);
        $user->delete();

        return back()->with('active_tab', 'users')->with('success', 'User removed.');
    }

    /**
     * Manageability from the owner panel (P4 item 16):
     *  - the account OWNER is never manageable here (super-admin territory),
     *  - the target must share a branch with the acting admin (item-14 access),
     *  - staff are always manageable; a co-admin (hostel_admin, non-owner) only
     *    when $adminAllowed — used for the read-only-ish disable/reset actions,
     *    never edit/delete/role-change.
     */
    protected function authorizeManage(User $user, bool $adminAllowed = false): void
    {
        abort_if($user->isOwner(), 403);

        $shared = count(array_intersect(auth()->user()->accessibleHostelIds(), $user->accessibleHostelIds())) > 0;
        abort_unless($shared, 403);

        $isStaff = array_key_exists($user->role, config('hostelease.staff_roles'));
        abort_unless($isStaff || ($adminAllowed && $user->isHostelAdmin()), 403);
    }

    /** Intersect requested branch ids with what the acting owner can access. */
    protected function allowedBranchIds(Request $request, array $requested): array
    {
        return array_values(array_intersect(
            array_map('intval', $requested),
            $request->user()->accessibleHostelIds(),
        ));
    }
}

