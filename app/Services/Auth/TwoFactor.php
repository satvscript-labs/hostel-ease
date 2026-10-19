<?php

namespace App\Services\Auth;

use App\Mail\SecurityAlertMail;
use App\Mail\TwoFactorCodeMail;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Two-factor sign-in (S7.1 · 34_S7_1_DESIGN.md §3). Opt-in for every role (D-S7-4);
 * the second step is a code from an authenticator app OR a code emailed to the
 * person's verified address (D-S7-3), and 8 one-time recovery codes either way.
 *
 * Nothing here logs anyone in. LoginController checks the password, hands the person
 * to startLogin(), and only after verifyLogin() succeeds does SignIn create the session.
 */
class TwoFactor
{
    public const METHOD_APP = 'app';

    public const METHOD_EMAIL = 'email';

    public const LOGIN_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const CODE_MINUTES = 10;

    public const RESEND_SECONDS = 60;

    public const MAX_SENDS = 5;

    public const TRUST_DAYS = 30;

    public const RECOVERY_CODES = 8;

    /** Wrong second steps allowed per PERSON (across sign-ins) before a 15-minute lock. */
    public const MAX_FAILURES = 10;

    public const LOCK_SECONDS = 900;

    /** Emailed codes per person per hour, however many sign-ins are started. */
    public const MAX_SENDS_PER_HOUR = 10;

    public const COOKIE = 'he_trusted_device';

    private const PENDING = 'auth.2fa.pending';

    private const SETUP = 'auth.2fa.setup';

    private const RECOVERY_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function __construct(protected Session $session) {}

    public function enabled(User $user): bool
    {
        return in_array($user->two_factor_method, [self::METHOD_APP, self::METHOD_EMAIL], true)
            && $user->two_factor_confirmed_at !== null;
    }

    // ── switching it on ──────────────────────────────────────────────────

    /** Start app set-up: a new key, held in the session until a code proves the app has it. */
    public function beginApp(User $user): string
    {
        if ($this->enabled($user)) {
            throw new RuntimeException(__('Two-factor sign-in is already on. Turn it off first to change how you sign in.'));
        }

        $secret = Totp::newSecret();
        $this->session->put(self::SETUP, ['user_id' => $user->id, 'method' => self::METHOD_APP, 'secret' => encrypt($secret)]);

        return $secret;
    }

    /** Start email set-up: needs a verified address; the first code goes out now. */
    public function beginEmail(User $user): void
    {
        if ($this->enabled($user)) {
            throw new RuntimeException(__('Two-factor sign-in is already on. Turn it off first to change how you sign in.'));
        }
        if (! $user->email || ! $user->email_verified_at) {
            throw new RuntimeException(__('Add and verify your email first — the codes are sent there.'));
        }

        $setup = ['user_id' => $user->id, 'method' => self::METHOD_EMAIL, 'sends' => 0];
        $this->session->put(self::SETUP, $this->issueEmailCode($user, $setup, 'setup'));
    }

    /** @return array<string, mixed>|null the set-up in progress for this person */
    public function setup(User $user): ?array
    {
        $s = $this->session->get(self::SETUP);

        return is_array($s) && ($s['user_id'] ?? null) === $user->id ? $s : null;
    }

    /** The key being set up (for the QR code), or null. */
    public function setupSecret(User $user): ?string
    {
        $s = $this->setup($user);

        return $s && $s['method'] === self::METHOD_APP ? decrypt($s['secret']) : null;
    }

    public function cancelSetup(): void
    {
        $this->session->forget(self::SETUP);
    }

    /** @throws RuntimeException */
    public function resendSetupCode(User $user): void
    {
        $s = $this->setup($user);
        if (! $s || $s['method'] !== self::METHOD_EMAIL) {
            throw new RuntimeException(__('That request has expired. Start again.'));
        }
        $this->assertCanResend($s);
        $this->session->put(self::SETUP, $this->issueEmailCode($user, $s, 'setup'));
    }

