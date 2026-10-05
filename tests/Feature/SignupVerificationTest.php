<?php

namespace Tests\Feature;

use App\Mail\SignupCodeMail;
use App\Models\Hostel;
use App\Models\SubscriptionOrder;
use App\Models\User;
use App\Services\Auth\SignupVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Self-signup with email verification merged in: details → 6-digit code → account.
 * Nothing exists until the code is right.
 */
class SignupVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    protected function details(array $over = []): array
    {
        return array_merge([
            'name' => 'Asha Mehta', 'hostel_name' => 'Lotus Girls PG',
            'mobile' => '9812300001', 'email' => 'Asha@Example.com', 'password' => 'correct-horse',
        ], $over);
    }

    /** Step 1, returning the code that was emailed. */
    protected function startSignup(array $over = []): string
    {
        $this->post(route('register.attempt'), $this->details($over))->assertRedirect(route('register.verify'));

        return $this->lastCode();
    }

    protected function lastCode(): string
    {
        $code = null;
        Mail::assertSent(SignupCodeMail::class, function (SignupCodeMail $m) use (&$code) {
            $code = $m->code;

            return true;
        });

        return $code;
    }

    public function test_step_one_creates_nothing_and_emails_a_code(): void
    {
        $this->startSignup();

        $this->assertSame(0, User::count(), 'No account before the email is verified.');
        $this->assertSame(0, Hostel::count());

        Mail::assertSent(SignupCodeMail::class, fn (SignupCodeMail $m) => $m->hasTo('asha@example.com')
            && preg_match('/^\d{6}$/', $m->code) === 1);
    }

    public function test_the_right_code_creates_the_owner_branch_and_trial_and_signs_them_in(): void
    {
        $code = $this->startSignup();

        $this->post(route('register.verify.attempt'), ['code' => $code])->assertRedirect(route('dashboard'));

        $owner = User::where('mobile', '+919812300001')->sole();
        $this->assertSame('asha@example.com', $owner->email);
        $this->assertNotNull($owner->email_verified_at, 'The email is recorded as verified.');
        $this->assertAuthenticatedAs($owner);

        $hostel = Hostel::where('name', 'Lotus Girls PG')->sole();
        $this->assertSame($owner->id, $hostel->owner_id);
        $this->assertTrue($hostel->isActive());
        $this->assertSame(1, SubscriptionOrder::where('kind', 'trial')->count(), 'The account\'s one free trial.');
    }

    /** The password is hashed at step 1 and stored as that hash — never hashed twice. */
    public function test_the_owner_can_sign_in_with_the_password_they_chose(): void
    {
        $code = $this->startSignup();
        $this->post(route('register.verify.attempt'), ['code' => $code]);
        $this->post(route('logout'));

        $this->post(route('login.attempt'), ['mobile' => '9812300001', 'password' => 'correct-horse'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_the_plain_password_is_never_kept_in_the_session(): void
    {
        $this->startSignup();

        $pending = session('signup.pending');
        $this->assertArrayNotHasKey('password', $pending);
        $this->assertStringStartsWith('$2y$', $pending['password_hash']);
        $this->assertStringNotContainsString('correct-horse', json_encode($pending));
    }

    public function test_a_wrong_code_says_how_many_tries_are_left(): void
    {
        $code = $this->startSignup();
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->from(route('register.verify'))->post(route('register.verify.attempt'), ['code' => $wrong])
            ->assertRedirect(route('register.verify'))
            ->assertSessionHasErrors(['code' => 'That code is not right. 4 tries left.']);

        $this->assertSame(0, User::count());
    }

    public function test_five_wrong_codes_cancel_the_signup(): void
    {
        $code = $this->startSignup();
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, SignupVerification::MAX_ATTEMPTS) as $n) {
            $this->post(route('register.verify.attempt'), ['code' => $wrong]);
        }

        // Even the right code is useless now: the sign-up is gone.
        $this->post(route('register.verify.attempt'), ['code' => $code])->assertRedirect(route('register'));
        $this->assertSame(0, User::count());
        $this->assertNull(session('signup.pending'));
    }

    public function test_an_expired_code_is_refused(): void
    {
        $code = $this->startSignup();
        $this->travel(SignupVerification::CODE_TTL_MINUTES + 1)->minutes();

        $this->post(route('register.verify.attempt'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertSame(0, User::count());
    }

    public function test_a_new_code_waits_a_minute_and_replaces_the_old_one(): void
    {
        $first = $this->startSignup();

        $this->post(route('register.resend'))->assertSessionHasErrors('code');   // too soon
        Mail::assertSentCount(1);

        $this->travel(SignupVerification::RESEND_COOLDOWN_SECONDS + 1)->seconds();
        $this->post(route('register.resend'))->assertSessionHas('status');
        Mail::assertSentCount(2);

        $second = null;
        Mail::assertSent(SignupCodeMail::class, function (SignupCodeMail $m) use (&$second) {
            $second = $m->code;

            return true;
        });

        if ($first !== $second) {
            $this->post(route('register.verify.attempt'), ['code' => $first])->assertSessionHasErrors('code');
        }
        $this->post(route('register.verify.attempt'), ['code' => $second])->assertRedirect(route('dashboard'));
    }

    public function test_the_number_of_codes_is_capped(): void
    {
        $this->startSignup();

        foreach (range(2, SignupVerification::MAX_SENDS) as $n) {
            $this->travel(SignupVerification::RESEND_COOLDOWN_SECONDS + 1)->seconds();
            $this->post(route('register.resend'));
        }
        Mail::assertSentCount(SignupVerification::MAX_SENDS);

        $this->travel(SignupVerification::RESEND_COOLDOWN_SECONDS + 1)->seconds();
        $this->post(route('register.resend'))->assertRedirect(route('register'));
        Mail::assertSentCount(SignupVerification::MAX_SENDS);
    }

    public function test_a_mistyped_email_can_be_changed_without_starting_over(): void
    {
        $this->startSignup(['email' => 'typo@exampel.com']);

        $this->post(route('register.email'), ['email' => 'asha@example.com'])->assertRedirect(route('register.verify'));

        Mail::assertSent(SignupCodeMail::class, fn (SignupCodeMail $m) => $m->hasTo('asha@example.com'));
        $code = $this->lastCode();

        $this->post(route('register.verify.attempt'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->assertSame('asha@example.com', User::sole()->email);
    }

    public function test_a_mobile_or_email_that_already_has_an_account_is_refused_at_step_one(): void
    {
        User::factory()->create(['mobile' => '9812300001', 'email' => 'asha@example.com']);

        $this->post(route('register.attempt'), $this->details())
            ->assertSessionHasErrors(['mobile', 'email']);

        Mail::assertNothingSent();
    }

    /** Taken between step 1 and step 2 — checked again at the last moment. */
    public function test_a_mobile_registered_meanwhile_is_refused_at_verification(): void
    {
        $code = $this->startSignup();
        User::factory()->create(['mobile' => '9812300001']);

        $this->post(route('register.verify.attempt'), ['code' => $code])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('mobile');

        $this->assertSame(0, Hostel::count());
    }

    public function test_an_undeliverable_code_stops_the_signup_with_a_reason(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $this->post(route('register.attempt'), $this->details())->assertSessionHasErrors('email');

        $this->assertNull(session('signup.pending'), 'Nothing is parked when the code cannot be sent.');
    }

    public function test_the_details_are_validated(): void
    {
        $this->post(route('register.attempt'), $this->details(['mobile' => '12345', 'password' => 'short', 'email' => 'not-an-email']))
            ->assertSessionHasErrors(['mobile', 'password', 'email']);

        Mail::assertNothingSent();
    }

    public function test_the_verify_page_needs_a_signup_in_progress(): void
    {
        $this->get(route('register.verify'))->assertRedirect(route('register'));
    }

    /** The code is shown on screen ONLY on a local machine — never in tests or production. */
    public function test_the_verify_page_never_shows_the_code_outside_local(): void
    {
        $code = $this->startSignup();

        $this->get(route('register.verify'))
            ->assertOk()
            ->assertSee('a•', false)                          // the masked address
            ->assertDontSee('Local development')
            ->assertSee('autocomplete="one-time-code"', false);

        $this->assertNull(session('signup.dev_code'));
        $this->assertNotEmpty($code);
    }

    /**
     * The student self-registration link is `register/{token}` and is printed on
     * hostels' QR codes. Sign-up's step 2 first lived at register/verify and that
     * route swallowed it as a token called "verify" — so step 2 moved to signup/.
     * This pins both: a real QR link still works, and step 2 is not mistaken for one.
     */
    public function test_student_qr_links_and_signup_step_two_do_not_collide(): void
    {
        $hostel = Hostel::factory()->create();
        $token = $hostel->ensureRegistrationToken();

        $this->get(route('public.register', $token))->assertOk();
        $this->assertStringStartsWith(url('signup/'), route('register.verify'));
    }

    public function test_the_masked_email_hides_most_of_the_address(): void
    {
        $this->assertSame('a••••@example.com', SignupVerification::maskEmail('asha1@example.com'));
        $this->assertSame('b••@x.in', SignupVerification::maskEmail('b@x.in'));
    }

    public function test_the_signup_and_login_pages_render(): void
    {
        $this->get(route('register'))->assertOk()
            ->assertSee('name="email"', false)
            ->assertSee('Your details')
            ->assertSee('Verify email');

        $this->get(route('login'))->assertOk()
            ->assertSee('name="mobile"', false)
            ->assertSee('Welcome back');

        $this->get(route('recover'))->assertOk()->assertSee('Locked out?');
    }

    /** A failed sign-in is reported for the PAIR, above the form — and says nothing about which part was wrong. */
    public function test_a_failed_sign_in_is_reported_for_the_pair(): void
    {
        User::factory()->create(['mobile' => '9812300009']);

        $this->from(route('login'))->post(route('login.attempt'), ['mobile' => '9812300009', 'password' => 'wrong'])
            ->assertSessionHasErrors('credentials')
            ->assertSessionDoesntHaveErrors('mobile');

        $this->get(route('login'))->assertSee('do not match an active account');
    }
}
