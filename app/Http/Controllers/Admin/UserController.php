<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Role;
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

    public function index(): View
    {
        try {
            $users = User::where('hostel_id', Tenant::id())
                ->whereIn('role', array_keys(config('hsms.staff_roles')))
                ->orderBy('name')->get();
        } catch (\Exception $e) {
            $users = collect();
        }

        try {
            $roles = Role::all();
        } catch (\Exception $e) {
            $roles = collect();
        }

        try {
            $branches = Branch::where('hostel_id', Tenant::id())->where('is_active', true)->get();
        } catch (\Exception $e) {
            $branches = collect();
        }

        return view('admin.users.index', [
            'users' => $users,
            'roles' => config('hsms.staff_roles'),
            'access' => config('hsms.role_access'),
            'allRoles' => $roles,
            'branches' => $branches,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'regex:/^\+91\d{10}$|^\d{10}$/', Rule::unique('users', 'mobile')->whereNull('deleted_at')],
            'role' => ['required', Rule::in(array_keys(config('hsms.staff_roles')))],
            'role_id' => ['nullable', 'exists:roles,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $digits = substr(preg_replace('/\D+/', '', $data['mobile']), -10);
        $mobile = '+91' . $digits;

        $password = Str::upper(Str::random(3)).random_int(10000, 99999);
        $user = User::create([
            'hostel_id' => Tenant::id(),
            'name' => $data['name'],
            'mobile' => $mobile,
            'password' => Hash::make($password),
            'role' => $data['role'],
            'role_id' => $data['role_id'],
            'branch_id' => $data['branch_id'] ?? Branch::where('hostel_id', Tenant::id())->first()?->id,
            'is_active' => true,
        ]);
        $user->hostels()->syncWithoutDetaching([Tenant::id()]);
        $this->logger->log('user.create', "Added {$data['role']} {$user->name}", $user);

        return back()->with('credentials', ['mobile' => $user->mobile, 'password' => $password])
            ->with('success', 'User created — share the login below.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUser($user);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'role' => ['required', Rule::in(array_keys(config('hsms.staff_roles')))],
            'role_id' => ['nullable', 'exists:roles,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $user->update([
            'name' => $data['name'],
            'role' => $data['role'],
            'role_id' => $data['role_id'],
            'branch_id' => $data['branch_id'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'User updated.');
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $this->authorizeUser($user);
        $password = Str::upper(Str::random(3)).random_int(10000, 99999);
        $user->update(['password' => Hash::make($password)]);

        return back()->with('credentials', ['mobile' => $user->mobile, 'password' => $password])
            ->with('success', 'Password reset — share the new login below.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorizeUser($user);
        $user->delete();

        return back()->with('success', 'User removed.');
    }

    protected function authorizeUser(User $user): void
    {
        abort_unless(
            $user->hostel_id === Tenant::id() && array_key_exists($user->role, config('hsms.staff_roles')),
            403
        );
    }
}
