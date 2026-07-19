<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\Student;
use App\Services\BedGenerator;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The StudentFactory + `hostelease:make-students` command exist so a fresh,
 * complete student profile is one call away. Guard the two properties that
 * matter: every MANDATORY field is filled with valid data, and the Aadhaar is
 * encrypted at rest with the base card/photo attached (so the profile renders
 * like a real intake).
 */
class MakeTestStudentsTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->hostel = Hostel::factory()->create();
        Tenant::set($this->hostel->id);
    }

    public function test_the_factory_fills_every_mandatory_field_with_valid_data(): void
    {
        $s = Student::factory()->create(['hostel_id' => $this->hostel->id]);

        $this->assertNotEmpty($s->name);
        $this->assertMatchesRegularExpression('/^\+91\d{10}$/', $s->mobile);
        $this->assertMatchesRegularExpression('/^\+91\d{10}$/', $s->father_mobile);
        $this->assertMatchesRegularExpression('/^\d{12}$/', $s->aadhaar);
        $this->assertNotEmpty($s->address);
        $this->assertNotEmpty($s->city);
        $this->assertNotEmpty($s->state);
        $this->assertContains($s->occupation_type, ['student', 'working']);
        $this->assertNotNull($s->join_date);
        $this->assertSame('active', $s->status);

        // A student must carry college + field of study; a working professional must not.
        if ($s->occupation_type === 'student') {
            $this->assertNotEmpty($s->college);
            $this->assertNotEmpty($s->field_of_study);
        }

        // Fresh by default — no bed, no plan, no invoices.
        $this->assertNull($s->fee_amount);
        $this->assertSame(0, $s->invoices()->count());
    }

    public function test_the_command_creates_students_with_encrypted_aadhaar_and_base_docs(): void
    {
        $this->artisan('hostelease:make-students', ['count' => 4, '--hostel' => $this->hostel->id])
            ->assertSuccessful();

        $students = Student::where('hostel_id', $this->hostel->id)->get();
        $this->assertCount(4, $students);

        foreach ($students as $s) {
            // Aadhaar reads back as 12 digits but is ciphertext in the column (P5).
            $this->assertMatchesRegularExpression('/^\d{12}$/', $s->aadhaar);
            $raw = (string) DB::table('students')->where('id', $s->id)->value('aadhaar');
            $this->assertStringNotContainsString($s->aadhaar, $raw);
            // Base card + photo attached → the Documents tab renders them.
            $this->assertNotNull($s->aadhaar_file);
            $this->assertNotNull($s->photo);
            Storage::disk('private')->assertExists($s->aadhaar_file);
        }
    }

    public function test_the_command_can_place_students_into_vacant_beds(): void
    {
        $floor = Floor::create(['hostel_id' => $this->hostel->id, 'name' => 'GF']);
        $room = Room::create(['hostel_id' => $this->hostel->id, 'floor_id' => $floor->id,
            'room_number' => '101', 'room_type' => 'non_ac', 'sharing' => 3, 'rent' => 5000]);
        app(BedGenerator::class)->sync($room);

        $this->artisan('hostelease:make-students', ['count' => 2, '--hostel' => $this->hostel->id, '--assign' => true])
            ->assertSuccessful();

        $placed = Student::where('hostel_id', $this->hostel->id)->get()
            ->filter(fn ($s) => $s->activeAssignment()->exists());

        $this->assertCount(2, $placed);
        // Assignment raised the first invoice (rent is for a bed), dated from join.
        foreach ($placed as $s) {
            $this->assertSame(1, $s->invoices()->count());
        }
    }
}
