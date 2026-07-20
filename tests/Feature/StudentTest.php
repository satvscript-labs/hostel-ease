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
        // validIntake() posts a fake Aadhaar upload, so every store test writes
        // a file. Fake both disks suite-wide so nothing lands on the real
        // filesystem — pre-P2 these tests were writing real images into
        // public/storage on every run.
        Storage::fake('private');
        Storage::fake('public');

        $this->hostel = Hostel::factory()->create();
        $this->admin = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);
    }

    /**
     * A complete intake, as the form actually sends it. Student records grew a
     * full KYC set (father's mobile, aadhaar + its scan, address, college…);
     * this test still posted four fields, so every submission bounced on
     * validation — and `assertRedirect()` happily passed on the bounce-back,
     * leaving the real failure to surface as "no Student found" two lines later.
     */
    protected function validIntake(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Student',
            'mobile' => '98765 43210',
            'father_mobile' => '98765 43211',
            'aadhaar' => '1234 5678 9012',
            'aadhaar_file' => UploadedFile::fake()->image('aadhaar.jpg'),
            'address' => '12 MG Road',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'occupation_type' => 'student',
            'college' => 'Nirma University',
            'field_of_study' => 'Computer Engineering',
            'join_date' => now()->toDateString(),
            'status' => 'active',
        ], $overrides);
    }

    /**
     * The Aadhaar card (and photo) captured at creation live on the student ROW
     * as columns, not in student_documents — but the profile's Documents tab must
     * still surface them as read-only base documents (owner report: the required
     * Aadhaar upload was invisible on the profile).
     */
    public function test_the_profile_surfaces_the_base_aadhaar_card_in_documents(): void
    {
        $this->actingAs($this->admin)->post(route('admin.students.store'), $this->validIntake([
            'photo' => UploadedFile::fake()->image('avatar.jpg'),
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $student = Student::firstOrFail();
        $this->assertNotNull($student->aadhaar_file);

        $this->actingAs($this->admin)->get(route('admin.students.show', $student))
            ->assertOk()
            ->assertSee('Aadhaar Card')
            ->assertSee('Student Photo')
            ->assertSee(route('admin.files.show', ['student', $student->id, 'aadhaar_file']), false);
    }

    /** H2b — the index filters + paginates on the SERVER now, not client-side. */
    public function test_the_index_filters_and_paginates_server_side(): void
    {
        Student::factory()->count(26)->create(['hostel_id' => $this->hostel->id, 'status' => 'active']);
        Student::factory()->left()->create(['hostel_id' => $this->hostel->id, 'name' => 'Departed Person']);
        Student::factory()->create(['hostel_id' => $this->hostel->id, 'name' => 'Working Wanda', 'occupation_type' => 'working']);

        // 26+ students → paginated (page-2 link present).
        $this->actingAs($this->admin)->get(route('admin.students.index'))
            ->assertOk()->assertSee('page=2', false);

        // filter=left shows only the departed student.
        $this->actingAs($this->admin)->get(route('admin.students.index', ['filter' => 'left']))
            ->assertOk()->assertSee('Departed Person');

        // A no-match search → empty state, and nobody's card is rendered.
        $this->actingAs($this->admin)->get(route('admin.students.index', ['q' => 'zzz-nobody']))
            ->assertOk()->assertSee('No matches')->assertDontSee('Departed Person');
    }

    public function test_admin_can_create_a_student_with_photo(): void
    {
        $this->actingAs($this->admin)->post(route('admin.students.store'), $this->validIntake([
            'photo' => UploadedFile::fake()->image('avatar.jpg'),
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $student = Student::firstOrFail();
        // Stored E.164, not bare digits: typed-in spacing is normalised away
        // and the country code is part of the stored value.
        $this->assertSame('+919876543210', $student->mobile);
        $this->assertSame('123456789012', $student->aadhaar);

        // P2: photo + Aadhaar land on the PRIVATE disk under the tenant, never
        // on the public web-root disk; Aadhaar sits in its own dir, split off
        // from documents (plan §3.4).
        $this->assertStringStartsWith("students/{$this->hostel->id}/photos/", $student->photo);
        $this->assertStringStartsWith("students/{$this->hostel->id}/aadhaar/", $student->aadhaar_file);
        Storage::disk('private')->assertExists($student->photo);
        Storage::disk('public')->assertMissing($student->photo);

        // photo_url is the guarded route now, not a public Storage URL.
        $this->assertSame(route('admin.files.show', ['student', $student->id, 'photo']), $student->photo_url);
    }

    /** A student without a college is not a student record we accept. */
    public function test_student_occupation_requires_college_and_field_of_study(): void
    {
        $this->actingAs($this->admin)->post(route('admin.students.store'), $this->validIntake([
            'college' => null,
            'field_of_study' => null,
        ]))->assertSessionHasErrors(['college', 'field_of_study']);

        $this->assertSame(0, Student::count());
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

    /**
     * The W6.4 systemic find: SubstituteBindings ran BEFORE the tenant
     * middleware, so route-model bindings resolved with the TenantScope
     * no-opped — any admin could open any hostel's student by URL id. The
     * priority fix in bootstrap/app.php binds the tenant first; this pins it.
     */
    public function test_cross_tenant_student_profile_is_not_found(): void
    {
        $other = Hostel::factory()->create();
        $foreign = Student::create(['hostel_id' => $other->id, 'name' => 'Foreign Student',
            'mobile' => '9222222222', 'occupation_type' => 'student', 'status' => 'active']);

        $this->actingAs($this->admin)->get(route('admin.students.show', $foreign->id))
            ->assertNotFound();
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

    // ── Public-ID hardening (U0): opaque ULID route key ───────────────────

    /** Every student gets a 26-char ULID public_id on create; the PK is untouched. */
    public function test_a_student_is_assigned_a_public_id_on_create(): void
    {
        $student = Student::create(['hostel_id' => $this->hostel->id, 'name' => 'ULID Kid',
            'mobile' => '9555500000', 'occupation_type' => 'student', 'status' => 'active']);

        $this->assertNotNull($student->public_id);
        $this->assertSame(26, strlen($student->public_id));
        // Route key is the opaque id, not the integer PK.
        $this->assertSame($student->public_id, $student->getRouteKey());
        $this->assertNotSame((string) $student->id, $student->getRouteKey());
    }

    /** The profile URL carries the ULID, never the sequential integer id. */
    public function test_the_profile_url_uses_the_public_id_not_the_integer(): void
    {
        $student = Student::create(['hostel_id' => $this->hostel->id, 'name' => 'No Enum',
            'mobile' => '9555511111', 'occupation_type' => 'student', 'status' => 'active']);

        $url = route('admin.students.show', $student);
        $this->assertStringContainsString($student->public_id, $url);
        $this->assertStringEndsWith('/'.$student->public_id, $url);

        // Opaque URL resolves; guessing the sequential integer no longer does.
        $this->actingAs($this->admin)->get($url)->assertOk();
        $this->actingAs($this->admin)->get('/admin/students/'.$student->id)->assertNotFound();
    }

    /** Cross-tenant is still blocked even with a VALID foreign public_id. */
    public function test_a_foreign_public_id_is_not_found(): void
    {
        $other = Hostel::factory()->create();
        $foreign = Student::create(['hostel_id' => $other->id, 'name' => 'Foreign',
            'mobile' => '9555522222', 'occupation_type' => 'student', 'status' => 'active']);

        // A real, valid ULID — but it belongs to another tenant, so TenantScope 404s it.
        $this->actingAs($this->admin)->get(route('admin.students.show', $foreign))
            ->assertNotFound();
    }
}
