<?php

namespace Tests\Feature;

use App\Mail\SecurityAlertMail;
use App\Mail\TwoFactorCodeMail;
use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\Auth\Totp;
use App\Services\Auth\TwoFactor;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * S7.1 — two-factor sign-in (_artifact/saas_billing_autopay/34_S7_1_DESIGN.md §3).
 */
class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
        Mail::fake();
    }

    protected function owner(string $mobile = '9811000001', bool $verifiedEmail = true): User
    {
        $owner = User::factory()->create([
            'role' => 'hostel_admin', 'mobile' => $mobile, 'name' => 'Ramesh Patel',
            'email' => 'o'.$mobile.'@example.test', 'email_verified_at' => $verifiedEmail ? now() : null,
        ]);
        $h = Hostel::factory()->create(['mobile' => $mobile, 'owner_id' => $owner->id, 'status' => 'active', 'subscription_end' => now()->addMonths(6)]);
        $owner->hostels()->sync([$h->id]);
        $owner->forceFill(['hostel_id' => $h->id])->save();
        SubscriptionAccount::create(['owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addMonths(6)]);

        return $owner;
    }

    /** Switch the app method on directly; returns [secret, recovery codes]. */
    protected function withApp(User $user): array
    {
        $this->actingAs($user)->post(route('security.2fa.app'), ['password' => 'password'])->assertRedirect(route('profile.security'));
        $secret = app(TwoFactor::class)->setupSecret($user);
        $this->actingAs($user)->post(route('security.2fa.confirm'), ['code' => Totp::codeAt($secret, Totp::stepAt(now()->timestamp))])
            ->assertSessionHas('recovery_codes');
        $codes = session('recovery_codes');
        auth()->logout();
        $this->flushSession();
        // The code just used is spent (no replay): move past its 30-second step.
        $this->travel(31)->seconds();

        return [$secret, $codes];
    }

    protected function login(string $mobile = '9811000001', string $password = 'password', array $extra = [])
    {
        return $this->post(route('login.attempt'), ['mobile' => $mobile, 'password' => $password] + $extra);
    }

    protected function appCode(string $secret, int $offsetSteps = 0): string
    {
        return Totp::codeAt($secret, Totp::stepAt(now()->timestamp) + $offsetSteps);
    }

    // =================================================================
    // The algorithm
    // =================================================================

    public function test_totp_matches_the_rfc_6238_test_vectors(): void
    {
        $secret = Totp::base32Encode('12345678901234567890');
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $secret);

        foreach ([59 => '94287082', 1111111109 => '07081804', 1234567890 => '89005924', 2000000000 => '69279037'] as $t => $code) {
            $this->assertSame($code, Totp::codeAt($secret, Totp::stepAt($t), 8), "t={$t}");
        }
        $this->assertSame('12345678901234567890', Totp::base32Decode($secret));
    }

    public function test_a_code_one_step_off_works_but_an_older_or_used_one_does_not(): void
    {
        $secret = Totp::newSecret();
        $now = 1_800_000_000;
        $step = Totp::stepAt($now);

        $this->assertSame($step - 1, Totp::verify($secret, Totp::codeAt($secret, $step - 1), $now));
        $this->assertNull(Totp::verify($secret, Totp::codeAt($secret, $step - 2), $now));
        $this->assertNull(Totp::verify($secret, Totp::codeAt($secret, $step), $now, after: $step), 'already used');
        $this->assertNull(Totp::verify($secret, '12345', $now));
    }

    // =================================================================
    // Turning it on
    // =================================================================

    public function test_without_two_factor_sign_in_is_unchanged(): void
    {
        $this->owner();
        $this->login()->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_setting_up_the_app_needs_the_password_and_a_working_code(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('security.2fa.app'), ['password' => 'wrong'])->assertSessionHasErrors('password', null, 'twoFactor');
        $this->assertNull(app(TwoFactor::class)->setup($owner));

        $this->actingAs($owner)->post(route('security.2fa.app'), ['password' => 'password']);
        $secret = app(TwoFactor::class)->setupSecret($owner);
        $this->assertNotNull($secret);
        $this->actingAs($owner)->get(route('profile.security'))->assertOk()->assertSee('<svg', false)->assertSee(trim(chunk_split($secret, 4, ' ')));

        $this->actingAs($owner)->post(route('security.2fa.confirm'), ['code' => '000000'])->assertSessionHasErrors('code', null, 'twoFactor');
        $this->assertFalse($owner->fresh()->hasTwoFactor());

        $this->actingAs($owner)->post(route('security.2fa.confirm'), ['code' => $this->appCode($secret)])->assertSessionHas('recovery_codes');
        $owner->refresh();
        $this->assertTrue($owner->hasTwoFactor());
        $this->assertSame('app', $owner->two_factor_method);
        $this->assertCount(8, session('recovery_codes'));
        $this->assertSame($secret, $owner->two_factor_secret);
        $this->assertNotSame($secret, DB::table('users')->where('id', $owner->id)->value('two_factor_secret'), 'stored encrypted');
        Mail::assertSent(SecurityAlertMail::class, fn ($m) => $m->kind === 'enabled');
    }

    public function test_email_codes_need_a_verified_email(): void
    {
        $owner = $this->owner(verifiedEmail: false);

        $this->actingAs($owner)->post(route('security.2fa.email'), ['password' => 'password'])->assertSessionHasErrors('code', null, 'twoFactor');
        Mail::assertNotSent(TwoFactorCodeMail::class);
    }

    // =================================================================
    // Signing in
    // =================================================================

    public function test_with_the_app_the_password_alone_does_not_sign_in(): void
    {
        $owner = $this->owner();
        [$secret] = $this->withApp($owner);

        $this->login()->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->get(route('two-factor.challenge'))->assertOk()->assertSee(__('Enter your code'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $this->post(route('two-factor.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post(route('two-factor.verify'), ['code' => $this->appCode($secret)])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);
    }

    public function test_an_app_code_works_only_once(): void
    {
        $owner = $this->owner();
        [$secret] = $this->withApp($owner);
        $this->travel(1)->minutes();   // past the step used to switch it on
        $code = $this->appCode($secret);

        $this->login();
        $this->post(route('two-factor.verify'), ['code' => $code]);
        $this->assertAuthenticatedAs($owner);

        auth()->logout();
        $this->login();
        $this->post(route('two-factor.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_five_wrong_codes_end_the_sign_in(): void
    {
        $owner = $this->owner();
        [$secret] = $this->withApp($owner);

        $this->login();
        for ($i = 0; $i < 4; $i++) {
            $this->post(route('two-factor.verify'), ['code' => '111111'])->assertSessionHasErrors('code');
        }
        $this->post(route('two-factor.verify'), ['code' => '111111'])->assertRedirect(route('login'));

        // Even the right code is useless now: back to the password.
        $this->post(route('two-factor.verify'), ['code' => $this->appCode($secret)])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_parked_sign_in_expires_after_ten_minutes(): void
    {
        $owner = $this->owner();
        [$secret] = $this->withApp($owner);

        $this->login();
        $this->travel(11)->minutes();
        $this->post(route('two-factor.verify'), ['code' => $this->appCode($secret)])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_disabled_account_cannot_finish_signing_in(): void
    {
        $owner = $this->owner();
        [$secret] = $this->withApp($owner);

        $this->login();
        $owner->forceFill(['is_active' => false])->save();
        $this->post(route('two-factor.verify'), ['code' => $this->appCode($secret)])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_challenge_page_needs_a_password_first(): void
    {
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
        $this->post(route('two-factor.verify'), ['code' => '123456'])->assertRedirect(route('login'));
    }

    public function test_a_recovery_code_works_once_and_tells_the_owner(): void
    {
        $owner = $this->owner();
        [, $codes] = $this->withApp($owner);

        $this->login();
        $this->post(route('two-factor.verify'), ['code' => strtolower($codes[0]), 'recovery' => 1])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);
        $this->assertSame(7, app(TwoFactor::class)->remainingRecoveryCodes($owner->fresh()));
        Mail::assertSent(SecurityAlertMail::class, fn ($m) => $m->kind === 'recovery_used');

        auth()->logout();
        $this->login();
        $this->post(route('two-factor.verify'), ['code' => $codes[0], 'recovery' => 1])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_email_codes_from_set_up_to_sign_in(): void
    {
        $owner = $this->owner();
        $sent = null;
        Mail::fake();

        $this->actingAs($owner)->post(route('security.2fa.email'), ['password' => 'password']);
        Mail::assertSent(TwoFactorCodeMail::class, function ($m) use (&$sent) { $sent = $m->code; return $m->purpose === 'setup'; });
        $this->actingAs($owner)->post(route('security.2fa.confirm'), ['code' => $sent])->assertSessionHas('recovery_codes');
        $this->assertSame('email', $owner->fresh()->two_factor_method);
        auth()->logout();
        $this->flushSession();

        // Sign in: a fresh code is emailed.
        $this->login()->assertRedirect(route('two-factor.challenge'));
        $loginCode = null;
        Mail::assertSent(TwoFactorCodeMail::class, function ($m) use (&$loginCode) { if ($m->purpose === 'login') { $loginCode = $m->code; } return $m->purpose === 'login'; });
        $this->get(route('two-factor.challenge'))->assertOk()->assertSee('o••••••@example.test', false);

        // Resend is held back for a minute.
        $this->post(route('two-factor.resend'))->assertSessionHasErrors('code');

        $this->post(route('two-factor.verify'), ['code' => $loginCode])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);
    }

    public function test_an_email_code_expires(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post(route('security.2fa.email'), ['password' => 'password']);
        $code = null;
        Mail::assertSent(TwoFactorCodeMail::class, function ($m) use (&$code) { $code = $m->code; return true; });
        $this->actingAs($owner)->post(route('security.2fa.confirm'), ['code' => $code]);
        auth()->logout();
        $this->flushSession();
        Mail::fake();

        $this->login();
        Mail::assertSent(TwoFactorCodeMail::class, function ($m) use (&$code) { $code = $m->code; return true; });
        $this->travel(9)->minutes();   // inside the 10-minute sign-in, past… not yet the code
        $this->travel(2)->minutes();   // 11 minutes: both gone
        $this->post(route('two-factor.verify'), ['code' => $code])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // =================================================================
    // Trusted devices
    // =================================================================

    public function test_a_trusted_device_skips_the_code_for_thirty_days_and_only_for_that_person(): void
    {
        $owner = $this->owner();
        [$secret] = $this->withApp($owner);

        $this->login();
        $res = $this->post(route('two-factor.verify'), ['code' => $this->appCode($secret), 'trust' => 1]);
        $cookie = collect($res->headers->getCookies())->first(fn ($c) => $c->getName() === TwoFactor::COOKIE);
        $this->assertNotNull($cookie);
        $this->assertSame(1, TrustedDevice::where('user_id', $owner->id)->count());
        $this->assertNotSame($cookie->getValue(), TrustedDevice::first()->token_hash);

        // Next sign-in on this device: straight in.
        auth()->logout();
        $this->flushSession();
        $this->withCookie(TwoFactor::COOKIE, $this->plainCookie($res))->post(route('login.attempt'), ['mobile' => '9811000001', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);

        // Another person on the same browser is still asked.
        auth()->logout();
        $other = $this->owner('9811000002');
        $this->withApp($other);
        $this->withCookie(TwoFactor::COOKIE, $this->plainCookie($res))->post(route('login.attempt'), ['mobile' => '9811000002', 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge'));

        // After 30 days the device is asked again.
        $this->flushSession();
        $this->travel(31)->days();
        $this->withCookie(TwoFactor::COOKIE, $this->plainCookie($res))->post(route('login.attempt'), ['mobile' => '9811000001', 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_a_forged_device_cookie_does_nothing(): void
    {
        $owner = $this->owner();
        $this->withApp($owner);

        $this->withCookie(TwoFactor::COOKIE, 'not-a-real-token')->post(route('login.attempt'), ['mobile' => '9811000001', 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
    }

    // =================================================================
    // Managing it
    // =================================================================

    public function test_turning_it_off_and_new_codes_need_the_password(): void
    {
        $owner = $this->owner();
        [, $codes] = $this->withApp($owner);

        $this->actingAs($owner)->delete(route('security.2fa.disable'), ['password' => 'wrong'])->assertSessionHasErrors('password', null, 'twoFactor');
        $this->assertTrue($owner->fresh()->hasTwoFactor());

        $this->actingAs($owner)->post(route('security.2fa.codes'), ['password' => 'password'])->assertSessionHas('recovery_codes');
        $new = session('recovery_codes');
        $this->assertNotSame($codes, $new);

        TrustedDevice::create(['user_id' => $owner->id, 'token_hash' => str_repeat('a', 64), 'expires_at' => now()->addDays(5)]);
        $this->actingAs($owner)->delete(route('security.2fa.disable'), ['password' => 'password'])->assertSessionHas('success');
        $owner->refresh();
        $this->assertFalse($owner->hasTwoFactor());
        $this->assertNull($owner->two_factor_secret);
        $this->assertSame(0, TrustedDevice::where('user_id', $owner->id)->count());
        Mail::assertSent(SecurityAlertMail::class, fn ($m) => $m->kind === 'disabled');
    }

    public function test_changing_the_password_signs_out_everywhere_else(): void
    {
        $owner = $this->owner();
        DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);
        TrustedDevice::create(['user_id' => $owner->id, 'token_hash' => str_repeat('b', 64), 'expires_at' => now()->addDays(5)]);
        $token = $owner->remember_token;

        $this->actingAs($owner)->put(route('profile.password.update'), ['current_password' => 'password', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertRedirect(route('profile.security'));

        $this->assertSame(0, DB::table('sessions')->where('id', 'other-session')->count());
        $this->assertSame(0, TrustedDevice::where('user_id', $owner->id)->count());
        $this->assertNotSame($token, $owner->fresh()->remember_token);
    }

    public function test_sign_out_everywhere_else(): void
    {
        $owner = $this->owner();
        DB::table('sessions')->insert(['id' => 'phone-session', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($owner)->post(route('security.logout-others'), ['password' => 'wrong'])->assertSessionHasErrors('password', null, 'twoFactor');
        $this->assertSame(1, DB::table('sessions')->count());

        $this->actingAs($owner)->post(route('security.logout-others'), ['password' => 'password'])->assertSessionHas('success');
        $this->assertSame(0, DB::table('sessions')->where('id', 'phone-session')->count());
    }

    public function test_someone_else_cannot_remove_your_trusted_device(): void
    {
        $owner = $this->owner();
        $other = $this->owner('9811000003');
        $d = TrustedDevice::create(['user_id' => $owner->id, 'token_hash' => str_repeat('c', 64), 'expires_at' => now()->addDays(5)]);

        $this->actingAs($other)->delete(route('security.devices.forget', $d->id));
        $this->assertNotNull($d->fresh());
        $this->actingAs($owner)->delete(route('security.devices.forget', $d->id));
        $this->assertNull($d->fresh());
    }

    public function test_an_owner_resets_a_staff_members_two_factor(): void
    {
        $owner = $this->owner();
        $staff = User::factory()->create(['role' => 'manager', 'mobile' => '9811000009', 'hostel_id' => $owner->hostel_id, 'email' => 's@example.test', 'email_verified_at' => now()]);
        $staff->hostels()->sync([$owner->hostel_id]);
        $this->withApp($staff);

        $this->actingAs($owner)->get(route('admin.settings.index', ['tab' => 'users']))->assertOk()->assertSee(route('admin.users.reset-2fa', $staff), false);
        $this->actingAs($owner)->patch(route('admin.users.reset-2fa', $staff))->assertSessionHas('success');
        $this->assertFalse($staff->fresh()->hasTwoFactor());
        Mail::assertSent(SecurityAlertMail::class, fn ($m) => $m->kind === 'reset');

        // ...and an owner elsewhere cannot.
        $staff = $staff->fresh();
        $this->withApp($staff);
        $stranger = $this->owner('9811000004');
        $this->actingAs($stranger)->patch(route('admin.users.reset-2fa', $staff))->assertForbidden();
        $this->assertTrue($staff->fresh()->hasTwoFactor());
    }

    public function test_the_super_admin_resets_an_owners_two_factor(): void
    {
        $owner = $this->owner();
        $this->withApp($owner);
        $admin = User::factory()->create(['role' => 'super_admin', 'mobile' => '9811000099']);

        $this->actingAs($admin)->patch(route('superadmin.admins.reset-2fa', $owner))->assertSessionHas('success');
        $this->assertFalse($owner->fresh()->hasTwoFactor());
    }

    public function test_the_api_never_issues_a_token_past_two_factor(): void
    {
        $owner = $this->owner();
        $this->withApp($owner);

        $this->postJson('/api/v1/login', ['mobile' => '9811000001', 'password' => 'password'])->assertStatus(422);
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_every_role_has_the_security_page(): void
    {
        $owner = $this->owner();
        $staff = User::factory()->create(['role' => 'warden', 'mobile' => '9811000010', 'hostel_id' => $owner->hostel_id]);
        $staff->hostels()->sync([$owner->hostel_id]);
        $admin = User::factory()->create(['role' => 'super_admin', 'mobile' => '9811000098']);

        foreach ([$owner, $staff, $admin] as $u) {
            $this->actingAs($u)->get(route('profile.security'))->assertOk()->assertSee(__('Two-factor sign-in'));
        }
        $this->actingAs($owner)->get(route('profile.password'))->assertRedirect(route('profile.security'));
    }

    // =================================================================
    // Audit regressions (35_S7_1_AUDIT.md)
    // =================================================================

    public function test_wrong_codes_count_per_person_not_per_sign_in(): void
    {
        $owner = $this->owner();
        [$secret] = $this->withApp($owner);

        // 2 sign-ins × 5 wrong codes = 10: the account is locked, even with the right code.
        foreach ([1, 2] as $round) {
            $this->login();
            for ($i = 0; $i < 5; $i++) {
                $this->post(route('two-factor.verify'), ['code' => '111111']);
            }
            $this->travel(70)->seconds();   // past the per-minute route throttles, not the 15-minute lock
        }
        $this->login();
        $this->post(route('two-factor.verify'), ['code' => $this->appCode($secret)])->assertRedirect(route('login'));
        $this->assertGuest();

        // 15 minutes on, it works again.
        $this->travel(16)->minutes();
        $this->login();
        $this->post(route('two-factor.verify'), ['code' => $this->appCode($secret)])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);
    }

    public function test_emailed_codes_are_capped_per_person_per_hour(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post(route('security.2fa.email'), ['password' => 'password']);
        $code = null;
        Mail::assertSent(TwoFactorCodeMail::class, function ($m) use (&$code) { $code = $m->code; return true; });
        $this->actingAs($owner)->post(route('security.2fa.confirm'), ['code' => $code]);
        auth()->logout();
        $this->flushSession();
        Mail::fake();

        // Restarting the sign-in over and over does not mean endless emails.
        for ($i = 0; $i < 14; $i++) {
            $this->flushSession();
            $this->login();
            $this->travel(70)->seconds();   // past the per-minute route throttle, inside the hour
        }
        Mail::assertSent(TwoFactorCodeMail::class, TwoFactor::MAX_SENDS_PER_HOUR - 1);   // one was spent on set-up
    }

    public function test_turning_it_on_signs_out_every_other_device(): void
    {
        $owner = $this->owner();
        DB::table('sessions')->insert(['id' => 'old-laptop', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);
        $token = $owner->remember_token;

        $this->withApp($owner);

        $this->assertSame(0, DB::table('sessions')->where('id', 'old-laptop')->count());
        $this->assertNotSame($token, $owner->fresh()->remember_token);
    }

    public function test_an_old_password_hash_is_still_upgraded_on_sign_in(): void
    {
        $owner = $this->owner();
        $weak = password_hash('password', PASSWORD_BCRYPT, ['cost' => 5]);   // the app hashes at 4 in tests
        DB::table('users')->where('id', $owner->id)->update(['password' => $weak]);

        $this->login()->assertRedirect(route('dashboard'));

        $this->assertNotSame($weak, DB::table('users')->where('id', $owner->id)->value('password'));
    }

    /** The plain token the browser holds (the response cookie is encrypted by the middleware). */
    protected function plainCookie($response): string
    {
        $c = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === TwoFactor::COOKIE);
        $value = app('encrypter')->decrypt($c->getValue(), false);

        return \Illuminate\Cookie\CookieValuePrefix::remove($value);
    }
}
