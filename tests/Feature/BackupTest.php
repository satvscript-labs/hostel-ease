<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Services\Backup\SqlRestorer;
use App\Services\Backup\SqlVerifier;
use App\Services\BackupService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Backups in PHP (no mysqldump, no proc_open). The point of these tests is the part a
 * green "file was created" cannot show: that what comes OUT of a restore is what went
 * IN, for the awkward values, and that a damaged backup is caught rather than trusted.
 *
 * The round trips run on throwaway SQLite FILES, not the in-memory test database,
 * because a restore drops and re-creates tables and must not run inside the test's
 * transaction. The MySQL-specific half (SHOW CREATE TABLE, backslash escaping) cannot
 * run here; it is proven on the server — see _artifact/saas_billing_autopay/19_BACKUP_REWRITE.md.
 */
class BackupTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'he-bk-'.bin2hex(random_bytes(4));
        mkdir($this->dir.DIRECTORY_SEPARATOR.'private', 0777, true);
        config([
            'hostelease.backup.path' => $this->dir.DIRECTORY_SEPARATOR.'backups',
            'filesystems.disks.local.root' => $this->dir.DIRECTORY_SEPARATOR.'private',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['bk_src', 'bk_dst'] as $name) {
            DB::purge($name);
        }
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    /** A migrated SQLite file database, registered as connection $name. */
    private function freshDatabase(string $name): string
    {
        config(["database.connections.{$name}" => array_merge(config('database.connections.sqlite'), [
            'database' => $this->dir.DIRECTORY_SEPARATOR.$name.'.sqlite',
            'foreign_key_constraints' => false,   // lets the fixtures skip building parents
        ])]);
        touch($this->dir.DIRECTORY_SEPARATOR.$name.'.sqlite');
        Artisan::call('migrate', ['--database' => $name, '--force' => true]);

        return $name;
    }

    // Everything that could break the file format if it were written raw: quotes, a
    // backslash, CR/TAB, non-ASCII, and — on lines of their own — the statement
    // separator and something shaped like a data row.
    private const TRICKY = "Rahul's \"PG\"\nsecond line\\back\r\ttab ₹ 日本語 😀\n-- @@\n(1,'looks like a row')\nINSERT INTO x VALUES (1);";

    private function fixtures(string $connection): void
    {
        $db = DB::connection($connection);
        $now = now()->toDateTimeString();

        $db->table('notifications')->insert([
            ['type' => 't1', 'title' => 'tricky', 'message' => self::TRICKY, 'data' => json_encode(['k' => "v'\n"]), 'level' => 'info', 'created_at' => $now, 'updated_at' => $now],
            ['type' => 't2', 'title' => 'null message', 'message' => null, 'data' => null, 'level' => 'info', 'created_at' => $now, 'updated_at' => $now],
            ['type' => 't3', 'title' => 'not utf-8', 'message' => "\xff\xfe\x01bin", 'data' => null, 'level' => 'info', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // invoices.balance is `amount - paid_amount`, computed by the database.
        $db->table('invoices')->insert([
            'hostel_id' => 1, 'student_id' => 1, 'type' => 'rent', 'title' => 'Rent',
            'amount' => 5000, 'paid_amount' => 1200.5, 'status' => 'partial',
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    // ── the round trip ───────────────────────────────────────────────────

    public function test_what_comes_out_of_a_restore_is_what_went_in(): void
    {
        $src = $this->freshDatabase('bk_src');
        $dst = $this->freshDatabase('bk_dst');
        $this->fixtures($src);

        $service = app(BackupService::class);
        $name = $service->create($src);
        $path = $service->path($name);

        (new SqlRestorer($dst))->restore($path);

        $copy = DB::connection($dst)->table('notifications')->orderBy('id')->get();
        $this->assertCount(3, $copy);
        $this->assertSame(self::TRICKY, $copy[0]->message, 'quotes, newlines, backslash, emoji and a fake marker survive');
        $this->assertSame('{"k":"v\'\n"}', $copy[0]->data);
        $this->assertNull($copy[1]->message, 'NULL stays NULL, not an empty string');
        $this->assertSame("\xff\xfe\x01bin", $copy[2]->message, 'bytes that are not UTF-8 are kept exactly');

        $invoice = DB::connection($dst)->table('invoices')->first();
        $this->assertEquals(5000, $invoice->amount);
        $this->assertEquals(3799.5, $invoice->balance, 'the computed column is recomputed, not copied');

        // Running the same restore again must be harmless: every table is replaced.
        (new SqlRestorer($dst))->restore($path);
        $this->assertSame(3, DB::connection($dst)->table('notifications')->count());
    }

    public function test_generated_columns_are_never_written_into_an_insert(): void
    {
        $src = $this->freshDatabase('bk_src');
        $this->fixtures($src);

        $path = app(BackupService::class)->path(app(BackupService::class)->create($src));

        $insert = collect(iterator_to_array(SqlVerifier::lines($path), false))
            ->first(fn ($l) => str_starts_with($l, 'INSERT INTO "invoices"'));

        $this->assertNotNull($insert);
        $this->assertStringNotContainsString('balance', $insert, 'MySQL refuses an INSERT that names a generated column');
        $this->assertStringContainsString('"paid_amount"', $insert);
    }

    public function test_throwaway_tables_keep_their_structure_but_not_their_rows(): void
    {
        DB::table('sessions')->insert(['id' => 'secret-session-id', 'payload' => 'x', 'last_activity' => time()]);

        $service = app(BackupService::class);
        $path = $service->path($service->create());
        $lines = iterator_to_array(SqlVerifier::lines($path), false);

        $this->assertContains('-- Table: sessions', $lines);
        $this->assertContains('-- table: sessions rows: 0', $lines);
        $this->assertStringNotContainsString('secret-session-id', implode("\n", $lines));
    }

    public function test_every_backup_is_read_back_and_gets_a_checksum(): void
    {
        $service = app(BackupService::class);
        $name = $service->create();

        $this->assertStringEndsWith('.sql.gz', $name);
        $this->assertFileDoesNotExist($service->path($name).'.partial');
        $this->assertSame(['ok' => true], ['ok' => $service->verify($name)['ok']]);

        $entry = $service->latest('database');
        $this->assertTrue($entry['verified']);
        $this->assertNotSame('', $entry['detail']);
    }

    // ── a damaged backup is caught, not trusted ──────────────────────────

    /** Write a plain-text copy of a real dump, edited by $change, and return its path. */
    private function tampered(callable $change): string
    {
        $service = app(BackupService::class);
        $lines = iterator_to_array(SqlVerifier::lines($service->path($service->create())), false);
        $path = $this->dir.DIRECTORY_SEPARATOR.'tampered-'.bin2hex(random_bytes(3)).'.sql';
        file_put_contents($path, implode("\n", $change($lines))."\n");

        return $path;
    }

    public function test_a_truncated_dump_is_refused(): void
    {
        $path = $this->tampered(fn ($l) => array_slice($l, 0, intdiv(count($l), 2)));

        $result = (new SqlVerifier)->verify($path);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('stops early', $result['message']);
    }

    public function test_rows_that_do_not_match_the_footer_are_refused(): void
    {
        // Drop one data row from the first table that has any (the migrations table does).
        $path = $this->tampered(function ($lines) {
            foreach ($lines as $i => $line) {
                if (str_starts_with($line, '(')) {
                    unset($lines[$i]);
                    break;
                }
            }

            return array_values($lines);
        });

        $result = (new SqlVerifier)->verify($path);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('footer says', $result['message']);
    }

    public function test_something_that_is_not_a_backup_is_refused(): void
    {
        $path = $this->dir.DIRECTORY_SEPARATOR.'notes.sql';
        file_put_contents($path, "DROP DATABASE everything;\n");

        $this->assertFalse((new SqlVerifier)->verify($path)['ok']);
    }

    public function test_a_changed_file_fails_its_checksum(): void
    {
        $service = app(BackupService::class);
        $name = $service->create();

        file_put_contents($service->path($name), 'x', FILE_APPEND);

        $result = $service->verify($name);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('checksum', $result['message']);
    }

    public function test_restore_refuses_a_damaged_file_and_touches_nothing(): void
    {
        $src = $this->freshDatabase('bk_src');
        $this->fixtures($src);
        $service = app(BackupService::class);
        $name = $service->create($src);
        file_put_contents($service->path($name), 'garbage', FILE_APPEND);   // damage it

        $this->artisan('hostelease:backup-restore', ['file' => $name, '--connection' => $src, '--force' => true, '--no-safety' => true])
            ->assertFailed();

        $this->assertSame(3, DB::connection($src)->table('notifications')->count());
    }

    // ── prune ────────────────────────────────────────────────────────────

    public function test_prune_never_removes_the_newest_few_however_old(): void
    {
        $dir = app(BackupService::class)->directory();
        foreach (range(1, 5) as $n) {
            $file = $dir.DIRECTORY_SEPARATOR."hostel-ease-2026-01-0{$n}_000000.sql.gz";
            file_put_contents($file, 'x');
            touch($file, strtotime("2026-01-0{$n} 00:00:00"));
        }
        // Two old file-archives: a different kind, counted separately.
        foreach ([1, 2] as $n) {
            $file = $dir.DIRECTORY_SEPARATOR."hostel-ease-files-2026-01-0{$n}_000000.zip";
            file_put_contents($file, 'x');
            touch($file, strtotime("2026-01-0{$n} 00:00:00"));
        }

        $removed = app(BackupService::class)->prune(30);

        $this->assertSame(2, $removed, 'five old database backups: the newest three stay');
        $this->assertCount(3, glob($dir.DIRECTORY_SEPARATOR.'hostel-ease-2026-*.sql.gz'));
        $this->assertCount(2, glob($dir.DIRECTORY_SEPARATOR.'hostel-ease-files-*.zip'), 'under the floor, so both stay');
    }

    // ── the command and its alerts ───────────────────────────────────────

    public function test_a_failed_backup_raises_an_alert_that_the_next_good_one_clears(): void
    {
        $this->mock(BackupService::class, fn ($m) => $m->shouldReceive('create')->once()->andThrow(new \RuntimeException('Backup failed: disk full')));

        $this->artisan('hostelease:backup')->assertFailed();

        $alert = Notification::whereNull('hostel_id')->where('type', 'backup_failed')->sole();
        $this->assertSame('danger', $alert->level);
        $this->assertStringContainsString('disk full', $alert->message);

        $this->app->forgetInstance(BackupService::class);   // drop the mock; the real one is next
        $this->artisan('hostelease:backup')->assertSuccessful();

        $this->assertSame(0, Notification::where('type', 'backup_failed')->count());
    }

    public function test_alerts_say_when_nothing_has_been_backed_up_recently(): void
    {
        $alerts = new NotificationService;
        $service = app(BackupService::class);

        $alerts->syncBackupAlerts($service);
        $this->assertSame(['database', 'files'], Notification::where('type', 'backup_stale')->orderBy('id')->get()->map(fn ($n) => $n->data['sig'])->all());

        $service->create();
        $alerts->syncBackupAlerts($service);

        $this->assertSame(['files'], Notification::where('type', 'backup_stale')->get()->map(fn ($n) => $n->data['sig'])->all(), 'a fresh database backup clears its own alert only');
    }

    public function test_an_old_backup_counts_as_stale(): void
    {
        $service = app(BackupService::class);
        $service->create();

        $this->assertFalse($service->health()['database']['stale']);

        $this->travel(37)->hours();

        $this->assertTrue($service->health()['database']['stale']);
    }

    // ── uploaded files ───────────────────────────────────────────────────

    public function test_the_uploaded_files_are_zipped_with_their_folders(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('zip extension not available');
        }

        $root = $this->dir.DIRECTORY_SEPARATOR.'private';
        mkdir($root.DIRECTORY_SEPARATOR.'students'.DIRECTORY_SEPARATOR.'aadhaar', 0777, true);
        file_put_contents($root.DIRECTORY_SEPARATOR.'students'.DIRECTORY_SEPARATOR.'aadhaar'.DIRECTORY_SEPARATOR.'a1.jpg', 'JPEGDATA');
        file_put_contents($root.DIRECTORY_SEPARATOR.'doc.pdf', 'PDFDATA');

        $service = app(BackupService::class);
        $name = $service->createFiles();

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($service->path($name)));
        $this->assertSame('JPEGDATA', $zip->getFromName('students/aadhaar/a1.jpg'));
        $this->assertSame('PDFDATA', $zip->getFromName('doc.pdf'));
        $this->assertSame(2, json_decode($zip->getFromName('backup-manifest.json'), true)['files']);
        $zip->close();

        $this->assertTrue($service->verify($name)['ok']);
        $this->assertSame('files', $service->latest('files')['type']);
        $this->assertSame('2 files', $service->latest('files')['detail']);
    }

    public function test_an_empty_upload_folder_still_backs_up(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('zip extension not available');
        }

        // A fresh install has no uploads yet; that must not look like a failed backup.
        $service = app(BackupService::class);

        $this->assertTrue($service->verify($service->createFiles())['ok']);
    }

    // ── the Super Admin page ─────────────────────────────────────────────

    public function test_the_super_admin_can_back_up_check_download_and_delete(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('superadmin.backups.store'))->assertSessionHas('success');

        $name = app(BackupService::class)->list()[0]['name'];

        $this->actingAs($admin)->get(route('superadmin.backups.index'))
            ->assertOk()->assertSee($name)->assertSee('Database');

        $this->actingAs($admin)->post(route('superadmin.backups.verify', $name))->assertSessionHas('success');
        $this->actingAs($admin)->get(route('superadmin.backups.download', $name))->assertOk();

        $this->actingAs($admin)->delete(route('superadmin.backups.destroy', $name))->assertSessionHas('success');
        $this->assertSame([], app(BackupService::class)->list());
        $this->assertFileDoesNotExist(app(BackupService::class)->directory().DIRECTORY_SEPARATOR.$name.'.json', 'the checksum file goes with it');
    }

    public function test_only_plain_backup_names_can_be_reached(): void
    {
        $service = app(BackupService::class);

        foreach (['../.env', '..\\..\\.env', 'hostel-ease-x/../../.env.sql', 'hostel-ease-a.sql.json', 'notes.sql'] as $bad) {
            $this->assertNull($service->path($bad), $bad);
        }
    }

    // ── restore command ──────────────────────────────────────────────────

    public function test_the_restore_command_replaces_the_database_after_a_typed_confirmation(): void
    {
        $src = $this->freshDatabase('bk_src');
        $this->fixtures($src);
        $service = app(BackupService::class);
        $name = $service->create($src);

        DB::connection($src)->table('notifications')->delete();    // "disaster"
        $this->assertSame(0, DB::connection($src)->table('notifications')->count());

        // Wrong confirmation: nothing happens.
        $this->artisan('hostelease:backup-restore', ['file' => $name, '--connection' => $src])
            ->expectsQuestion('Type the database name (bk_src.sqlite) to continue', 'nope')
            ->assertFailed();
        $this->assertSame(0, DB::connection($src)->table('notifications')->count());

        // Right confirmation: restored, and the state before it was backed up first.
        $before = count($service->list());
        $this->artisan('hostelease:backup-restore', ['file' => $name, '--connection' => $src])
            ->expectsQuestion('Type the database name (bk_src.sqlite) to continue', 'bk_src.sqlite')
            ->assertSuccessful();

        $this->assertSame(3, DB::connection($src)->table('notifications')->count());
        $this->assertSame($before + 1, count($service->list()), 'a safety backup of the pre-restore state');
    }
}
