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

        // Keyed `credentials`, not `mobile`: a failed sign-in is about the pair, so
        // the page shows it above the form instead of blaming the mobile field. The
        // message deliberately does not say WHICH part was wrong — that would tell a
        // stranger whether a mobile number has an account.
        return back()->withErrors([
            'credentials' => __('That mobile number and password do not match an active account. Check both and try again.'),
        ])->onlyInput('mobile', 'remember');
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
