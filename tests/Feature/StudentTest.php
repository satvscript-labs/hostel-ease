<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\Student;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hostel = Hostel::factory()->create();
        $this->admin = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);
    }

    public function test_admin_can_create_a_student_with_photo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->post(route('admin.students.store'), [
            'name' => 'Test Student',
            'mobile' => '98765 43210',          // formatting stripped to 10 digits
            'occupation_type' => 'student',
            'status' => 'active',
            'photo' => UploadedFile::fake()->image('avatar.jpg'),
        ])->assertRedirect();

        $student = Student::firstOrFail();
        $this->assertSame('9876543210', $student->mobile);
        $this->assertNotNull($student->photo);
        Storage::disk('public')->assertExists($student->photo);
    }

    public function test_invalid_mobile_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.students.store'), [
            'name' => 'Bad Mobile',
            'mobile' => '123',
            'occupation_type' => 'student',
            'status' => 'active',
        ])->assertSessionHasErrors('mobile');
    }

    public function test_students_are_scoped_to_the_tenant(): void
    {
        $other = Hostel::factory()->create();
        Student::create(['hostel_id' => $other->id, 'name' => 'Other Hostel Student', 'mobile' => '9000000000', 'occupation_type' => 'student', 'status' => 'active']);
        Student::create(['hostel_id' => $this->hostel->id, 'name' => 'My Student', 'mobile' => '9111111111', 'occupation_type' => 'student', 'status' => 'active']);

        // Tenant bound to $this->hostel — only its student is visible.
        $this->assertSame(1, Student::count());
        $this->assertSame('My Student', Student::first()->name);
    }
}
