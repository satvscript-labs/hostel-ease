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

    public function test_fee_settings_update_returns_json_when_requested(): void
    {
        // Matches the Property Board's fee-plan gate, which fetch()es this
        // endpoint with an XHR (Accept: application/json) instead of a
        // normal form submission.
        $student = Student::create(['hostel_id' => $this->hostel->id, 'name' => 'Gate Test',
            'mobile' => '9222222222', 'occupation_type' => 'student', 'status' => 'active']);

        $this->actingAs($this->admin)->putJson(route('admin.students.fee-settings.update', $student), [
            'fee_frequency' => 'monthly',
            'fee_amount' => 6000,
        ])->assertOk()->assertJson(['success' => true]);

        $student->refresh();
        $this->assertSame('monthly', $student->fee_frequency);
        $this->assertEquals(6000, (float) $student->fee_amount);
    }

    public function test_fee_settings_update_returns_json_validation_errors(): void
    {
        $student = Student::create(['hostel_id' => $this->hostel->id, 'name' => 'Gate Test 2',
            'mobile' => '9333333333', 'occupation_type' => 'student', 'status' => 'active']);

        $this->actingAs($this->admin)->putJson(route('admin.students.fee-settings.update', $student), [
            'fee_frequency' => 'monthly',
            // fee_amount omitted — required.
        ])->assertStatus(422)->assertJsonValidationErrors('fee_amount');

        $student->refresh();
        $this->assertNull($student->fee_frequency);
    }

    public function test_fee_settings_update_still_redirects_for_normal_form_submission(): void
    {
        $student = Student::create(['hostel_id' => $this->hostel->id, 'name' => 'Gate Test 3',
            'mobile' => '9444444444', 'occupation_type' => 'student', 'status' => 'active']);

        // Matches the existing "Change Fee & Room Plan" modal on the student
        // profile, which still submits as a normal (non-AJAX) form.
        $this->actingAs($this->admin)->put(route('admin.students.fee-settings.update', $student), [
            'fee_frequency' => 'yearly',
            'fee_amount' => 50000,
        ])->assertRedirect(route('admin.students.show', $student));
    }
}
