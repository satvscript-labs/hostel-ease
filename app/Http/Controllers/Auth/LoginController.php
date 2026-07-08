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

    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'mobile' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $mobile = preg_replace('/\D+/', '', $credentials['mobile']);
        $mobile = '+91' . substr($mobile, -10);

        if (Auth::attempt(['mobile' => $mobile, 'password' => $credentials['password'], 'is_active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();
            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ])->save();

            $this->logger->log('login', 'User logged in');

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'mobile' => __('These credentials do not match our records, or your account is inactive.'),
        ])->onlyInput('mobile');
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
