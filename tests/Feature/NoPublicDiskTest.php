<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P4 guard rails (private-disk migration). The whole point of removing the
 * public disk is that the old mistake becomes UNREPRESENTABLE — a tired
 * afternoon can't quietly put a file back on a public URL. These tests are the
 * tripwire: if someone re-adds the disk or writes to it, CI goes red here with
 * a message that says why.
 */
class NoPublicDiskTest extends TestCase
{
    /**
     * The 'public' disk is disabled by an unregistered driver, because Laravel
     * 11 merges the framework's default (working) public disk under our config
     * and null crashes boot — so "gone" means "throws when used", not "absent
     * from the array". Using it must fail loudly.
     */
    public function test_the_public_disk_cannot_be_used(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Storage::disk('public')->exists('anything');
    }

    /**
     * THE property that actually matters: no storage disk may root inside the
     * web root (public/). A file under public/ is served by the web server
     * before any PHP runs — that is the leak the whole migration closed. This
     * catches anyone re-pointing a disk at public_path(), whatever they name it.
     */
    public function test_no_disk_is_rooted_inside_the_web_root(): void
    {
        $web = str_replace('\\', '/', public_path());

        foreach (config('filesystems.disks') as $name => $config) {
            $root = $config['root'] ?? null;
            if ($root === null) {
                continue;
            }
            $this->assertFalse(
                str_starts_with(str_replace('\\', '/', $root), $web),
                "Disk [{$name}] roots inside the web root ({$root}). Files there are served by the web "
                .'server with no auth — store personal files on the private disk and serve them through '
                .'SecureFileController.'
            );
        }
    }

    public function test_the_default_disk_is_never_public(): void
    {
        $this->assertNotSame('public', config('filesystems.default'),
            'FILESYSTEM_DISK must not be "public".');
    }

    public function test_no_application_code_reaches_for_the_public_disk(): void
    {
        $offenders = [];

        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $body = $file->getContents();

            // Storage::disk('public'), ->store($x, $y, 'public'), etc. The
            // migration commands legitimately name public/storage as a PATH
            // (public_path), which is not a disk reference — so match only the
            // disk-name string forms.
            if (preg_match("/disk\\(\\s*['\"]public['\"]\\s*\\)/", $body)
                || preg_match("/,\\s*['\"]public['\"]\\s*[,)]/", $body)) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders,
            "These files reference the removed 'public' storage disk: ".implode(', ', $offenders)
            .'. Personal files belong on the private disk, served through SecureFileController.');
    }
}
