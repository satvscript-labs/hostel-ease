<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * P4's destructive step. The command reads the real filesystem (public_path),
 * so the test points public_path at a temp dir and works there. The property
 * that matters most: a file a row STILL references is never deleted — the
 * safety is intrinsic, not a flag.
 */
class PurgePublicUploadsTest extends TestCase
{
    use RefreshDatabase;

    protected string $tmp;
    protected string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/he-purge-'.uniqid();
        $this->storage = $this->tmp.'/storage';
        File::ensureDirectoryExists($this->storage);
        $this->app->usePublicPath($this->tmp);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    private function putPublic(string $relative, string $body = 'X'): void
    {
        $full = $this->storage.'/'.$relative;
        File::ensureDirectoryExists(dirname($full));
        File::put($full, $body);
    }

    public function test_a_dry_run_deletes_nothing(): void
    {
        $this->putPublic('staff/documents/orphan.webp');

        $this->artisan('hostelease:purge-public-uploads')->assertSuccessful();

        $this->assertTrue(File::exists($this->storage.'/staff/documents/orphan.webp'));
    }

    public function test_force_deletes_an_unreferenced_file(): void
    {
        $this->putPublic('staff/documents/orphan.webp');

        $this->artisan('hostelease:purge-public-uploads --force')->assertSuccessful();

        $this->assertFalse(File::exists($this->storage.'/staff/documents/orphan.webp'));
    }

    /** THE safety property: a file a row still points at is never deleted, and
     *  the command fails so a deploy script halts. */
    public function test_a_still_referenced_file_is_protected_and_the_command_fails(): void
    {
        $hostel = Hostel::factory()->create();
        Staff::create(['hostel_id' => $hostel->id, 'name' => 'S', 'mobile' => '9800000001',
            'monthly_salary' => 1, 'is_active' => true, 'aadhaar_file' => 'staff/documents/live.webp']);
        $this->putPublic('staff/documents/live.webp');
        $this->putPublic('staff/documents/orphan.webp');

        // FAILURE exit because something is still referenced (migration unfinished).
        $this->artisan('hostelease:purge-public-uploads --force')->assertFailed();

        // The referenced file survives; the orphan is gone.
        $this->assertTrue(File::exists($this->storage.'/staff/documents/live.webp'));
        $this->assertFalse(File::exists($this->storage.'/staff/documents/orphan.webp'));
    }

    public function test_it_keeps_framework_dotfiles(): void
    {
        $this->putPublic('.gitignore', "*\n!.gitignore\n");

        $this->artisan('hostelease:purge-public-uploads --force')->assertSuccessful();

        $this->assertTrue(File::exists($this->storage.'/.gitignore'));
    }
}