    /**
     * The code proves the app (or inbox) works: switch it on and return the recovery
     * codes — the only time they are ever shown.
     *
     * @return list<string>
     *
     * @throws RuntimeException
     */
    public function confirm(User $user, string $code): array
    {
        $s = $this->setup($user) ?? throw new RuntimeException(__('That request has expired. Start again.'));

        $attrs = ['two_factor_method' => $s['method'], 'two_factor_confirmed_at' => now()];

        if ($s['method'] === self::METHOD_APP) {
            $secret = decrypt($s['secret']);
            $step = Totp::verify($secret, $code, now()->timestamp);
            if ($step === null) {
                throw new RuntimeException(__('That code is not right. Check the time on your phone is set automatically, then enter the newest code.'));
            }
            $attrs += ['two_factor_secret' => $secret, 'two_factor_last_step' => $step];
        } else {
            $s = $this->checkEmailCode($s, $code, self::SETUP);
            $attrs += ['two_factor_secret' => null, 'two_factor_last_step' => null];
        }

        [$plain, $hashed] = $this->makeRecoveryCodes();
        $user->forceFill($attrs + ['two_factor_recovery_codes' => $hashed])->save();
        $this->cancelSetup();
        $this->alert($user, 'enabled');

        return $plain;
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(User $user): array
    {
        if (! $this->enabled($user)) {
            throw new RuntimeException(__('Two-factor sign-in is off.'));
        }
        [$plain, $hashed] = $this->makeRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $hashed])->save();

