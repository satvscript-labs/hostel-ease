<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\Auth\SignIn;
use App\Services\Auth\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        protected ActivityLogger $logger,
        protected TwoFactor $twoFactor,
        protected SignIn $signIn,
    ) {}

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

        // The password is CHECKED here, not logged in: with two-factor on, the session
        // is created only after the second step (S7.1).
        if (Auth::validate(['mobile' => $mobile, 'password' => $credentials['password'], 'is_active' => true])) {
            /** @var \App\Models\User $user */
            $user = Auth::getLastAttempted();
            $remember = $request->boolean('remember');

            // Auth::attempt() used to upgrade an old password hash on sign-in; validate()
            // does not, so it is done here (S7.1 audit).
            if (method_exists(Auth::getProvider(), 'rehashPasswordIfRequired')) {
                Auth::getProvider()->rehashPasswordIfRequired($user, ['password' => $credentials['password']]);
            }

            if ($this->twoFactor->enabled($user) && ! $this->twoFactor->isTrusted($user, $request)) {
                $this->twoFactor->startLogin($user, $remember);

                return redirect()->route('two-factor.challenge');
            }

            return $this->signIn->complete($request, $user, $remember, $this->twoFactor->enabled($user) ? 'trusted device' : 'password');
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
