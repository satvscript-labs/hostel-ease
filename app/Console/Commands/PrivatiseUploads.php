<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * P3 of the private-disk migration (_artifact/ui_ux_audit/05_private_disk_plan.md).
 *
 * Moves every legacy upload out of the public web root (public/storage) onto
 * the private disk, and rewrites the stored paths to the tenant-scoped shape
 * P2 already writes new files in.
 *
 * WHY A COMMAND, NOT A DATABASE MIGRATION: it moves files. `migrate:rollback`
 * can't un-move them, so a migration would lie about being reversible. This is
 * re-runnable, dry-runnable, reports what it did, and copies-then-verifies
 * rather than moving — so a crash at any point leaves the app fully working on
 * the untouched public files.
 *
 * COPY, VERIFY, REWRITE — never move-then-rewrite. If the process died between
 * a move and the row rewrite, the row would point at a path that no longer
 * exists and the file would be unreachable — the hardest loss to notice.
 * Copy-first means the old file is still there and still served until P4
 * deletes it.
 *
 * Keyed on DISTINCT PATHS, not rows: approve() shares one file across two rows
 * (a registration's photo/Aadhaar becomes the student's — plan §3.2), so a file
 * is copied once and every row that referenced it is rewritten together.
 *
 * Reads public/storage as a PHYSICAL folder (File + public_path), not through a
 * 'public' disk: P4 removes that disk from config, and this command runs before
 * P4 in the same codebase, so it must not depend on it.
 */
class PrivatiseUploads extends Command
{
    protected $signature = 'hostelease:privatise-uploads {--dry-run : Plan and report, change nothing} {--chunk=200 : Rows read per query}';

    protected $description = 'Move legacy public uploads onto the private disk (private-disk migration P3)';

