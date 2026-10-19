<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\TrustedDevice;
use App\Services\ActivityLogger;
use App\Services\Auth\Totp;
use App\Services\Auth\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * The Security page (S7.1) — every role has one: password, two-factor sign-in,
 * trusted devices, signing out elsewhere, recent sign-ins. Anything that weakens or
 * re-keys sign-in asks for the password again, so a session left open on a shared
 * computer cannot lock its owner out or switch two-factor off.
 */
class SecurityController extends Controller
{
    public function __construct(protected TwoFactor $twoFactor, protected ActivityLogger $logger) {}

    public function show(Request $request): View
    {
        $user = $request->user()->refresh();
        $setup = $this->twoFactor->setup($user);
        $secret = $this->twoFactor->setupSecret($user);

        return view('profile.security', [
            'user' => $user,
            'enabled' => $this->twoFactor->enabled($user),
            'setup' => $setup,
            'secret' => $secret,
            'qr' => $secret ? (string) QrCode::format('svg')->size(184)->margin(0)
                ->generate(Totp::uri($secret, hostelease_phone($user->mobile), config('app.name', 'HostelEase'))) : null,
            'codesLeft' => $this->twoFactor->remainingRecoveryCodes($user),
            'resendIn' => $this->twoFactor->secondsUntilResend($setup),
            'devCode' => app()->environment('local') ? $request->session()->get('auth.2fa.dev_code') : null,
            'devices' => TrustedDevice::where('user_id', $user->id)->where('expires_at', '>', now())->latest('last_used_at')->get(),
            'currentDeviceHash' => $request->cookie(TwoFactor::COOKIE) ? hash('sha256', (string) $request->cookie(TwoFactor::COOKIE)) : null,
            'otherSessions' => DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->count(),
            'signIns' => ActivityLog::where('user_id', $user->id)->where('action', 'login')->latest('id')->limit(5)->get(),
        ]);
    }

    public function beginApp(Request $request): RedirectResponse
    {
        $this->confirmPassword($request);

        return $this->attempt(fn () => $this->twoFactor->beginApp($request->user()));
    }

    public function beginEmail(Request $request): RedirectResponse
    {
        $this->confirmPassword($request);

        return $this->attempt(fn () => $this->twoFactor->beginEmail($request->user()));
    }

    public function resendSetupCode(Request $request): RedirectResponse
    {
        return $this->attempt(fn () => $this->twoFactor->resendSetupCode($request->user()), __('We sent a new code.'));
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);

        try {
            $codes = $this->twoFactor->confirm($request->user(), (string) $request->input('code'));
        } catch (RuntimeException $e) {
            return $this->back()->withErrors(['code' => $e->getMessage()], 'twoFactor');
        }
        $this->logger->log('security.2fa_on', 'Turned on two-factor sign-in ('.$request->user()->two_factor_method.')');

        // Devices already signed in got there on the password alone: they sign in
        // again, with a code (S7.1 audit). This one stays.
        self::endOtherSessions($request->user(), $request->session()->getId());

        return $this->back()->with('recovery_codes', $codes)->with('success', __('Two-factor sign-in is on. Your other devices were signed out.'));
    }

    public function cancel(): RedirectResponse
    {
        $this->twoFactor->cancelSetup();

        return $this->back();
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $this->confirmPassword($request);

        try {
            $codes = $this->twoFactor->regenerateRecoveryCodes($request->user());
        } catch (RuntimeException $e) {
            return $this->back()->with('error', $e->getMessage());
        }
        $this->logger->log('security.recovery_codes', 'Made new recovery codes');

        return $this->back()->with('recovery_codes', $codes)->with('success', __('New recovery codes made. The old ones no longer work.'));
    }

    public function disable(Request $request): RedirectResponse
    {
        $this->confirmPassword($request);
        $this->twoFactor->disable($request->user());
        $this->logger->log('security.2fa_off', 'Turned off two-factor sign-in');

        return $this->back()->with('success', __('Two-factor sign-in is off.'));
    }

    public function forgetDevice(Request $request, int $device): RedirectResponse
    {
        TrustedDevice::where('user_id', $request->user()->id)->whereKey($device)->delete();

        return $this->back()->with('success', __('That device will ask for a code next time.'));
    }

    /**
     * Sign out everywhere else: other sessions end, "keep me signed in" cookies stop
     * working, and every trusted device asks for a code again.
     */
    public function logoutOthers(Request $request): RedirectResponse
    {
        $this->confirmPassword($request);
        $user = $request->user();

        self::endOtherSessions($user, $request->session()->getId());
        $this->twoFactor->forgetDevices($user);
        $this->logger->log('security.logout_others', 'Signed out of all other devices');

        return $this->back()->with('success', __('You are signed out on every other device.'));
    }

    /** Shared by "sign out others", a password change and an admin reset. */
    public static function endOtherSessions(\App\Models\User $user, ?string $keepSessionId = null): void
    {
        DB::table('sessions')->where('user_id', $user->id)
            ->when($keepSessionId, fn ($q) => $q->where('id', '!=', $keepSessionId))->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }

    // ── internals ────────────────────────────────────────────────────────

    protected function confirmPassword(Request $request): void
    {
        $request->validateWithBag('twoFactor', ['password' => ['required', 'current_password']], [
            'password.current_password' => __('That password is not right.'),
        ]);
    }

    protected function attempt(\Closure $fn, ?string $ok = null): RedirectResponse
    {
        try {
            $fn();
        } catch (RuntimeException $e) {
            return $this->back()->withErrors(['code' => $e->getMessage()], 'twoFactor');
        }

        return $ok ? $this->back()->with('status', $ok) : $this->back();
    }

    protected function back(): RedirectResponse
    {
        return redirect()->route('profile.security');
    }
}
