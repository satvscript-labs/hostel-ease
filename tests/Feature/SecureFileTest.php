<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SecureFileController is a security boundary, so it is tested before anything
 * points at it (P1 of the private-disk migration).
 *
 * What it replaces: files in public/storage are served by the web server before
 * PHP runs, so auth, role/access middleware, SetTenant, TenantScope and route
 * binding are all bypassed. Anyone holding the URL — forever — could read
 * another hostel's Aadhaar card. These tests are the proof that the new path
 * doesn't have that property.
 */
class SecureFileTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected User $owner;
    protected Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Storage::fake('public');

        $this->hostel = Hostel::factory()->create();
        $this->owner = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);

        $this->staff = Staff::create([
            'hostel_id' => $this->hostel->id, 'name' => 'Govind', 'mobile' => '9800000001',
            'monthly_salary' => 12000, 'is_active' => true,
            'aadhaar_file' => 'staff/documents/secret.webp',
            'photo' => 'staff/photos/face.webp',
        ]);

        Storage::disk('private')->put('staff/documents/secret.webp', 'AADHAAR-BYTES');
        Storage::disk('private')->put('staff/photos/face.webp', 'FACE-BYTES');
    }

    protected function url(string $source, int $id, string $field): string
    {
        return route('admin.files.show', [$source, $id, $field]);
    }

    // ── The boundary ─────────────────────────────────────────────────────

    public function test_the_owner_gets_the_file(): void
    {
        $res = $this->actingAs($this->owner)->get($this->url('staff', $this->staff->id, 'aadhaar_file'))->assertOk();

        $this->assertSame('AADHAAR-BYTES', $res->streamedContent());
    }

    /** THE test. This request used to be a static file read that always won. */
    public function test_another_hostels_admin_cannot_read_the_file(): void
    {
        $other = Hostel::factory()->create();
        $otherAdmin = User::factory()->create(['hostel_id' => $other->id, 'role' => 'hostel_admin']);

        $this->actingAs($otherAdmin)
            ->get($this->url('staff', $this->staff->id, 'aadhaar_file'))
            ->assertNotFound();
    }

    public function test_a_logged_out_visitor_cannot_read_the_file(): void
    {
        $this->get($this->url('staff', $this->staff->id, 'aadhaar_file'))
            ->assertRedirect(route('login'));
    }

    /**
     * A sub-user who cannot open the Staff Board must not be able to fetch a
     * staff Aadhaar by URL either — the route mirrors the `access:` middleware
     * that gates the page.
     */
    public function test_a_role_without_the_area_cannot_read_the_file(): void
    {
        $accountant = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'accountant']);
        $this->assertFalse($accountant->canAccessArea('staff'), 'Fixture assumption: accountant has no staff area.');

        $this->actingAs($accountant)
            ->get($this->url('staff', $this->staff->id, 'aadhaar_file'))
            ->assertNotFound();
    }

    /** Every refusal looks the same from outside: 404, never 403 — a "forbidden"
     *  confirms the thing exists. */
    public function test_refusals_are_indistinguishable_from_not_found(): void
    {
        $other = Hostel::factory()->create();
        $otherAdmin = User::factory()->create(['hostel_id' => $other->id, 'role' => 'hostel_admin']);

        $real = $this->actingAs($otherAdmin)->get($this->url('staff', $this->staff->id, 'aadhaar_file'));
        $fake = $this->actingAs($otherAdmin)->get($this->url('staff', 99999, 'aadhaar_file'));

        $this->assertSame($real->status(), $fake->status());
        $this->assertSame(404, $real->status());
    }

    // ── The whitelist ────────────────────────────────────────────────────

    public function test_an_unknown_source_or_field_is_not_found(): void
    {
        $this->actingAs($this->owner)->get($this->url('wallets', 1, 'photo'))->assertNotFound();
        // `name` is a real column — the field list is a whitelist, not a
        // blacklist, so it resolves to nothing rather than to something.
        $this->actingAs($this->owner)->get($this->url('staff', $this->staff->id, 'name'))->assertNotFound();
    }

    public function test_a_field_with_no_file_is_not_found(): void
    {
        $bare = Staff::create(['hostel_id' => $this->hostel->id, 'name' => 'No Photo',
            'mobile' => '9800000002', 'monthly_salary' => 1, 'is_active' => true]);

        $this->actingAs($this->owner)->get($this->url('staff', $bare->id, 'photo'))->assertNotFound();
    }

    public function test_a_traversal_path_is_refused(): void
    {
        $this->staff->forceFill(['photo' => '../../../.env'])->saveQuietly();

        $this->actingAs($this->owner)->get($this->url('staff', $this->staff->id, 'photo'))->assertNotFound();
    }

    // ── Behaviour the pages depend on ────────────────────────────────────

    /** Removing a staff member keeps their record reachable (W7.1), so their
     *  photo must resolve too or the profile renders broken. */
    public function test_a_removed_staff_members_file_still_resolves(): void
    {
        $this->staff->delete();

        $this->actingAs($this->owner)
            ->get($this->url('staff', $this->staff->id, 'photo'))
            ->assertOk();
    }

    /** Avatars are the hot path now that photos are private (owner decision
     *  D1), so a repeat view must be a 304, not a re-download. */
    public function test_a_repeat_request_is_a_304(): void
    {
        $first = $this->actingAs($this->owner)->get($this->url('staff', $this->staff->id, 'photo'))->assertOk();

        $etag = $first->headers->get('ETag');
        $this->assertNotNull($etag);
        $this->assertStringContainsString('private', $first->headers->get('Cache-Control'));

        $this->actingAs($this->owner)
            ->withHeaders(['If-None-Match' => $etag])
            ->get($this->url('staff', $this->staff->id, 'photo'))
            ->assertStatus(304);
    }

    /** student_documents holds PDFs as well as images — it must stream with the
     *  right type, not force a download of an unknown blob. */
    public function test_a_pdf_document_streams_with_its_own_content_type(): void
    {
        $student = Student::create(['hostel_id' => $this->hostel->id, 'name' => 'A',
            'mobile' => '9000000001', 'occupation_type' => 'student', 'status' => 'active']);

        $doc = StudentDocument::create(['hostel_id' => $this->hostel->id, 'student_id' => $student->id,
            'type' => 'agreement', 'title' => 'Agreement', 'file_path' => 'students/documents/1/deal.pdf']);

        Storage::disk('private')->put('students/documents/1/deal.pdf', '%PDF-1.4 fake');

        $this->actingAs($this->owner)
            ->get($this->url('document', $doc->id, 'file_path'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * P2 ships before the files move (P3), so a path still living on the legacy
     * public disk has to resolve through here too — otherwise every existing
     * document 404s the moment the views switch over.
     */
    public function test_a_legacy_file_still_on_the_public_disk_is_served(): void
    {
        Storage::disk('private')->delete('staff/photos/face.webp');
        Storage::disk('public')->put('staff/photos/face.webp', 'OLD-FACE-BYTES');

        $res = $this->actingAs($this->owner)->get($this->url('staff', $this->staff->id, 'photo'))->assertOk();

        $this->assertSame('OLD-FACE-BYTES', $res->streamedContent());
    }
}
