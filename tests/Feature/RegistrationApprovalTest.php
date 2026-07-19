<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\Student;
use App\Models\StudentRegistration;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationApprovalTest extends TestCase
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
        $this->actingAs($this->admin);
    }

    /**
     * D2 (private-disk). The public form requires an Aadhaar upload; before
     * this, approve() copied only `photo` and the Aadhaar was stored, never
     * read, and eventually orphaned — a stranger's national ID kept for nothing.
     * Now it becomes the student's Aadhaar on record.
     */
    public function test_approving_carries_the_aadhaar_file_to_the_student(): void
    {
        $registration = StudentRegistration::create([
            'hostel_id' => $this->hostel->id,
            'name' => 'Aarti Shah', 'mobile' => '+919800000001', 'father_mobile' => '+919800000002',
            'aadhaar' => '123456789012', 'address' => '1 Road', 'city' => 'Ahmedabad', 'state' => 'Gujarat',
            'occupation_type' => 'working', 'joining_date' => now()->toDateString(),
            'photo' => 'registrations/'.$this->hostel->id.'/photos/face.webp',
            'aadhaar_file' => 'registrations/'.$this->hostel->id.'/aadhaar/card.webp',
            'status' => 'pending',
        ]);

        $this->post(route('admin.registrations.approve', $registration))
            ->assertRedirect()->assertSessionHas('success');

        $student = Student::where('name', 'Aarti Shah')->firstOrFail();
        $this->assertSame($registration->photo, $student->photo);
        // The line that was missing.
        $this->assertSame($registration->aadhaar_file, $student->aadhaar_file);
    }

    /** H5b — the public form requires college/field for students; approval must carry them. */
    public function test_approving_a_student_carries_the_academic_fields(): void
    {
        $registration = StudentRegistration::create([
            'hostel_id' => $this->hostel->id,
            'name' => 'Bhavya Patel', 'mobile' => '+919800000011', 'father_mobile' => '+919800000012',
            'aadhaar' => '223344556677', 'address' => '2 Road', 'city' => 'Surat', 'state' => 'Gujarat',
            'occupation_type' => 'student', 'college' => 'Nirma University', 'field_of_study' => 'Computer Engineering',
            'joining_date' => now()->toDateString(), 'status' => 'pending',
        ]);

        $this->post(route('admin.registrations.approve', $registration))->assertRedirect()->assertSessionHas('success');

        $student = Student::where('name', 'Bhavya Patel')->firstOrFail();
        $this->assertSame('student', $student->occupation_type);
        $this->assertSame('Nirma University', $student->college);
        $this->assertSame('Computer Engineering', $student->field_of_study);
    }
}
