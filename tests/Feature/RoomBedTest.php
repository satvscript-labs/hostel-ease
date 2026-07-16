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

class RoomBedTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hostel = Hostel::factory()->create();
        $this->admin = User::factory()->create([
            'hostel_id' => $this->hostel->id,
            'role' => 'hostel_admin',
        ]);
        Tenant::set($this->hostel->id);
    }

    public function test_creating_a_room_generates_matching_beds(): void
    {
        $floor = Floor::create(['hostel_id' => $this->hostel->id, 'name' => 'Ground Floor']);

        $this->actingAs($this->admin)->post(route('admin.rooms.store'), [
            'floor_id' => $floor->id,
            'room_number' => '101',
            'room_type' => 'non_ac',
            'sharing' => 3,
            'rent' => 5000,
        ])->assertRedirect(route('admin.property.index'));

        $room = Room::where('room_number', '101')->firstOrFail();
        $this->assertSame(3, $room->beds()->count());
        $this->assertEqualsCanonicalizing(['B1', 'B2', 'B3'], $room->beds()->pluck('bed_number')->all());
    }

    public function test_increasing_sharing_adds_beds(): void
    {
        $floor = Floor::create(['hostel_id' => $this->hostel->id, 'name' => 'Ground Floor']);
        $room = Room::create([
            'hostel_id' => $this->hostel->id, 'floor_id' => $floor->id,
            'room_number' => '102', 'room_type' => 'non_ac', 'sharing' => 2, 'rent' => 4000,
        ]);
        app(BedGenerator::class)->sync($room);

        $room->update(['sharing' => 5]);
        $result = app(BedGenerator::class)->sync($room);

        $this->assertSame(3, $result['created']);
        $this->assertSame(5, $room->beds()->count());
    }

    public function test_reducing_sharing_keeps_occupied_beds(): void
    {
        $floor = Floor::create(['hostel_id' => $this->hostel->id, 'name' => 'Ground Floor']);
        $room = Room::create([
            'hostel_id' => $this->hostel->id, 'floor_id' => $floor->id,
            'room_number' => '103', 'room_type' => 'non_ac', 'sharing' => 4, 'rent' => 4000,
        ]);
        app(BedGenerator::class)->sync($room);

        // Occupy the highest bed (B4) — it must be preserved when shrinking.
        $room->beds()->where('bed_number', 'B4')->update(['status' => 'occupied']);

        $room->update(['sharing' => 2]);
        $result = app(BedGenerator::class)->sync($room);

        $this->assertSame(1, $result['keptBlocked']);
        $this->assertTrue($room->beds()->where('bed_number', 'B4')->exists());
    }
}
