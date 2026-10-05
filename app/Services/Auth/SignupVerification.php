<?php

namespace App\Services\Auth;

use App\Mail\SignupCodeMail;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Email verification for self-signup, done BEFORE the account exists.
 *
 * The sign-up form no longer creates anything. It parks the details as a PENDING
 * sign-up in the server-side session (SESSION_DRIVER=database — nothing reaches the
 * browser; the password is already a hash) and emails a 6-digit code. Only a correct
 * code creates the owner, the branch and the account's one free trial.
 *
 * Why before, not after: an unverified account would already hold a mobile number,
 * a branch and the account's single free trial — so a typo'd or invented email would
 * leave a dead account squatting a real person's mobile and a trial nobody can use.
 * Verifying first means every self-signup account has an email that works, which is
 * also what makes email a dependable channel for renewal reminders later.
 *
 * Limits, all per pending sign-up:
 *   · a code is valid for 10 minutes;
 *   · 5 wrong guesses and the sign-up is discarded (a 6-digit code cannot be
 *     brute-forced in 5 tries);
 *   · a new code at most once a minute, and at most 5 codes in all.
 */
class SignupVerification
{
    private const KEY = 'signup.pending';

    public const CODE_TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_SENDS = 5;

    public function __construct(protected Session $session) {}

    /**
     * Park the details and send the first code.
     *
     * @param  array{name:string, hostel_name:string, mobile:string, email:string, password:string}  $details  password in PLAIN text — hashed here, never stored as given
     *
     * @throws RuntimeException when the code cannot be delivered
     */
    public function start(array $details): void
    {
        $pending = [
            'name' => $details['name'],
            'hostel_name' => $details['hostel_name'],
            'mobile' => $details['mobile'],
            'email' => $details['email'],
            'password_hash' => Hash::make($details['password']),
            'sends' => 0,
        ];

        $this->issueCode($pending);
    }

    /** Send a fresh code for the same sign-up. @throws RuntimeException */
    public function resend(): void
    {
        $pending = $this->pending() ?? throw new RuntimeException(__('Your sign-up has expired. Please start again.'));

        $wait = $this->secondsUntilResend($pending);
        if ($wait > 0) {
            throw new RuntimeException(__('Please wait :s seconds before asking for another code.', ['s' => $wait]));
        }

        $this->issueCode($pending);
    }

    /** The owner typed the wrong address: change it and send a code there. @throws RuntimeException */
    public function changeEmail(string $email): void
    {
        $pending = $this->pending() ?? throw new RuntimeException(__('Your sign-up has expired. Please start again.'));

        $pending['email'] = $email;

        // A new address is a fresh start for the code — but NOT for the send budget,
        // or changing the email back and forth would be an unlimited mail cannon.
        $this->issueCode($pending, ignoreCooldown: true);
    }

    /**
     * Check a code. Returns the details when it matches; the caller creates the
     * account and then calls forget().
     *
     * @return array{name:string, hostel_name:string, mobile:string, email:string, password_hash:string}
     *
     * @throws RuntimeException with a message for the person signing up
     */
    public function verify(string $code): array
    {
        $pending = $this->pending() ?? throw new RuntimeException(__('Your sign-up has expired. Please start again.'));

        if (now()->timestamp > $pending['expires_at']) {
            throw new RuntimeException(__('That code has expired. Send a new one and try again.'));
        }

        $code = preg_replace('/\D/', '', $code);

        if (! hash_equals($pending['code_hash'], $this->hash($code))) {
            $pending['attempts']++;

            if ($pending['attempts'] >= self::MAX_ATTEMPTS) {
                $this->forget();

                throw new RuntimeException(__('Too many wrong codes. For your security we have cancelled this sign-up — please start again.'));
            }

            $this->session->put(self::KEY, $pending);
            $left = self::MAX_ATTEMPTS - $pending['attempts'];

            throw new RuntimeException(trans_choice('That code is not right. :n try left.|That code is not right. :n tries left.', $left, ['n' => $left]));
        }

        return [
            'name' => $pending['name'],
            'hostel_name' => $pending['hostel_name'],
            'mobile' => $pending['mobile'],
            'email' => $pending['email'],
            'password_hash' => $pending['password_hash'],
        ];
    }

    /** @return array<string, mixed>|null */
    public function pending(): ?array
    {
        $pending = $this->session->get(self::KEY);

        return is_array($pending) && isset($pending['code_hash']) ? $pending : null;
    }

    public function forget(): void
    {
        $this->session->forget([self::KEY, 'signup.dev_code']);
    }

    public function secondsUntilResend(?array $pending = null): int
    {
        $pending ??= $this->pending();
        if (! $pending) {
            return 0;
        }

        return max(0, ($pending['sent_at'] + self::RESEND_COOLDOWN_SECONDS) - now()->timestamp);
    }

    public function sendsLeft(?array $pending = null): int
    {
        $pending ??= $this->pending();

        return $pending ? max(0, self::MAX_SENDS - $pending['sends']) : 0;
    }

    /** "r•••@gmail.com" — enough to recognise, not enough to harvest. */
    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).str_repeat('•', max(2, min(6, mb_strlen($local) - 1))).'@'.$domain;
    }

    // -----------------------------------------------------------------

    /** @throws RuntimeException */
    protected function issueCode(array $pending, bool $ignoreCooldown = false): void
    {
        if ($pending['sends'] >= self::MAX_SENDS) {
            $this->forget();

            throw new RuntimeException(__('We have sent the most codes we can for one sign-up. Please start again.'));
        }

        if (! $ignoreCooldown && isset($pending['sent_at']) && $this->secondsUntilResend($pending) > 0) {
            throw new RuntimeException(__('Please wait :s seconds before asking for another code.', ['s' => $this->secondsUntilResend($pending)]));
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $pending['code_hash'] = $this->hash($code);
        $pending['expires_at'] = now()->addMinutes(self::CODE_TTL_MINUTES)->timestamp;
        $pending['sent_at'] = now()->timestamp;
        $pending['attempts'] = 0;
        $pending['sends']++;

        try {
            // SENT, not queued: the person is staring at the screen waiting for it,
            // and the production queue drains once a minute.
            Mail::to($pending['email'])->send(new SignupCodeMail($code, $pending['name']));
        } catch (Throwable $e) {
            Log::warning('Signup verification email could not be sent', [
                'email' => $pending['email'], 'error' => $e->getMessage(),
            ]);

            // LOCAL ONLY: this machine's .env has a placeholder mail password, so mail
            // cannot leave it. Show the code on screen so sign-up can be exercised
            // here. Never in any other environment — there, an undeliverable code
            // simply stops the sign-up with an explanation.
            if (! app()->environment('local')) {
                throw new RuntimeException(__('We could not send a code to that email address. Check it is correct, or try again in a minute.'));
            }
        }

        if (app()->environment('local')) {
            $this->session->put('signup.dev_code', $code);
        }

        $this->session->put(self::KEY, $pending);
    }

    protected function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
