<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * P4 of the private-disk migration — the one-way door.
 *
 * Deletes the legacy files from public/storage now that P3 has copied them onto
 * the private disk and rewritten every row to point there. This is what
 * actually closes the leak: removing the 'public' disk from config stops the
 * APP writing there, but the WEB SERVER serves public/storage by filesystem
 * path regardless of config — so the files themselves must physically go.
 *
 * SAFE BY CONSTRUCTION: it deletes a public file ONLY if no database row
 * references its path. So —
 *   · run it before the migration and it deletes nothing (every file is still
 *     referenced);
 *   · run it after and it deletes exactly the migrated-away copies plus any
 *     pre-existing orphan junk;
 *   · a file the migration skipped (still referenced) is never touched.
 * The protection is intrinsic, not a flag you can forget.
 *
 * Reads the filesystem directly (File facade + public_path), NOT the 'public'
 * disk — that disk is removed from config by this same phase, so the command
 * must not depend on it.
 *
 * Destructive and irreversible: dry-run is the DEFAULT; --force is required to
 * delete. Take a file backup first (RunBackup covers the DATABASE only).
 */
class PurgePublicUploads extends Command
{
    protected $signature = 'hostelease:purge-public-uploads
        {--force : Actually delete (default is a dry run)}
        {--prune-empty-dirs : Remove directories left empty afterwards}';

    protected $description = 'Delete legacy public/storage uploads that no row references (private-disk migration P4)';

    /** Every column that stores an uploaded file path (mirrors PrivatiseUploads). */
    private const COLUMNS = [
        ['table' => 'staff', 'column' => 'photo'],
        ['table' => 'staff', 'column' => 'aadhaar_file'],
        ['table' => 'students', 'column' => 'photo'],
        ['table' => 'students', 'column' => 'aadhaar_file'],
        ['table' => 'student_documents', 'column' => 'file_path'],
        ['table' => 'student_registrations', 'column' => 'photo'],
        ['table' => 'student_registrations', 'column' => 'aadhaar_file'],
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $root = public_path('storage');

        if (! File::isDirectory($root)) {
            $this->info('public/storage does not exist — nothing to purge.');

            return self::SUCCESS;
        }

        $this->info($force ? 'LIVE — deleting unreferenced public files.' : 'DRY RUN — nothing will be deleted (pass --force to delete).');
        $this->newLine();

        // Everything a row still points at. These are PROTECTED — never deleted,
        // whatever state the migration is in.
        $referenced = [];
        foreach (self::COLUMNS as $spec) {
            DB::table($spec['table'])
                ->whereNotNull($spec['column'])
                ->where($spec['column'], '!=', '')
                ->pluck($spec['column'])
                ->each(function ($path) use (&$referenced) {
                    $referenced[$path] = true;
                });
        }

        $deleted = 0;
        $protected = [];
        $wouldDelete = [];

        foreach (File::allFiles($root) as $file) {
            // Keep framework housekeeping (public/storage/.gitignore).
            if (str_starts_with($file->getFilename(), '.')) {
                continue;
            }

            // Path as stored in the DB: relative to public/storage, forward slashes.
            $relative = str_replace('\\', '/', ltrim(substr($file->getPathname(), strlen($root)), '\\/'));

            if (isset($referenced[$relative])) {
                // A row still needs this file — the migration hasn't moved it.
                // Refuse, loudly: deleting it would break the app.
                $protected[] = $relative;

                continue;
            }

            if ($force) {
                File::delete($file->getPathname());
                $deleted++;
                $this->line("  <fg=red>deleted</> {$relative}");
            } else {
                $wouldDelete[] = $relative;
                $this->line("  <fg=yellow>would delete</> {$relative}");
            }
        }

        if ($force && $this->option('prune-empty-dirs')) {
            $this->pruneEmptyDirs($root);
        }

        $this->newLine();
        $this->info('── Summary ────────────────────────────────────');
        $this->table(['', 'count'], [
            [$force ? 'deleted' : 'would delete', $force ? $deleted : count($wouldDelete)],
            ['PROTECTED (still referenced — kept)', count($protected)],
        ]);

        if (! empty($protected)) {
            $this->newLine();
            $this->warn('PROTECTED — a row still points at these, so they were NOT deleted.');
            $this->warn('Run hostelease:privatise-uploads first; these have not been migrated:');
            foreach ($protected as $p) {
                $this->line("  <fg=gray>{$p}</>");
            }
        }

        $this->newLine();
        if ($force) {
            $this->info('✓ Done. The public sidewalk is clear; the leak is closed.');
        } else {
            $this->info('✓ Dry run. Re-run with --force to delete (back up files first — RunBackup is DB-only).');
        }

        // Referenced files surviving into P4 is a real problem: the migration
        // isn't finished. Non-zero so a deploy script halts.
        return empty($protected) ? self::SUCCESS : self::FAILURE;
    }

    /** Depth-first removal of directories left empty after deletion. */
    private function pruneEmptyDirs(string $root): void
    {
        foreach (array_reverse(File::directories($root)) as $dir) {
            $this->pruneEmptyDirs($dir);
        }
        // Never remove the root itself; remove a subdir only when truly empty.
        if ($root !== public_path('storage') && empty(File::allFiles($root)) && empty(File::directories($root))) {
            File::deleteDirectory($root);
        }
    }
}
