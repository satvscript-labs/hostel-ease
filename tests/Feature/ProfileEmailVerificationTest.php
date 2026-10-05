<?php

namespace Tests\Feature;

use App\Mail\EmailCodeMail;
use App\Mail\StaffInviteMail;
use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The profile email is proven by a 6-digit code before it is saved, and a team
 * member's email is confirmed by a signed link. An address is never shown as verified
 * unless the person behind it proved it.
 */
class ProfileEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
        Mail::fake();
    }

    protected function owner(array $attrs = []): User
    {
        $owner = User::factory()->create(array_merge(['role' => 'hostel_admin', 'mobile' => '9800000001', 'email' => null, 'email_verified_at' => null], $attrs));
        $hostel = Hostel::factory()->create([
            'mobile' => '9800000001', 'owner_id' => $owner->id,
            'status' => 'active', 'subscription_end' => now()->addMonths(6),
        ]);
        $owner->hostels()->sync([$hostel->id]);
        $owner->forceFill(['hostel_id' => $hostel->id])->save();
        SubscriptionAccount::create(['owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addMonths(6)]);

        return $owner;
    }

    /** Ask for a code and return what was emailed. */
    protected function requestCode(User $owner, string $email): string
    {
        $this->actingAs($owner)->post(route('profile.email.send'), ['email' => $email])->assertSessionHasNoErrors();

        $email = mb_strtolower($email); // the address is stored and mailed lower-case
        $code = null;
        Mail::assertSent(EmailCodeMail::class, function (EmailCodeMail $m) use (&$code, $email) {
            if ($m->hasTo($email)) {
                $code = $m->code;
            }

            return $m->hasTo($email);
        });

        return $code;
    }

    // ── the owner's own email ───────────────────────────────────────────

    public function test_asking_for_a_code_sends_it_and_changes_nothing_yet(): void
    {
        $owner = $this->owner(['email' => 'old@example.com', 'email_verified_at' => now()]);

        $this->requestCode($owner, 'New@Example.com');

        $owner->refresh();
        $this->assertSame('old@example.com', $owner->email, 'The address is not saved until the code is right.');
        $this->assertNotNull($owner->email_verified_at);
    }

    public function test_the_right_code_saves_the_address_as_verified(): void
    {
        $owner = $this->owner();
        $code = $this->requestCode($owner, 'owner@example.com');

        $this->actingAs($owner)->post(route('profile.email.verify'), ['code' => $code])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'profile']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $owner->refresh();
        $this->assertSame('owner@example.com', $owner->email);
        $this->assertNotNull($owner->email_verified_at);
    }

    public function test_a_wrong_code_saves_nothing_and_five_wrong_codes_cancel_it(): void
    {
        $owner = $this->owner();
        $code = $this->requestCode($owner, 'owner@example.com');
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, 5) as $i) {
            $this->actingAs($owner)->post(route('profile.email.verify'), ['code' => $wrong])->assertSessionHasErrors('code', null, 'emailFlow');
        }

        // The request is gone: even the right code no longer works.
        $this->actingAs($owner)->post(route('profile.email.verify'), ['code' => $code])->assertSessionHasErrors('code', null, 'emailFlow');
        $this->assertNull($owner->fresh()->email);
        $this->assertNull($owner->fresh()->email_verified_at);
    }

    public function test_an_address_another_account_uses_is_refused_even_if_that_account_was_removed(): void
    {
        $owner = $this->owner();
        User::factory()->create(['email' => 'taken@example.com'])->delete(); // soft-deleted rows still hold the unique index

        $this->actingAs($owner)->post(route('profile.email.send'), ['email' => 'taken@example.com'])
            ->assertSessionHasErrors('email', null, 'emailFlow');

        Mail::assertNothingSent();
    }

    public function test_an_unverified_address_can_be_verified_in_place(): void
    {
        $owner = $this->owner(['email' => 'legacy@example.com', 'email_verified_at' => null]);
        $code = $this->requestCode($owner, 'legacy@example.com');

        $this->actingAs($owner)->post(route('profile.email.verify'), ['code' => $code])->assertSessionHasNoErrors();

        $this->assertNotNull($owner->fresh()->email_verified_at);
        $this->assertSame('legacy@example.com', $owner->fresh()->email);
    }

    public function test_a_verified_address_is_not_re_verified(): void
    {
        $owner = $this->owner(['email' => 'done@example.com', 'email_verified_at' => now()]);

        $this->actingAs($owner)->post(route('profile.email.send'), ['email' => 'done@example.com'])
            ->assertSessionHasErrors('email', null, 'emailFlow');
        Mail::assertNothingSent();
    }

    public function test_a_code_only_works_for_the_person_it_was_sent_to(): void
    {
        $owner = $this->owner();
        $code = $this->requestCode($owner, 'owner@example.com');

        $other = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9800000002', 'email' => null]);
        $this->actingAs($other)->post(route('profile.email.verify'), ['code' => $code])->assertSessionHasErrors('code', null, 'emailFlow');
        $this->assertNull($other->fresh()->email);
    }

    public function test_the_profile_form_no_longer_saves_an_email_directly(): void
    {
        $owner = $this->owner(['email' => 'old@example.com', 'email_verified_at' => now()]);

        $this->actingAs($owner)->put(route('profile.update'), ['name' => 'Renamed', 'email' => 'sneaky@example.com'])->assertRedirect();

        $this->assertSame('Renamed', $owner->fresh()->name);
        $this->assertSame('old@example.com', $owner->fresh()->email);
        $this->assertNotNull($owner->fresh()->email_verified_at);
    }

    public function test_the_profile_shows_whether_the_email_is_verified(): void
    {
        $verified = $this->owner(['email' => 'ok@example.com', 'email_verified_at' => now()]);
        $this->actingAs($verified)->get(route('admin.settings.index'))->assertOk()->assertSee('Email verified')->assertSee('ok@example.com');

        $unverified = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9800000003', 'email' => 'no@example.com', 'email_verified_at' => null]);
        $hostel = Hostel::factory()->create(['mobile' => '9800000003', 'owner_id' => $unverified->id, 'status' => 'active', 'subscription_end' => now()->addMonths(3)]);
        $unverified->hostels()->sync([$hostel->id]);
        $unverified->forceFill(['hostel_id' => $hostel->id])->save();
        SubscriptionAccount::create(['owner_id' => $unverified->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addMonths(3)]);

        $this->actingAs($unverified)->get(route('admin.settings.index'))->assertOk()->assertSee('Not verified')->assertSee('Verify your email');
    }

    // ── a team member's email ───────────────────────────────────────────

    protected function addStaff(User $owner, array $extra = [])
    {
        return $this->actingAs($owner)->post(route('admin.users.store'), array_merge([
            'name' => 'Asha Warden', 'mobile' => '9811111111', 'role' => 'warden',
            'branches' => $owner->hostels->pluck('id')->all(),
        ], $extra));
    }

    public function test_staff_added_with_an_email_get_an_invite_and_start_unverified(): void
    {
        $owner = $this->owner();

        $this->addStaff($owner, ['email' => 'Asha@Example.com'])->assertSessionHas('credentials');

        $staff = User::where('mobile', '+919811111111')->sole();
        $this->assertSame('asha@example.com', $staff->email);
        $this->assertNull($staff->email_verified_at);
        Mail::assertSent(StaffInviteMail::class, fn (StaffInviteMail $m) => $m->hasTo('asha@example.com'));
    }

    public function test_staff_without_an_email_are_still_created_and_nothing_is_sent(): void
    {
        $owner = $this->owner();

        $this->addStaff($owner)->assertSessionHas('credentials');

        $this->assertNull(User::where('mobile', '+919811111111')->sole()->email);
        Mail::assertNothingSent();
    }

    public function test_a_duplicate_staff_email_is_refused(): void
    {
        $owner = $this->owner();
        User::factory()->create(['email' => 'asha@example.com']);

        $this->addStaff($owner, ['email' => 'asha@example.com'])->assertSessionHasErrors('email');
        $this->assertSame(0, User::where('mobile', '+919811111111')->count());
    }

    public function test_the_invite_link_verifies_the_address_once_and_only_that_address(): void
    {
        $owner = $this->owner();
        $this->addStaff($owner, ['email' => 'asha@example.com']);
        $staff = User::where('mobile', '+919811111111')->sole();
        $link = StaffInviteMail::linkFor($staff);

        $this->get($link)->assertRedirect(route('login'))->assertSessionHas('success');
        $this->assertNotNull($staff->fresh()->email_verified_at);
    }

    public function test_a_forged_or_unsigned_invite_link_verifies_nothing(): void
    {
        $owner = $this->owner();
        $this->addStaff($owner, ['email' => 'asha@example.com']);
        $staff = User::where('mobile', '+919811111111')->sole();

        $this->get(route('email.confirm', ['user' => $staff->public_id, 'hash' => sha1('asha@example.com')]))->assertForbidden();
        $this->get(StaffInviteMail::linkFor($staff).'x')->assertForbidden();
        $this->assertNull($staff->fresh()->email_verified_at);
    }

    public function test_changing_the_email_unverifies_it_and_kills_the_old_link(): void
    {
        $owner = $this->owner();
        $this->addStaff($owner, ['email' => 'asha@example.com']);
        $staff = User::where('mobile', '+919811111111')->sole();
        $oldLink = StaffInviteMail::linkFor($staff);
        $this->get($oldLink);
        $this->assertNotNull($staff->fresh()->email_verified_at);

        $this->actingAs($owner)->put(route('admin.users.update', $staff), [
            'name' => $staff->name, 'role' => 'warden', 'branches' => $owner->hostels->pluck('id')->all(),
            'email' => 'asha.new@example.com', 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $staff->refresh();
        $this->assertSame('asha.new@example.com', $staff->email);
        $this->assertNull($staff->email_verified_at, 'A new address starts unproven.');
        Mail::assertSent(StaffInviteMail::class, fn (StaffInviteMail $m) => $m->hasTo('asha.new@example.com'));

        $this->get($oldLink); // signed fine, but bound to the old address
        $this->assertNull($staff->fresh()->email_verified_at);
    }

    public function test_saving_staff_without_touching_the_email_keeps_it_verified(): void
    {
        $owner = $this->owner();
        $this->addStaff($owner, ['email' => 'asha@example.com']);
        $staff = User::where('mobile', '+919811111111')->sole();
        $this->get(StaffInviteMail::linkFor($staff));

        $this->actingAs($owner)->put(route('admin.users.update', $staff), [
            'name' => 'Asha Renamed', 'role' => 'manager', 'branches' => $owner->hostels->pluck('id')->all(),
            'email' => 'ASHA@example.com', 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($staff->fresh()->email_verified_at);
    }

    public function test_the_owner_can_resend_a_verification_but_only_for_their_own_team(): void
    {
        $owner = $this->owner();
        $this->addStaff($owner, ['email' => 'asha@example.com']);
        $staff = User::where('mobile', '+919811111111')->sole();
        Mail::fake(); // forget the invite; count only the resend

        $this->actingAs($owner)->post(route('admin.users.verification', $staff))->assertSessionHas('success');
        Mail::assertSent(StaffInviteMail::class, 1);

        $stranger = $this->owner2();
        $this->actingAs($stranger)->post(route('admin.users.verification', $staff))->assertForbidden();
    }

    public function test_the_team_list_shows_each_members_email_state(): void
    {
        $owner = $this->owner();
        $this->addStaff($owner, ['email' => 'asha@example.com']);

        $this->actingAs($owner)->get(route('admin.settings.index', ['tab' => 'users']))
            ->assertOk()->assertSee('asha@example.com')->assertSee('not verified');
    }

    protected function owner2(): User
    {
        $o = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9800000009']);
        $h = Hostel::factory()->create(['mobile' => '9800000009', 'owner_id' => $o->id, 'status' => 'active', 'subscription_end' => now()->addMonths(3)]);
        $o->hostels()->sync([$h->id]);
        $o->forceFill(['hostel_id' => $h->id])->save();
        SubscriptionAccount::create(['owner_id' => $o->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addMonths(3)]);

        return $o;
    }
}
