<?php

namespace App\Services\Auth;

use App\Mail\EmailCodeMail;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Prove an email address belongs to a signed-in person — the profile's "Verify" and
 * "Change email" flow. Same rules as sign-up (SignupVerification): a 6-digit code to
 * the address, valid 10 minutes, 5 wrong guesses cancel it, a new code at most once a
 * minute and 5 in all.
 *
 * The address on the user is NEVER touched until the code is right. A typo'd or
 * borrowed address therefore cannot replace a verified one, and an unverified address
 * can never be shown as verified: `email_verified_at` is written in the same save that
 * writes the address.
 */
class EmailVerification
{
    private const KEY = 'profile.email.pending';

    public function __construct(protected Session $session) {}

    /**
     * Park the address and send the first code.
     *
     * @throws RuntimeException with a message for the person
     */
    public function start(User $user, string $email): void
    {
        $email = mb_strtolower(trim($email));

        if ($user->email_verified_at && mb_strtolower((string) $user->email) === $email) {
            throw new RuntimeException(__('This is already your verified email.'));
        }

        $this->assertFree($user, $email);

        $this->issue($user, ['user_id' => $user->id, 'email' => $email, 'sends' => 0]);
    }

    /** @throws RuntimeException */
    public function resend(User $user): void
    {
        $pending = $this->pending($user) ?? throw new RuntimeException(__('That request has expired. Enter your email again.'));

        $wait = $this->secondsUntilResend($user, $pending);
        if ($wait > 0) {
            throw new RuntimeException(__('Please wait :s seconds before asking for another code.', ['s' => $wait]));
        }

        $this->issue($user, $pending);
    }

    /**
     * Check the code; on a match the address becomes the user's and verified.
     *
     * @throws RuntimeException with a message for the person
     */
    public function verify(User $user, string $code): void
    {
        $pending = $this->pending($user) ?? throw new RuntimeException(__('That request has expired. Enter your email again.'));

        if (now()->timestamp > $pending['expires_at']) {
            throw new RuntimeException(__('That code has expired. Send a new one and try again.'));
        }

        $code = preg_replace('/\D/', '', $code);

        if (! hash_equals($pending['code_hash'], $this->hash($code))) {
            $pending['attempts']++;

            if ($pending['attempts'] >= SignupVerification::MAX_ATTEMPTS) {
                $this->forget();

                throw new RuntimeException(__('Too many wrong codes. For your security we cancelled this — please start again.'));
            }

            $this->session->put(self::KEY, $pending);
            $left = SignupVerification::MAX_ATTEMPTS - $pending['attempts'];

            throw new RuntimeException(trans_choice('That code is not right. :n try left.|That code is not right. :n tries left.', $left, ['n' => $left]));
        }

        // Someone may have claimed the address in the minutes since the code was sent.
        try {
            $this->assertFree($user, $pending['email']);
        } catch (RuntimeException $e) {
            $this->forget();
            throw $e;
        }

        $user->forceFill(['email' => $pending['email'], 'email_verified_at' => now()])->save();
        $this->forget();
    }

    /** @return array<string, mixed>|null */
    public function pending(User $user): ?array
    {
        $pending = $this->session->get(self::KEY);

        return is_array($pending) && ($pending['user_id'] ?? null) === $user->id && isset($pending['code_hash']) ? $pending : null;
    }

    public function forget(): void
    {
        $this->session->forget([self::KEY, 'profile.email.dev_code']);
    }

    public function secondsUntilResend(User $user, ?array $pending = null): int
    {
        $pending ??= $this->pending($user);

        return $pending ? max(0, ($pending['sent_at'] + SignupVerification::RESEND_COOLDOWN_SECONDS) - now()->timestamp) : 0;
    }

    // -----------------------------------------------------------------

    /** The unique index counts soft-deleted users too, so the check must as well. */
    protected function assertFree(User $user, string $email): void
    {
        if (User::withTrashed()->where('email', $email)->where('id', '!=', $user->id)->exists()) {
            throw new RuntimeException(__('Another account already uses this email. Use a different one.'));
        }
    }

    /** @throws RuntimeException */
    protected function issue(User $user, array $pending): void
    {
        if ($pending['sends'] >= SignupVerification::MAX_SENDS) {
            $this->forget();

            throw new RuntimeException(__('We have sent the most codes we can for one request. Please start again.'));
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $pending['code_hash'] = $this->hash($code);
        $pending['expires_at'] = now()->addMinutes(SignupVerification::CODE_TTL_MINUTES)->timestamp;
        $pending['sent_at'] = now()->timestamp;
        $pending['attempts'] = 0;
        $pending['sends']++;

        try {
            // Sent, not queued: the person is waiting on the screen for it.
            Mail::to($pending['email'])->send(new EmailCodeMail($code, $user->name));
        } catch (Throwable $e) {
            Log::warning('Email verification code could not be sent', ['user' => $user->id, 'error' => $e->getMessage()]);

            // Local machines have no working mail: show the code on screen there only.
            if (! app()->environment('local')) {
                throw new RuntimeException(__('We could not send a code to that address. Check it is correct, or try again in a minute.'));
            }
        }

        if (app()->environment('local')) {
            $this->session->put('profile.email.dev_code', $code);
        }

        $this->session->put(self::KEY, $pending);
    }

    protected function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
