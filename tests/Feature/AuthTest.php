<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** H3 — self-service signup must provision the tenant fully. */
    public function test_self_signup_provisions_owner_payment_modes_and_account(): void
    {
        $this->post('/register', [
            'name' => 'New Owner', 'hostel_name' => 'Fresh PG',
            'mobile' => '9812345678', 'password' => 'secret123',
        ])->assertRedirect();

        $owner = User::where('mobile', '+919812345678')->firstOrFail();
        $hostel = \App\Models\Hostel::where('name', 'Fresh PG')->firstOrFail();

        // Explicit owner FK, default payment modes (so the owner can record a
        // payment immediately), and the account billing spine — all provisioned.
        $this->assertSame($owner->id, $hostel->owner_id);
        $this->assertGreaterThan(0, \App\Models\PaymentMode::where('hostel_id', $hostel->id)->count());
        $this->assertNotNull(\App\Models\SubscriptionAccount::where('owner_id', $owner->id)->first());
    }

    public function test_login_screen_can_be_rendered(): void
    {
        // Asserts the form, not the brand wording: this used to look for
        // "Hostel Management", which the page stopped saying when the title
        // moved to config('app.name') — a copy edit shouldn't fail a test
        // about whether the login screen works.
        $this->get('/login')->assertOk()->assertSee('name="mobile"', false);
    }

    public function test_super_admin_can_authenticate_with_mobile(): void
    {
        $user = User::factory()->create([
            'mobile' => '9999999999',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        // Stored normalised (+919999999999) by the model, so a bare 10-digit
        // login still resolves — that mismatch is what locked provisioned
        // owners out of their own accounts before W6.3-followup.
        $this->assertSame('+919999999999', $user->fresh()->mobile);

        $this->post('/login', [
            'mobile' => '9999999999',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    /** However it's typed, it's the same login. */
    public function test_mobile_is_normalised_however_it_is_entered(): void
    {
        $user = User::factory()->create(['mobile' => '+91 99999 99999', 'is_active' => true]);
        $this->assertSame('+919999999999', $user->fresh()->mobile);

        $this->post('/login', ['mobile' => '099999 99999', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        // Must exist AND be findable — before normalisation this passed for
        // the wrong reason (the lookup never matched, so is_active was never
        // the thing under test).
        $user = User::factory()->create([
            'mobile' => '8888888888',
            'is_active' => false,
        ]);
        $this->assertSame('+918888888888', $user->fresh()->mobile);

        $this->post('/login', [
            'mobile' => '8888888888',
            'password' => 'password',
        ])->assertSessionHasErrors('mobile');

        $this->assertGuest();
    }
}
