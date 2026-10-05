<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Auth\SignupVerification;
use App\Services\Billing\AccountBillingService;
use App\Services\HostelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * Self-signup, in two steps: your details, then verify your email.
 *
 * Nothing is created until the email is verified (see SignupVerification for why).
 * Step 1 parks the details and sends a 6-digit code; step 2 checks it and only then
 * creates the owner, their first branch and the account's one free trial — in the
 * same order, through the same services, as before.
 */
class RegisterController extends Controller
{
    public function __construct(
        protected SignupVerification $verification,
        protected ActivityLogger $logger,
    ) {}

    /** Step 1 — your details. */
    public function show(): View
    {
        return view('auth.register');
    }

    /** Step 1 submitted: validate, park, send the code. */
    public function register(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'mobile' => preg_replace('/\D+/', '', (string) $request->input('mobile')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'hostel_name' => ['required', 'string', 'max:120'],
            // Indian mobile numbers: ten digits, starting 6–9. It is the login, so a
            // typo here locks the owner out of their own account.
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'email' => ['required', 'email:rfc', 'max:150'],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ], [
            'mobile.regex' => __('Enter a valid 10-digit Indian mobile number.'),
            'password.min' => __('Use at least 8 characters for your password.'),
        ]);

        $data['mobile'] = '+91'.$data['mobile'];
        $this->assertAvailable($data['mobile'], $data['email']);

        try {
            $this->verification->start($data);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['email' => $e->getMessage()]);
        }

        return redirect()->route('register.verify');
    }

    /** Step 2 — check your email. */
    public function showVerify(): View|RedirectResponse
    {
        $pending = $this->verification->pending();

        if (! $pending) {
            return redirect()->route('register');
        }

        return view('auth.register-verify', [
            'maskedEmail' => SignupVerification::maskEmail($pending['email']),
            'email' => $pending['email'],
            'resendIn' => $this->verification->secondsUntilResend($pending),
            'sendsLeft' => $this->verification->sendsLeft($pending),
            // LOCAL ONLY — set by SignupVerification when mail cannot leave this machine.
            'devCode' => app()->environment('local') ? session('signup.dev_code') : null,
        ]);
    }

    /** Step 2 submitted: check the code, then create everything. */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        try {
            $details = $this->verification->verify((string) $request->input('code'));
        } catch (RuntimeException $e) {
            // The sign-up may have been cancelled (too many tries, expired session):
            // then step 2 has nothing to show, so go back to the start.
            return ($this->verification->pending() ? back() : redirect()->route('register'))
                ->withErrors(['code' => $e->getMessage()]);
        }

        // Someone may have registered this mobile or email in the minutes between
        // step 1 and now. Checked again at the last moment, and the database's
        // unique indexes back it up below.
        try {
            $this->assertAvailable($details['mobile'], $details['email']);
        } catch (ValidationException $e) {
            $this->verification->forget();

            return redirect()->route('register')->withErrors($e->errors());
        }

        try {
            $user = $this->createAccount($details);
        } catch (Throwable $e) {
            Log::error('Self-signup failed after a verified email', ['mobile' => $details['mobile'], 'error' => $e->getMessage()]);

            return back()->withErrors(['code' => __('Your email is verified, but we could not finish creating your account. Please try again in a moment.')]);
        }

        $this->verification->forget();

        Auth::login($user);
        $request->session()->regenerate();

        $this->logger->log('register', 'Owner signed up and verified their email');

        return redirect()->route('dashboard')->with('success', __('Welcome to :app! Your 14-day free trial has started.', ['app' => config('app.name', 'HostelEase')]));
    }

    /** Send another code. */
    public function resend(): RedirectResponse
    {
        try {
            $this->verification->resend();
        } catch (RuntimeException $e) {
            return ($this->verification->pending() ? back() : redirect()->route('register'))
                ->withErrors(['code' => $e->getMessage()]);
        }

        return back()->with('status', __('We sent a new code.'));
    }

    /** Typed the wrong email: change it and send a code there. */
    public function changeEmail(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:150']]);

        if (User::where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => __('An account already uses this email. Log in instead, or use a different email.')])->withInput();
        }

        try {
            $this->verification->changeEmail($data['email']);
        } catch (RuntimeException $e) {
            return ($this->verification->pending() ? back() : redirect()->route('register'))
                ->withErrors(['email' => $e->getMessage()])->withInput();
        }

        return redirect()->route('register.verify')->with('status', __('We sent a code to your new email.'));
    }

    // -----------------------------------------------------------------

    /** @throws ValidationException */
    protected function assertAvailable(string $mobile, string $email): void
    {
        $errors = [];

        if (User::where('mobile', $mobile)->exists()) {
            $errors['mobile'] = __('This mobile number already has an account. Log in instead.');
        }
        if (User::where('email', $email)->exists()) {
            $errors['email'] = __('An account already uses this email. Log in instead, or use a different email.');
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Create the owner, their first branch and the account's one free trial.
     * Unchanged in substance from the single-step version — only now it runs after
     * the email is proven, and records that proof.
     */
    protected function createAccount(array $details): User
    {
        return DB::transaction(function () use ($details) {
            // 1. The branch. NO COVERAGE IS WRITTEN HERE (S0/S1): the ₹0 trial order
            //    in step 4 is what grants the window, through the biller.
            $hostel = Hostel::create([
                'name' => $details['hostel_name'],
                'owner_name' => $details['name'],
                'mobile' => $details['mobile'],
                'email' => $details['email'],
                'status' => 'active',
            ]);

            // 2. The owner — with the email they have just proven they can receive at.
            $user = User::create([
                'name' => $details['name'],
                'mobile' => $details['mobile'],
                'email' => $details['email'],
                // Already a bcrypt hash (SignupVerification hashed it at step 1). The
                // model's `hashed` cast leaves an existing hash as it is.
                'password' => $details['password_hash'],
                'role' => 'hostel_admin',
                'hostel_id' => $hostel->id,
                'is_active' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            // 3. Full provisioning (H3): pivot access, owner FK, payment modes.
            $user->hostels()->attach($hostel->id);
            $hostel->update(['owner_id' => $user->id]);
            app(HostelService::class)->seedPaymentModes($hostel);

            // 4. The account's one free trial, through the biller (S1; one trial per
            //    account — a brand-new owner's first branch always qualifies).
            app(AccountBillingService::class)->recordBranchRenewal($hostel->fresh(), 'trial', [
                'payment_status' => 'paid',
                'payment_method' => null,
                'remarks' => 'Self-signup free trial',
            ]);

            return $user;
        });
    }
}
