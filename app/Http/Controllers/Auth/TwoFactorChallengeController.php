<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\SignIn;
use App\Services\Auth\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * The second step of signing in (S7.1). Reached only after LoginController has checked
 * the password and parked the sign-in; nobody is logged in until verify() succeeds.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(protected TwoFactor $twoFactor, protected SignIn $signIn) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->twoFactor->pendingUser();
        if (! $user) {
            $this->twoFactor->clearLogin();

            return redirect()->route('login')->with('error', __('Your sign-in has expired. Enter your mobile and password again.'));
        }
        $pending = $this->twoFactor->pendingLogin();

        return view('auth.two-factor', [
            'method' => $user->two_factor_method,
            'maskedEmail' => self::mask((string) $user->email),
            'resendIn' => $this->twoFactor->secondsUntilResend($pending),
            'sendError' => $pending['send_error'] ?? null,
            'devCode' => app()->environment('local') ? $request->session()->get('auth.2fa.dev_code') : null,
            'recovery' => $request->boolean('recovery') || old('recovery'),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'recovery' => ['nullable', 'boolean'],
            'trust' => ['nullable', 'boolean'],
        ]);
        $recovery = (bool) ($data['recovery'] ?? false);

        try {
            [$user, $remember] = $this->twoFactor->verifyLogin($data['code'], $recovery);
        } catch (RuntimeException $e) {
            // An expired or abandoned sign-in goes back to the password; a wrong code stays.
            return $this->twoFactor->pendingLogin()
                ? back()->withErrors(['code' => $e->getMessage()])->withInput(['recovery' => $recovery ? 1 : 0])
                : redirect()->route('login')->with('error', $e->getMessage());
        }

        if (! empty($data['trust'])) {
            $this->twoFactor->trustDevice($user, $request);
        }

        return $this->signIn->complete($request, $user, $remember, $recovery ? 'recovery code' : 'two-factor');
    }

    public function resend(): RedirectResponse
    {
        try {
            $this->twoFactor->resendLoginCode();
        } catch (RuntimeException $e) {
            return $this->twoFactor->pendingLogin()
                ? back()->withErrors(['code' => $e->getMessage()])
                : redirect()->route('login')->with('error', $e->getMessage());
        }

        return back()->with('status', __('We sent a new code.'));
    }

    public function cancel(): RedirectResponse
    {
        $this->twoFactor->clearLogin();

        return redirect()->route('login');
    }

    /** r****@gmail.com — enough to recognise, not enough to harvest. */
    public static function mask(string $email): string
    {
        if (! str_contains($email, '@')) {
            return '';
        }
        [$name, $domain] = explode('@', $email, 2);

        return mb_substr($name, 0, 1).str_repeat('•', max(2, min(6, mb_strlen($name) - 1))).'@'.$domain;
    }
}