    /** Every column that stores an uploaded file path. */
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
        $dry = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));

        $this->info($dry ? 'DRY RUN — nothing will be changed.' : 'LIVE RUN — copying files and rewriting paths.');
        $this->newLine();

        // ── Snapshot every referenced path BEFORE touching anything. Both the
        //    migration and the orphan sweep read from this one snapshot, so
        //    rewriting rows mid-run can't make a just-moved file look orphaned.
        //    path => [ ['table','column','id','hostel_id'], … ]
        $refs = [];
        foreach (self::COLUMNS as $spec) {
            DB::table($spec['table'])
                ->select('id', 'hostel_id', $spec['column'].' as path')
                ->whereNotNull($spec['column'])
                ->where($spec['column'], '!=', '')
                ->orderBy('id')
                ->chunk($chunk, function ($rows) use (&$refs, $spec) {
                    foreach ($rows as $row) {
                        $refs[$row->path][] = [
                            'table' => $spec['table'],
                            'column' => $spec['column'],
                            'id' => $row->id,
                            'hostel_id' => $row->hostel_id,
                        ];
                    }
                });
        }

        $private = Storage::disk('private');

        $report = [
            'moved' => 0, 'already_private' => 0, 'rows_rewritten' => 0,
            'missing' => [], 'conflicts' => [], 'mismatches' => [], 'unknown' => [], 'orphans' => [],
        ];

        // ── Orphan sweep: public files no row points at. From the snapshot, so
        //    it's unaffected by anything rewritten below. Reported, never
        //    deleted — the stray .jpg names in registrations/photos look like
        //    an older code path and deserve human eyes (plan §6).
        //
        //    public/storage is addressed as a PHYSICAL folder (File + public_path),
        //    NOT through a 'public' disk — that disk is removed from config by P4,
        //    and this command must keep working after that (it runs before it).
        foreach ($this->publicFiles() as $file) {
            // Skip framework housekeeping (.gitignore etc.) — those are not
            // uploads and flagging them as orphans is just noise.
            if (str_starts_with(basename($file), '.')) {
                continue;
            }
            if (! isset($refs[$file])) {
                $report['orphans'][] = $file;
            }
        }

        // ── Move each distinct path.
        foreach ($refs as $path => $owners) {
            // A file P2 already wrote (or a prior run moved) lives on private and
            // not on public. Nothing to do.
            if ($private->exists($path) && ! $this->publicExists($path)) {
                $report['already_private']++;

                continue;
            }

            // The row points at a file that is on neither disk.
            if (! $this->publicExists($path)) {
                $report['missing'][] = ['path' => $path, 'owners' => $owners];

                continue;
            }

            // Every row sharing a path must agree on the tenant, or the target
            // (which embeds the hostel) is ambiguous.
            $hostels = array_unique(array_map(fn ($o) => $o['hostel_id'], $owners));
            if (count($hostels) !== 1) {
                $report['conflicts'][] = ['path' => $path, 'hostels' => $hostels, 'owners' => $owners];

                continue;
            }

            $target = $this->targetPath($path, (int) $hostels[0]);
            if ($target === null) {
                $report['unknown'][] = ['path' => $path, 'owners' => $owners];

                continue;
            }

            if ($dry) {
                $this->line("  <fg=cyan>would move</> {$path}");
                $this->line("            <fg=gray>→ {$target}</>  (".count($owners).' row'.(count($owners) === 1 ? '' : 's').')');
                $report['moved']++;
                $report['rows_rewritten'] += count($owners);

                continue;
            }

            if (! $this->copyVerify($private, $path, $target)) {
                $report['mismatches'][] = ['path' => $path, 'target' => $target];

                continue;
            }

            // Rewrite every referencing row in one transaction. The extra
            // where(column, path) makes it a no-op if the value changed under
            // us — the file is copied, so a re-run finishes the rewrite.
            DB::transaction(function () use ($owners, $path, $target, &$report) {
                foreach ($owners as $o) {
                    $report['rows_rewritten'] += DB::table($o['table'])
                        ->where('id', $o['id'])
                        ->where($o['column'], $path)
                        ->update([$o['column'] => $target]);
                }
            });

            $report['moved']++;
            $this->line("  <fg=green>moved</> {$path} <fg=gray>→ {$target}</>");
        }

        $this->printReport($report, count($refs), $dry);

        // A mismatch or a conflict is a real problem worth a non-zero exit
        // (CI, scripts). Missing/orphan/unknown are informational.
        return empty($report['mismatches']) && empty($report['conflicts'])
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * Copy public→private, then read the private copy back and confirm size and
     * content hash match. On any mismatch, remove the partial copy so a re-run
     * starts clean, and return false.
     */
    private function copyVerify($private, string $path, string $target): bool
    {
        $bytes = @file_get_contents($this->publicPath($path));
        if ($bytes === false) {
            return false;
        }

        $private->put($target, $bytes);

        $back = $private->get($target);
        if ($back === null || strlen($back) !== strlen($bytes) || md5($back) !== md5($bytes)) {
            $private->delete($target);

            return false;
        }

        return true;
    }

    /** Absolute path of a stored path inside the public/storage folder. */
    private function publicPath(string $path): string
    {
        return public_path('storage/'.$path);
    }

    private function publicExists(string $path): bool
    {
        return is_file($this->publicPath($path));
    }

    /** Every file under public/storage, as forward-slashed paths relative to it. */
    private function publicFiles(): array
    {
        $root = public_path('storage');
        if (! is_dir($root)) {
            return [];
        }

        return array_map(
            fn ($file) => str_replace('\\', '/', ltrim(substr($file->getPathname(), strlen($root)), '\\/')),
            File::allFiles($root)
        );
    }

    /**
     * Old flat path + hostel → tenant-scoped private path. Only ever runs on
     * legacy public paths (new files are already new-shape on private), so the
     * inputs are the known old shapes:
     *
     *   staff/photos/X                → staff/{h}/photos/X
     *   staff/documents/X             → staff/{h}/aadhaar/X      (staff has only Aadhaar)
     *   students/photos/X             → students/{h}/photos/X
     *   students/documents/X          → students/{h}/aadhaar/X   (flat = a student's Aadhaar)
     *   students/documents/{id}/X     → students/{h}/documents/{id}/X
     *   registrations/photos/X        → registrations/{h}/photos/X
     *   registrations/aadhaar/X       → registrations/{h}/aadhaar/X
     *
     * The students/documents flat-vs-subdir split is why this can't be a blind
     * segment insert: a flat file is a student Aadhaar, a file under a numeric
     * id is a StudentDocument. Returns null for anything unrecognised, so an
     * unexpected shape is reported and skipped rather than moved somewhere wrong.
     */
    private function targetPath(string $path, int $hostel): ?string
    {
        $seg = explode('/', $path);
        $rest = fn (int $from) => implode('/', array_slice($seg, $from));

        return match ($seg[0] ?? null) {
            'staff' => match ($seg[1] ?? null) {
                'photos' => "staff/{$hostel}/photos/".$rest(2),
                'documents' => "staff/{$hostel}/aadhaar/".$rest(2),
                default => null,
            },
            'students' => match ($seg[1] ?? null) {
                'photos' => "students/{$hostel}/photos/".$rest(2),
                'documents' => (isset($seg[3]) && ctype_digit($seg[2] ?? ''))
                    ? "students/{$hostel}/documents/{$seg[2]}/".$rest(3)
                    : "students/{$hostel}/aadhaar/".$rest(2),
                default => null,
            },
            'registrations' => match ($seg[1] ?? null) {
                'photos' => "registrations/{$hostel}/photos/".$rest(2),
                'aadhaar' => "registrations/{$hostel}/aadhaar/".$rest(2),
                default => null,
            },
            default => null,
        };
    }

    private function printReport(array $r, int $pathsSeen, bool $dry): void
    {
        $this->newLine();
        $this->info('── Reconciliation ─────────────────────────────');
        $this->table(['', 'count'], [
            ['paths seen', $pathsSeen],
            [$dry ? 'would move' : 'moved', $r['moved']],
            ['already private', $r['already_private']],
            [$dry ? 'rows would rewrite' : 'rows rewritten', $r['rows_rewritten']],
            ['MISSING (row → no file)', count($r['missing'])],
            ['CONFLICT (path → 2+ hostels)', count($r['conflicts'])],
            ['MISMATCH (copy failed verify)', count($r['mismatches'])],
            ['UNKNOWN (unrecognised shape)', count($r['unknown'])],
            ['ORPHAN (file → no row)', count($r['orphans'])],
        ]);

        foreach (['missing' => 'MISSING — a row points at a file that is not on disk',
                  'conflicts' => 'CONFLICT — one file, rows in different hostels',
                  'unknown' => 'UNKNOWN — path shape not recognised',
                  'mismatches' => 'MISMATCH — copy did not verify'] as $key => $title) {
            if (! empty($r[$key])) {
                $this->newLine();
                $this->warn($title.':');
                foreach ($r[$key] as $item) {
                    $this->line('  '.($item['path'] ?? $item['target'] ?? json_encode($item)));
                    if (! empty($item['owners'])) {
                        foreach ($item['owners'] as $o) {
                            $this->line("      <fg=gray>{$o['table']}#{$o['id']}.{$o['column']}</>");
                        }
                    }
                }
            }
        }

        if (! empty($r['orphans'])) {
            $this->newLine();
            $this->warn('ORPHAN — public files no row references (NOT deleted; review before P4):');
            foreach ($r['orphans'] as $o) {
                $this->line("  <fg=gray>{$o}</>");
            }
        }

        $this->newLine();
        if (empty($r['mismatches']) && empty($r['conflicts'])) {
            $this->info($dry ? '✓ Dry run clean. Re-run without --dry-run to apply.' : '✓ Done. Legacy public files left in place for P4 to delete.');
        } else {
            $this->error('✗ Problems above must be resolved before P4.');
        }
    }
}
