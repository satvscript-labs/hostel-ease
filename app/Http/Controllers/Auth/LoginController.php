<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'mobile' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Login username is the mobile number (normalize to +91 format for db lookup).
        $mobile = preg_replace('/\D+/', '', $credentials['mobile']);
        $mobile = substr($mobile, -10);
        $mobile = '+91' . $mobile;

        if (! Auth::attempt(['mobile' => $mobile, 'password' => $credentials['password']], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'mobile' => __('These credentials do not match our records, or your account is inactive.'),
            ]);
        }

        if (! Auth::user()?->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'mobile' => __('Your account has been deactivated. Please contact support.'),
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->logger->log('login', 'User logged in');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->logger->log('logout', 'User logged out');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
