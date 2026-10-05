<?php

namespace Tests\Feature\Presence;

use App\Models\Hostel;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Presence is a premium add-on that is not on sale yet. config('presence.enabled')
 * switches the whole module: when it is off, nobody reaches the gate-device code and
 * the roles it is for see a "Coming soon" page instead.
 */
class PresenceSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $hostel = Hostel::factory()->create();
        $this->owner = User::factory()->create(['hostel_id' => $hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($hostel->id);
    }

    public function test_the_module_is_off_unless_the_env_says_otherwise(): void
    {
        // The shipped default (what production gets with no PRESENCE_ENABLED line).
        $this->assertStringContainsString("env('PRESENCE_ENABLED', false)", file_get_contents(config_path('presence.php')));
    }

    public function test_every_presence_url_lands_on_coming_soon_when_off(): void
    {
        config(['presence.enabled' => false]);

        foreach (['students', 'staff', 'log', 'devices', 'muster'] as $page) {
            $this->actingAs($this->owner)->get('/admin/presence/'.$page)
                ->assertRedirect(route('admin.presence.soon'));
        }

        $this->actingAs($this->owner)->post('/admin/presence/devices', ['name' => 'x'])
            ->assertRedirect(route('admin.presence.soon'));
        $this->assertDatabaseCount('presence_devices', 0);
    }

    public function test_the_coming_soon_page_renders_and_the_sidebar_shows_a_teaser(): void
    {
        config(['presence.enabled' => false]);

        $this->actingAs($this->owner)->get(route('admin.presence.soon'))
            ->assertOk()
            ->assertSee('Presence is coming soon')
            ->assertSee('Premium')
            ->assertSee('Soon')                                   // the sidebar badge
            ->assertDontSee(route('admin.presence.devices'), false);   // no module links
    }

    public function test_presence_data_and_reports_stay_hidden_when_off(): void
    {
        config(['presence.enabled' => false]);

        $this->assertFalse($this->owner->canAccessPresence());
        $this->assertTrue($this->owner->seesPresenceTeaser());

        $this->actingAs($this->owner)->get(route('admin.reports.show', 'presence_time_out'))->assertForbidden();
    }

    public function test_roles_the_module_is_not_for_see_neither_the_module_nor_the_teaser(): void
    {
        config(['presence.enabled' => false]);
        $viewer = User::factory()->create(['hostel_id' => $this->owner->hostel_id, 'role' => 'viewer']);

        $this->assertFalse($viewer->seesPresenceTeaser());
        $this->actingAs($viewer)->get('/admin/presence/students')->assertRedirect(route('admin.dashboard'));
    }

    public function test_switching_it_on_restores_the_module(): void
    {
        config(['presence.enabled' => true]);

        $this->assertTrue($this->owner->canAccessPresence());
        $this->assertFalse($this->owner->seesPresenceTeaser());
        $this->actingAs($this->owner)->get('/admin/presence/students')->assertOk();
    }
}
