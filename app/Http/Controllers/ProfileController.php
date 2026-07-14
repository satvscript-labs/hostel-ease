<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function edit(): View
    {
        return view('profile.password', ['user' => Auth::user()]);
    }

    /**
     * Basic profile info (name/email). The mobile is the LOGIN username and the
     * identity linking an owner to their branches — it is only changed via the
     * Super Admin's hostel-edit flow, which syncs the login and every sibling
     * branch together (P4 item 14).
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
        ]);

        $request->user()->update($data);
        $this->logger->log('profile.update', 'Updated profile info');

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
        ]);

        $user = Auth::user();
        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        $this->logger->log('password.change', 'Changed account password');

        return back()->with('success', 'Password changed successfully.');
    }
}
