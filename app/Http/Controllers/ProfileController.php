<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\Auth\EmailVerification;
use RuntimeException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(protected ActivityLogger $logger, protected EmailVerification $emailVerification)
    {
    }

    public function edit(): View
    {
        return view('profile.password', ['user' => Auth::user()]);
    }

    /**
     * Profile name. The mobile is the LOGIN username and the identity linking an
     * owner to their branches — it is only changed via the Super Admin's hostel-edit
     * flow, which syncs the login and every sibling branch together (P4 item 14).
     *
     * The EMAIL is deliberately not saved here: it changes only through the code
     * flow below, so an address on the account has always been proven.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        $request->user()->update($data);
        $this->logger->log('profile.update', 'Updated profile info');

        return $this->backToProfile()->with('success', __('Profile updated.'));
    }

    /** Send a code to a new address — or to the current one, to verify it. */
    public function sendEmailCode(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:150']]);

        try {
            $this->emailVerification->start($request->user(), $data['email']);
        } catch (RuntimeException $e) {
            return $this->backToProfile()->withErrors(['email' => $e->getMessage()], 'emailFlow')->withInput();
        }

        return $this->backToProfile()->with('email_status', __('We sent a 6-digit code to :email.', ['email' => $data['email']]));
    }

    public function resendEmailCode(Request $request): RedirectResponse
    {
        try {
            $this->emailVerification->resend($request->user());
        } catch (RuntimeException $e) {
            return $this->backToProfile()->withErrors(['code' => $e->getMessage()], 'emailFlow');
        }

        return $this->backToProfile()->with('email_status', __('We sent a new code.'));
    }

    public function verifyEmailCode(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        try {
            $this->emailVerification->verify($request->user(), (string) $request->input('code'));
        } catch (RuntimeException $e) {
            return $this->backToProfile()->withErrors(['code' => $e->getMessage()], 'emailFlow');
        }

        $this->logger->log('profile.email', 'Verified email address');

        return $this->backToProfile()->with('success', __('Your email is verified.'));
    }

    protected function backToProfile(): RedirectResponse
    {
        return redirect()->route('admin.settings.index', ['tab' => 'profile']);
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
