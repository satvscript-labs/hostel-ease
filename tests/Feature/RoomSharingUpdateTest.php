<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\User;
use App\Services\BedGenerator;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomSharingUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected User $admin;
    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hostel = Hostel::factory()->create();
        $this->admin = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);

        $floor = Floor::create(['hostel_id' => $this->hostel->id, 'name' => 'Ground Floor']);
        $this->room = Room::create([
            'hostel_id' => $this->hostel->id, 'floor_id' => $floor->id,
            'room_number' => '102', 'room_type' => 'ac', 'sharing' => 3,
        ]);
        app(BedGenerator::class)->sync($this->room);
    }

    /** The exact request the builder's save button sends. */
    protected function putSharing(int $sharing)
    {
        return $this->actingAs($this->admin)
            ->putJson("/admin/rooms/{$this->room->id}", [
                'floor_id' => $this->room->floor_id,
                'room_number' => $this->room->room_number,
                'room_type' => $this->room->room_type,
                'sharing' => $sharing,
            ]);
    }

    public function test_grow_sharing_via_http(): void
    {
        $this->putSharing(6)->assertOk()->assertJson(['success' => true]);

        $this->assertSame(6, $this->room->fresh()->sharing);
        $this->assertSame(6, $this->room->beds()->count());
    }

    public function test_shrink_then_grow_does_not_violate_unique_constraint(): void
    {
        // This is the sequence that produced SQLSTATE[23000] before the fix:
        // shrink soft-deletes beds, growing back must restore them, not re-insert.
        $this->putSharing(1)->assertOk();
        $this->assertSame(1, $this->room->fresh()->beds()->count());

        $this->putSharing(8)->assertOk()->assertJson(['success' => true]);
        $this->assertSame(8, $this->room->fresh()->sharing);
        $this->assertSame(8, $this->room->fresh()->beds()->count());
    }

    public function test_repeated_shrink_grow_cycles_stay_consistent(): void
    {
        foreach ([2, 5, 1, 7, 3, 6] as $n) {
            $this->putSharing($n)->assertOk();
            $this->assertSame($n, $this->room->fresh()->sharing, "sharing should be {$n}");
            $this->assertSame($n, $this->room->fresh()->beds()->count(), "bed count should be {$n}");
        }
    }
}
