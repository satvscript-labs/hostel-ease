<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Hostel;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentRegistration;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P5 — Aadhaar number at rest. The number is sensitive personal data (DPDP):
 * encrypted in the database, masked to last-4 in the UI, and only ever revealed
 * in full through an endpoint that writes an audit entry.
 */
class AadhaarPrivacyTest extends TestCase
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

    protected function makeStudent(array $attrs = []): Student
    {
        return Student::create(array_merge([
            'hostel_id' => $this->hostel->id, 'name' => 'Asha', 'mobile' => '+919800000002',
            'father_mobile' => '+919800000003', 'aadhaar' => '111122223333',
            'address' => 'X', 'city' => 'Y', 'state' => 'Z', 'occupation_type' => 'student',
            'college' => 'C', 'field_of_study' => 'F', 'join_date' => now(), 'status' => 'active',
        ], $attrs));
    }

    protected function makeStaff(array $attrs = []): Staff
    {
        return Staff::create(array_merge([
            'hostel_id' => $this->hostel->id, 'name' => 'Govind', 'designation' => 'Guard',
            'mobile' => '9800000001', 'monthly_salary' => 12000, 'is_active' => true,
            'aadhaar_number' => '123412341234',
        ], $attrs));
    }

    // ── Encryption at rest ───────────────────────────────────────────────

    public function test_the_three_aadhaar_columns_are_encrypted_at_rest(): void
    {
        $staff = $this->makeStaff();
        $student = $this->makeStudent();
        $reg = StudentRegistration::create([
            'hostel_id' => $this->hostel->id, 'name' => 'Applicant', 'mobile' => '+919800000009',
            'aadhaar' => '999988887777', 'occupation_type' => 'student', 'status' => 'pending',
        ]);

        // Model reads the plaintext back (transparent decrypt).
        $this->assertSame('123412341234', $staff->fresh()->aadhaar_number);
        $this->assertSame('111122223333', $student->fresh()->aadhaar);
        $this->assertSame('999988887777', $reg->fresh()->aadhaar);

        // The raw column holds ciphertext — never the plaintext number.
        foreach ([
            ['staff', 'aadhaar_number', $staff->id, '123412341234'],
            ['students', 'aadhaar', $student->id, '111122223333'],
            ['student_registrations', 'aadhaar', $reg->id, '999988887777'],
        ] as [$table, $column, $id, $plain]) {
            $raw = (string) DB::table($table)->where('id', $id)->value($column);
            $this->assertNotSame($plain, $raw, "$table.$column stored plaintext");
            $this->assertStringNotContainsString($plain, $raw, "$table.$column leaks plaintext");
            $this->assertTrue(strlen($raw) > 50, "$table.$column does not look encrypted");
        }
    }

    // ── Masking helper ───────────────────────────────────────────────────

    public function test_the_mask_shows_only_the_last_four_digits(): void
    {
        $this->assertSame('XXXX XXXX 9012', hostelease_mask_aadhaar('123456789012'));
        $this->assertSame('XXXX XXXX 9012', hostelease_mask_aadhaar('1234 5678 9012'));
        $this->assertSame('—', hostelease_mask_aadhaar(null));
        $this->assertSame('—', hostelease_mask_aadhaar(''));

        $this->assertSame('1234 5678 9012', hostelease_aadhaar_groups('123456789012'));
        $this->assertSame('—', hostelease_aadhaar_groups(null));
    }

    // ── Logged reveal ────────────────────────────────────────────────────

    public function test_revealing_staff_aadhaar_returns_the_full_number_and_logs_it(): void
    {
        $staff = $this->makeStaff();

        $this->getJson(route('admin.staff.aadhaar', $staff->id))
            ->assertOk()
            ->assertJson(['aadhaar' => '1234 1234 1234']);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'aadhaar.reveal', 'user_id' => $this->admin->id,
            'subject_type' => (new Staff)->getMorphClass(), 'subject_id' => $staff->id,
        ]);
    }

    public function test_revealing_student_aadhaar_returns_the_full_number_and_logs_it(): void
    {
        $student = $this->makeStudent();

        $this->getJson(route('admin.students.aadhaar', $student->id))
            ->assertOk()
            ->assertJson(['aadhaar' => '1111 2222 3333']);

        $this->assertSame(1, ActivityLog::where('action', 'aadhaar.reveal')
            ->where('subject_id', $student->id)->count());
    }

    public function test_reveal_is_tenant_scoped(): void
    {
        $otherHostel = Hostel::factory()->create();
        $otherStaff = Staff::create([
            'hostel_id' => $otherHostel->id, 'name' => 'Outsider', 'mobile' => '9800000099',
            'monthly_salary' => 1, 'is_active' => true, 'aadhaar_number' => '555566667777',
        ]);

        // Bound within the acting admin's tenant — the other hostel's staff is invisible.
        $this->getJson(route('admin.staff.aadhaar', $otherStaff->id))->assertNotFound();
        $this->assertDatabaseMissing('activity_logs', ['action' => 'aadhaar.reveal', 'subject_id' => $otherStaff->id]);
    }

    // ── Edit forms: blank keeps the stored number ────────────────────────

    public function test_editing_staff_with_a_blank_aadhaar_keeps_the_stored_number(): void
    {
        $staff = $this->makeStaff(['aadhaar_file' => "staff/{$this->hostel->id}/aadhaar/x.webp"]);

        $this->put(route('admin.staff.update', $staff), [
            'name' => 'Govind', 'mobile' => '9800000001', 'monthly_salary' => 15000,
            'aadhaar_number' => '',
        ])->assertRedirect();

        $this->assertSame('123412341234', $staff->fresh()->aadhaar_number);
        $this->assertEquals(15000, (float) $staff->fresh()->monthly_salary); // the rest still saved
    }

    public function test_editing_staff_with_a_new_aadhaar_replaces_it(): void
    {
        $staff = $this->makeStaff(['aadhaar_file' => "staff/{$this->hostel->id}/aadhaar/x.webp"]);

        $this->put(route('admin.staff.update', $staff), [
            'name' => 'Govind', 'mobile' => '9800000001', 'monthly_salary' => 12000,
            'aadhaar_number' => '4321 4321 4321',
        ])->assertRedirect();

        $this->assertSame('432143214321', $staff->fresh()->aadhaar_number);
    }

    public function test_editing_a_student_with_a_blank_aadhaar_keeps_the_stored_number(): void
    {
        $student = $this->makeStudent(['aadhaar_file' => "students/{$this->hostel->id}/aadhaar/x.webp"]);

        $this->put(route('admin.students.update', $student), [
            'name' => 'Asha', 'mobile' => '9800000002', 'father_mobile' => '9800000003',
            'address' => 'X', 'city' => 'Y', 'state' => 'Z', 'occupation_type' => 'student',
            'college' => 'C', 'field_of_study' => 'F', 'join_date' => now()->toDateString(),
            'status' => 'active', 'aadhaar' => '',
        ])->assertRedirect();

        $this->assertSame('111122223333', $student->fresh()->aadhaar);
    }
}
