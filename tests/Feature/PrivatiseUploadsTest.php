<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\StudentRegistration;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P3: the copy-verify-rewrite migration command. The cases that matter are the
 * ones the plan was redesigned around — one file shared by two rows (§3.2), a
 * row pointing at a missing file, and idempotency — plus the guarantee that a
 * dry run changes nothing and the legacy file is never deleted (P4's job).
 */
class PrivatiseUploadsTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Storage::fake('public');
        $this->hostel = Hostel::factory()->create();
    }

    private function staff(string $path): Staff
    {
        return Staff::create(['hostel_id' => $this->hostel->id, 'name' => 'S', 'mobile' => '9'.rand(100000000, 999999999),
            'monthly_salary' => 1, 'is_active' => true, 'aadhaar_file' => $path]);
    }

    private function student(array $attrs): Student
    {
        return Student::create(array_merge([
            'hostel_id' => $this->hostel->id, 'name' => 'St', 'mobile' => '9'.rand(100000000, 999999999),
            'occupation_type' => 'student', 'status' => 'active',
        ], $attrs));
    }

    private function migrate(bool $dry = false): int
    {
        return $this->artisan('hostelease:privatise-uploads'.($dry ? ' --dry-run' : ''))->run();
    }

    public function test_a_staff_aadhaar_moves_to_the_tenant_scoped_private_path(): void
    {
        Storage::disk('public')->put('staff/documents/card.webp', 'AADHAAR');
        $staff = $this->staff('staff/documents/card.webp');

        $this->assertSame(0, $this->migrate());

        $target = "staff/{$this->hostel->id}/aadhaar/card.webp";
        Storage::disk('private')->assertExists($target);
        $this->assertSame($target, $staff->fresh()->aadhaar_file);
        // Copy, not move: the legacy file survives until P4, and the bytes
        // that landed on private are the same bytes.
        Storage::disk('public')->assertExists('staff/documents/card.webp');
        $this->assertSame('AADHAAR', Storage::disk('private')->get($target));
    }

    public function test_a_flat_student_document_path_is_an_aadhaar_but_a_subdir_one_is_a_document(): void
    {
        Storage::disk('public')->put('students/documents/flat.webp', 'AADHAAR');
        Storage::disk('public')->put('students/documents/42/deal.pdf', 'PDF');

        $aadhaarStudent = $this->student(['aadhaar_file' => 'students/documents/flat.webp']);
        $docStudent = $this->student([]);
        StudentDocument::create(['hostel_id' => $this->hostel->id, 'student_id' => $docStudent->id,
            'type' => 'agreement', 'title' => 'A', 'file_path' => 'students/documents/42/deal.pdf']);

        $this->assertSame(0, $this->migrate());

        // Flat → aadhaar; under a numeric id → documents/{id}.
        $this->assertSame("students/{$this->hostel->id}/aadhaar/flat.webp", $aadhaarStudent->fresh()->aadhaar_file);
        Storage::disk('private')->assertExists("students/{$this->hostel->id}/documents/42/deal.pdf");
    }

    /**
     * §3.2 — one physical file, two rows. approve() copies a registration's
     * photo path onto the student, so a single file is referenced twice. It
     * must be copied ONCE and BOTH rows rewritten to the same target.
     */
    public function test_one_file_shared_by_two_rows_is_moved_once_and_both_rows_rewritten(): void
    {
        $path = 'registrations/photos/shared.webp';
        Storage::disk('public')->put($path, 'FACE');

        $student = $this->student(['photo' => $path]);
        $registration = StudentRegistration::create(['hostel_id' => $this->hostel->id, 'name' => 'St',
            'mobile' => '+919800000001', 'aadhaar' => '123456789012', 'occupation_type' => 'student',
            'joining_date' => now()->toDateString(), 'photo' => $path, 'status' => 'approved', 'student_id' => $student->id]);

        $this->assertSame(0, $this->migrate());

        $target = "registrations/{$this->hostel->id}/photos/shared.webp";
        Storage::disk('private')->assertExists($target);
        $this->assertSame($target, $student->fresh()->photo);
        $this->assertSame($target, $registration->fresh()->photo);
    }

    public function test_a_row_pointing_at_a_missing_file_is_reported_and_left_untouched(): void
    {
        $staff = $this->staff('staff/documents/gone.webp'); // never put on disk

        // MISSING is informational, not a failure.
        $this->assertSame(0, $this->migrate());
        $this->assertSame('staff/documents/gone.webp', $staff->fresh()->aadhaar_file);
    }

    public function test_a_public_file_no_row_references_is_left_in_place(): void
    {
        Storage::disk('public')->put('registrations/photos/orphan.jpg', 'STRAY');

        $this->assertSame(0, $this->migrate());

        // Reported as an orphan, but never touched — P4 decides its fate.
        Storage::disk('public')->assertExists('registrations/photos/orphan.jpg');
        Storage::disk('private')->assertMissing('registrations/photos/orphan.jpg');
    }

    public function test_a_file_already_on_private_is_skipped(): void
    {
        // A P2-born file: already at its new path on private, nothing on public.
        Storage::disk('private')->put("staff/{$this->hostel->id}/aadhaar/new.webp", 'NEW');
        $staff = $this->staff("staff/{$this->hostel->id}/aadhaar/new.webp");

        $this->assertSame(0, $this->migrate());
        $this->assertSame("staff/{$this->hostel->id}/aadhaar/new.webp", $staff->fresh()->aadhaar_file);
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        Storage::disk('public')->put('staff/documents/card.webp', 'AADHAAR');
        $staff = $this->staff('staff/documents/card.webp');

        $this->assertSame(0, $this->migrate(dry: true));

        $this->assertSame('staff/documents/card.webp', $staff->fresh()->aadhaar_file);
        Storage::disk('private')->assertMissing("staff/{$this->hostel->id}/aadhaar/card.webp");
    }

    public function test_it_is_idempotent(): void
    {
        Storage::disk('public')->put('staff/documents/card.webp', 'AADHAAR');
        $staff = $this->staff('staff/documents/card.webp');

        $this->migrate();
        $movedPath = $staff->fresh()->aadhaar_file;

        // A second run finds it already private and does nothing.
        $this->assertSame(0, $this->migrate());
        $this->assertSame($movedPath, $staff->fresh()->aadhaar_file);
    }

    /** Soft-deleted staff still hold files (their profile stays reachable),
     *  so the migration must see them — a direct table query does. */
    public function test_a_soft_deleted_rows_file_is_migrated_too(): void
    {
        Storage::disk('public')->put('staff/documents/gone.webp', 'AADHAAR');
        $staff = $this->staff('staff/documents/gone.webp');
        $staff->delete();

        $this->assertSame(0, $this->migrate());

        $this->assertSame("staff/{$this->hostel->id}/aadhaar/gone.webp", $staff->fresh()->aadhaar_file);
    }
}
