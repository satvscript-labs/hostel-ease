<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Token-based authentication for the Flutter mobile app.
 *
 * Uses Sanctum personal-access tokens (stateless) rather than the web's
 * session guard. Only active Hostel Admins may obtain a token here.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        // Normalize mobile to +91 format for db lookup.
        $mobile = preg_replace('/\D+/', '', $data['mobile']);
        $mobile = substr($mobile, -10);
        $mobile = '+91' . $mobile;

        $user = User::where('mobile', $mobile)->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'mobile' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->isHostelStaff()) {
            throw ValidationException::withMessages([
                'mobile' => ['This app is for hostel staff only.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'mobile' => ['Your account is inactive. Contact the administrator.'],
            ]);
        }

        // One token per device — revoke any previous token of the same name.
        $device = $data['device_name'] ?? 'mobile';
        $user->tokens()->where('name', $device)->delete();
        $token = $user->createToken($device)->plainTextToken;

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Shared user representation, including the branches they can switch between.
     */
    protected function userPayload(User $user): array
    {
        $user->loadMissing('hostels', 'hostel');

        $branches = $user->hostels->map(fn ($h) => [
            'id' => $h->id,
            'name' => $h->name,
            'is_primary' => $h->id === $user->hostel_id,
        ]);

        // Ensure the primary hostel is always present in the list.
        if ($user->hostel && ! $branches->contains('id', $user->hostel_id)) {
            $branches->push([
                'id' => $user->hostel->id,
                'name' => $user->hostel->name,
                'is_primary' => true,
            ]);
        }

        $roleLabel = config('hostelease.roles.'.$user->role)
            ?? config('hostelease.staff_roles.'.$user->role)
            ?? ucfirst($user->role);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'mobile' => $user->mobile,
            'email' => $user->email,
            'role' => $user->role,
            'role_label' => $roleLabel,
            'is_owner' => $user->isHostelAdmin(),
            'areas' => $user->accessibleAreas(),
            'readonly' => $user->isReadonly(),
            'primary_hostel_id' => $user->hostel_id,
            'branches' => $branches->values(),
        ];
    }
}

