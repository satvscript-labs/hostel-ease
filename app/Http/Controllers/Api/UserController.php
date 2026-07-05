<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Sub-user management for the hostel owner: create staff logins (Manager,
 * Accountant, Warden, Viewer) scoped to the owner's active hostel.
 */
class UserController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $hostelId = Tenant::id();
        // Sub-users whose primary hostel is the active one, excluding the owner.
        $users = User::where('hostel_id', $hostelId)
            ->where('id', '!=', $request->user()->id)
            ->whereIn('role', array_keys(config('hsms.staff_roles')))
            ->with(['role', 'branch'])
            ->orderBy('name')->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'mobile' => $u->mobile,
                'role' => $u->role,
                'role_label' => config('hsms.staff_roles.'.$u->role, $u->role),
                'role_id' => $u->role_id,
                'role_name' => $u->role()->first()?->display_name,
                'branch_id' => $u->branch_id,
                'branch_name' => $u->branch?->name,
                'is_active' => (bool) $u->is_active,
            ]);

        $roles = Role::whereIn('name', array_keys(config('hsms.staff_roles')))->get();
        $branches = Branch::where('hostel_id', $hostelId)->where('is_active', true)->get(['id', 'name']);

        return response()->json([
            'users' => $users,
            'roles' => config('hsms.staff_roles'),
            'all_roles' => $roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'display_name' => $r->display_name]),
            'branches' => $branches,
            'role_access' => collect(config('hsms.staff_roles'))->keys()->mapWithKeys(fn ($r) => [
                $r => config('hsms.role_access.'.$r),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'regex:/^\+91\d{10}$|^\d{10}$/', Rule::unique('users', 'mobile')->whereNull('deleted_at')],
            'role' => ['required', Rule::in(array_keys(config('hsms.staff_roles')))],
            'role_id' => ['nullable', 'exists:roles,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'password' => ['nullable', 'string', 'min:6', 'max:60'],
        ]);

        // Normalize mobile to +91 format
        $digits = substr(preg_replace('/\D+/', '', $data['mobile']), -10);
        $mobile = '+91' . $digits;

        $password = $data['password'] ?? (Str::upper(Str::random(3)).random_int(10000, 99999));
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
        // Grant branch access to the active hostel.
        $user->hostels()->syncWithoutDetaching([Tenant::id()]);
        $this->logger->log('user.create', "Added {$data['role']} {$user->name}", $user);

        return response()->json([
            'message' => 'User created.',
            'id' => $user->id,
            'mobile' => $user->mobile,
            'password' => $password, // shown once
        ], 201);
    }

    public function update(Request $request, int $user): JsonResponse
    {
        $model = $this->find($user);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'role' => ['required', Rule::in(array_keys(config('hsms.staff_roles')))],
            'role_id' => ['nullable', 'exists:roles,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $model->update([
            'name' => $data['name'],
            'role' => $data['role'],
            'role_id' => $data['role_id'],
            'branch_id' => $data['branch_id'],
            'is_active' => $request->boolean('is_active', $model->is_active),
        ]);
        $this->logger->log('user.update', "Updated user {$model->name}", $model);

        return response()->json(['message' => 'User updated.']);
    }

    public function resetPassword(int $user): JsonResponse
    {
        $model = $this->find($user);
        $password = Str::upper(Str::random(3)).random_int(10000, 99999);
        $model->update(['password' => Hash::make($password)]);
        $this->logger->log('user.reset', "Reset password for {$model->name}", $model);

        return response()->json(['message' => 'Password reset.', 'mobile' => $model->mobile, 'password' => $password]);
    }

    public function destroy(int $user): JsonResponse
    {
        $model = $this->find($user);
        $this->logger->log('user.delete', "Removed user {$model->name}", $model);
        $model->delete();

        return response()->json(['message' => 'User removed.']);
    }

    /** Resolve a sub-user that belongs to the active hostel + is a staff role. */
    protected function find(int $id): User
    {
        return User::where('hostel_id', Tenant::id())
            ->whereIn('role', array_keys(config('hsms.staff_roles')))
            ->findOrFail($id);
    }
}