        return $plain;
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count((array) $user->two_factor_recovery_codes);
    }

    /**
     * Switch it off. $by = 'self' (with the password) or 'admin' (an owner for their
     * staff, the Super Admin for an owner). Trusted devices go with it.
     */
    public function disable(User $user, string $by = 'self'): void
    {
        $was = $this->enabled($user);
        $user->forceFill([
            'two_factor_method' => null, 'two_factor_secret' => null, 'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null, 'two_factor_last_step' => null,
        ])->save();
        $this->forgetDevices($user);

        if ($was) {
            $this->alert($user, $by === 'admin' ? 'reset' : 'disabled');
        }
    }

    // ── signing in ───────────────────────────────────────────────────────

    /** The password was right; park the sign-in until the second step. */
    public function startLogin(User $user, bool $remember): void
    {
        $pending = [
            'user_id' => $user->id, 'remember' => $remember, 'attempts' => 0, 'sends' => 0,
            'expires_at' => now()->addMinutes(self::LOGIN_MINUTES)->timestamp,
        ];

        if ($user->two_factor_method === self::METHOD_EMAIL) {
            try {
                $pending = $this->issueEmailCode($user, $pending, 'login');
            } catch (RuntimeException $e) {
                // Still let them in with a recovery code; the page says what happened.
                $pending['send_error'] = $e->getMessage();
            }
        }

        $this->session->put(self::PENDING, $pending);
    }

    /** @return array<string, mixed>|null */
    public function pendingLogin(): ?array
    {
        $p = $this->session->get(self::PENDING);
        if (! is_array($p) || now()->timestamp > ($p['expires_at'] ?? 0)) {
            return null;
        }

        return $p;
    }

    /** The person part-way through signing in — still active, still on two-factor. */
    public function pendingUser(): ?User
    {
        $p = $this->pendingLogin();
        $user = $p ? User::find($p['user_id']) : null;

        return $user && $user->is_active && $this->enabled($user) ? $user : null;
    }

    /** @throws RuntimeException */
    public function resendLoginCode(): void
    {
        $p = $this->pendingLogin();
        $user = $this->pendingUser();
        if (! $p || ! $user || $user->two_factor_method !== self::METHOD_EMAIL) {
            throw new RuntimeException(__('Your sign-in has expired. Enter your mobile and password again.'));
        }
        $this->assertCanResend($p);
        unset($p['send_error']);
        $this->session->put(self::PENDING, $this->issueEmailCode($user, $p, 'login'));
    }

    /**
     * Check the second step. Returns the user (and whether to remember them) — the
     * caller logs them in. A recovery code works once and emails the person.
     *
     * @return array{0: User, 1: bool}
     *
     * @throws RuntimeException
     */
    public function verifyLogin(string $code, bool $recovery = false): array
    {
        $p = $this->pendingLogin();
        $user = $this->pendingUser();
        if (! $p || ! $user) {
            $this->clearLogin();
            throw new RuntimeException(__('Your sign-in has expired. Enter your mobile and password again.'));
        }

        // Per PERSON, not per sign-in: someone who knows the password could otherwise
        // start a fresh sign-in for every 5 guesses (S7.1 audit).
        $lockKey = '2fa-fail:'.$user->id;
        if (RateLimiter::tooManyAttempts($lockKey, self::MAX_FAILURES)) {
            $this->clearLogin();
            throw new RuntimeException(__('Too many wrong codes on this account. Try again in :m minutes.', ['m' => (int) ceil(RateLimiter::availableIn($lockKey) / 60)]));
        }

        $ok = DB::transaction(function () use ($user, $code, $recovery, &$p) {
            $user = User::whereKey($user->id)->lockForUpdate()->first();

            if ($recovery) {
                return $this->consumeRecoveryCode($user, $code);
            }
            if ($user->two_factor_method === self::METHOD_APP) {
                $step = Totp::verify((string) $user->two_factor_secret, $code, now()->timestamp, $user->two_factor_last_step);
                if ($step === null) {
                    return false;
                }
                $user->forceFill(['two_factor_last_step' => $step])->save();

                return true;
            }

            return isset($p['code_hash'], $p['code_expires_at'])
                && now()->timestamp <= $p['code_expires_at']
                && hash_equals($p['code_hash'], $this->hash(preg_replace('/\D/', '', $code)));
        });

        if (! $ok) {
            RateLimiter::hit($lockKey, self::LOCK_SECONDS);
            $p['attempts']++;
            if ($p['attempts'] >= self::MAX_ATTEMPTS) {
                $this->clearLogin();
                throw new RuntimeException(__('Too many wrong codes. For your security, enter your mobile and password again.'));
            }
            $this->session->put(self::PENDING, $p);
            $left = self::MAX_ATTEMPTS - $p['attempts'];

            throw new RuntimeException(trans_choice('That code is not right. :n try left.|That code is not right. :n tries left.', $left, ['n' => $left]));
        }

        $this->clearLogin();
        RateLimiter::clear($lockKey);
        if ($recovery) {
            $this->alert($user->fresh(), 'recovery_used');
        }

        return [$user->fresh(), (bool) $p['remember']];
    }

    public function clearLogin(): void
    {
        $this->session->forget([self::PENDING, 'auth.2fa.dev_code']);
    }

    public function secondsUntilResend(?array $state): int
    {
        return $state && isset($state['sent_at']) ? max(0, $state['sent_at'] + self::RESEND_SECONDS - now()->timestamp) : 0;
    }

    // ── trusted devices ──────────────────────────────────────────────────

    public function trustDevice(User $user, Request $request): void
    {
        $token = Str::random(64);
        TrustedDevice::create([
            'user_id' => $user->id, 'token_hash' => hash('sha256', $token),
            'label' => mb_substr(self::deviceLabel((string) $request->userAgent()), 0, 150),
            'ip' => $request->ip(), 'last_used_at' => now(), 'expires_at' => now()->addDays(self::TRUST_DAYS),
        ]);
        Cookie::queue(Cookie::make(self::COOKIE, $token, self::TRUST_DAYS * 24 * 60, '/', null, null, true, false, 'lax'));
    }

    public function isTrusted(User $user, Request $request): bool
    {
        $token = (string) $request->cookie(self::COOKIE);
        if ($token === '') {
            return false;
        }
        $device = TrustedDevice::where('token_hash', hash('sha256', $token))->where('user_id', $user->id)
            ->where('expires_at', '>', now())->first();
        $device?->forceFill(['last_used_at' => now(), 'ip' => $request->ip()])->save();

        return $device !== null;
    }

    public function forgetDevices(User $user): void
    {
        TrustedDevice::where('user_id', $user->id)->delete();
    }

    /** "Chrome on Windows" from a user agent — just enough to recognise a device. */
    public static function deviceLabel(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => __('Browser'),
        };
        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'Mac',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };

        return $os ? __(':browser on :os', ['browser' => $browser, 'os' => $os]) : $browser;
    }

    // ── internals ────────────────────────────────────────────────────────

    /** @return array{0: list<string>, 1: list<string>} plain codes, and their hashes */
    protected function makeRecoveryCodes(): array
    {
        $plain = [];
        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $c = '';
            for ($j = 0; $j < 8; $j++) {
                $c .= self::RECOVERY_ALPHABET[random_int(0, strlen(self::RECOVERY_ALPHABET) - 1)];
            }
            $plain[] = substr($c, 0, 4).'-'.substr($c, 4);
        }

        return [$plain, array_map(fn ($c) => $this->hash(self::normaliseRecovery($c)), $plain)];
    }

    protected function consumeRecoveryCode(User $user, string $code): bool
    {
        $hash = $this->hash(self::normaliseRecovery($code));
        $codes = array_values((array) $user->two_factor_recovery_codes);
        foreach ($codes as $i => $stored) {
            if (hash_equals($stored, $hash)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    protected static function normaliseRecovery(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    /** @throws RuntimeException */
    protected function assertCanResend(array $state): void
    {
        if (($state['sends'] ?? 0) >= self::MAX_SENDS) {
            throw new RuntimeException(__('We have sent the most codes we can for now. Use a recovery code, or try again later.'));
        }
        $wait = $this->secondsUntilResend($state);
        if ($wait > 0) {
            throw new RuntimeException(__('Please wait :s seconds before asking for another code.', ['s' => $wait]));
        }
    }

    /** @throws RuntimeException */
    protected function checkEmailCode(array $state, string $code, string $key): array
    {
        if (! isset($state['code_hash']) || now()->timestamp > ($state['code_expires_at'] ?? 0)) {
            throw new RuntimeException(__('That code has expired. Send a new one and try again.'));
        }
        if (! hash_equals($state['code_hash'], $this->hash(preg_replace('/\D/', '', $code)))) {
            $state['attempts'] = ($state['attempts'] ?? 0) + 1;
            if ($state['attempts'] >= self::MAX_ATTEMPTS) {
                $this->session->forget($key);
                throw new RuntimeException(__('Too many wrong codes. For your security we cancelled this — please start again.'));
            }
            $this->session->put($key, $state);
            $left = self::MAX_ATTEMPTS - $state['attempts'];
            throw new RuntimeException(trans_choice('That code is not right. :n try left.|That code is not right. :n tries left.', $left, ['n' => $left]));
        }

        return $state;
    }

    /**
     * A new 6-digit code to the person's verified email.
     *
     * @throws RuntimeException when it could not be sent (except on a local machine,
     *                          where the code is shown on screen instead)
     */
    protected function issueEmailCode(User $user, array $state, string $purpose): array
    {
        $sendKey = '2fa-send:'.$user->id;
        if (RateLimiter::tooManyAttempts($sendKey, self::MAX_SENDS_PER_HOUR)) {
            throw new RuntimeException(__('We have sent the most codes we can for now. Use a recovery code, or try again later.'));
        }
        RateLimiter::hit($sendKey, 3600);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $state['code_hash'] = $this->hash($code);
        $state['code_expires_at'] = now()->addMinutes(self::CODE_MINUTES)->timestamp;
        $state['sent_at'] = now()->timestamp;
        $state['sends'] = ($state['sends'] ?? 0) + 1;

        try {
            Mail::to($user)->send(new TwoFactorCodeMail($code, $user->name, $purpose));
        } catch (Throwable $e) {
            Log::warning('Two-factor code could not be sent', ['user' => $user->id, 'error' => $e->getMessage()]);
            if (! app()->environment('local')) {
                throw new RuntimeException(__('We could not send your code just now. Use a recovery code, or try again in a minute.'));
            }
        }
        if (app()->environment('local')) {
            $this->session->put('auth.2fa.dev_code', $code);
        }

        return $state;
    }

    /** Tell the person (verified email only) when their sign-in security changes. */
    protected function alert(User $user, string $kind): void
    {
        if (! $user->email || ! $user->email_verified_at) {
            return;
        }
        try {
            Mail::to($user)->send(new SecurityAlertMail($user->name, $kind, request()?->ip()));
        } catch (Throwable $e) {
            Log::warning('Security alert email could not be sent', ['user' => $user->id, 'kind' => $kind, 'error' => $e->getMessage()]);
        }
    }

    protected function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
