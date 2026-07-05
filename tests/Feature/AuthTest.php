<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk()->assertSee('Hostel Management');
    }

    public function test_super_admin_can_authenticate_with_mobile(): void
    {
        $user = User::factory()->create([
            'mobile' => '9999999999',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->post('/login', [
            'mobile' => '9999999999',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        User::factory()->create([
            'mobile' => '8888888888',
            'is_active' => false,
        ]);

        $this->post('/login', [
            'mobile' => '8888888888',
            'password' => 'password',
        ])->assertSessionHasErrors('mobile');

        $this->assertGuest();
    }
}
