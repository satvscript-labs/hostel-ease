<?php

namespace App\Services;

use App\Services\Backup\SqlDumper;
use App\Services\Backup\SqlVerifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Backups, in PHP.
 *
 * Two kinds, both written to config('hostelease.backup.path'):
 *
 *   database  hostel-ease-2026-10-05_020000.sql.gz   the whole database (SqlDumper)
 *   files     hostel-ease-files-2026-10-05_030000.zip   everything students uploaded
 *
 * The old version shelled out to `mysqldump`, which needs proc_open — disabled on
 * Hostinger, so no backup there ever succeeded — and it never touched uploaded files at
 * all (Aadhaar scans, photos, documents live on disk, not in the database).
 *
 * How a backup earns its place in the list:
 *   1. It is written to a `.partial` name, so a half-written file is never listed,
 *      downloaded or pruned-around.
 *   2. It is READ BACK and checked before it is kept (see SqlVerifier). A backup that
 *      fails that check is deleted and the run fails loudly.
 *   3. It gets a sidecar `<name>.json` with its size, SHA-256 and row counts, so it can
 *      be re-checked later and a damaged download is detectable.
 */
class BackupService
{
    public const DATABASE = 'database';

    public const FILES = 'files';

    public function directory(): string
    {
        $dir = (string) config('hostelease.backup.path');
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    // ── create ───────────────────────────────────────────────────────────

    /**
     * Dump the database. Returns the filename.
     *
     * @throws \RuntimeException when it cannot be written or does not verify.
     */
    public function create(?string $connection = null): string
    {
        $this->allowLongRun();
        $gzip = function_exists('gzopen');
        $name = $this->uniqueName('hostel-ease-'.now()->format('Y-m-d_His'), $gzip ? '.sql.gz' : '.sql');
        $final = $this->directory().DIRECTORY_SEPARATOR.$name;
        $partial = $final.'.partial';

        $handle = $gzip ? gzopen($partial, 'wb6') : fopen($partial, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Backup failed: cannot write to '.$this->directory().'.');
        }
        @chmod($partial, 0600);

        try {
            $dumper = new SqlDumper($connection);
            $rows = $dumper->dump(function (string $chunk) use ($handle, $gzip) {
                $written = $gzip ? gzwrite($handle, $chunk) : fwrite($handle, $chunk);
                if ($written === false || $written < strlen($chunk)) {
                    throw new \RuntimeException('the disk is full or not writable');
                }
            }, (array) config('hostelease.backup.skip_data'), (int) config('hostelease.backup.rows_per_insert', 200));

            $gzip ? gzclose($handle) : fclose($handle);
            $handle = null;

            $check = (new SqlVerifier)->verify($partial);
            if (! $check['ok']) {
                throw new \RuntimeException('the file did not verify — '.$check['message']);
            }

            rename($partial, $final);
            $this->writeMeta($name, [
                'type' => self::DATABASE,
                'tables' => count($rows),
                'rows' => array_sum($rows),
                'driver' => $dumper->driverName(),
            ]);

            return $name;
        } catch (\Throwable $e) {
            if ($handle) {
                $gzip ? @gzclose($handle) : @fclose($handle);
            }
            @unlink($partial);

            throw new \RuntimeException('Backup failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Zip everything under the private upload disk. Returns the filename.
     *
     * @throws \RuntimeException
     */
    public function createFiles(): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Backup failed: the PHP zip extension is not installed on this server.');
        }

        $this->allowLongRun();
        $root = rtrim((string) config('filesystems.disks.local.root'), '\\/');
        $name = $this->uniqueName('hostel-ease-files-'.now()->format('Y-m-d_His'), '.zip');
        $final = $this->directory().DIRECTORY_SEPARATOR.$name;
        $partial = $final.'.partial';
        $backups = realpath($this->directory()) ?: $this->directory();

        $zip = new \ZipArchive;
        if ($zip->open($partial, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Backup failed: cannot write to '.$this->directory().'.');
        }
        @chmod($partial, 0600);

        $count = 0;
        $bytes = 0;

        try {
            if (is_dir($root)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY,
                );

                foreach ($files as $file) {
                    $real = $file->getRealPath();
                    // Never zip the backups into a backup, and skip anything unreadable.
                    if (! $file->isFile() || ! $real || str_starts_with($real, $backups) || ! is_readable($real)) {
                        continue;
                    }

                    $entry = str_replace('\\', '/', ltrim(substr($real, strlen(realpath($root))), '\\/'));
                    $zip->addFile($real, $entry);
                    $zip->setCompressionName($entry, \ZipArchive::CM_STORE);   // jpg/pdf are already compressed
                    $count++;
                    $bytes += $file->getSize();

                    // A ZipArchive holds every added file open until close(); thousands of
                    // uploads would hit the open-file limit. Flush in batches.
                    if ($count % 200 === 0) {
                        $zip->close();
                        $zip->open($partial);
                    }
                }
            }

            // The manifest also guarantees the archive is never empty (a zip with no
            // entries is not written at all), and says what it should contain.
            $zip->addFromString('backup-manifest.json', json_encode([
                'created_at' => now()->toIso8601String(), 'files' => $count, 'bytes' => $bytes,
            ], JSON_PRETTY_PRINT));

            if (! $zip->close()) {
                throw new \RuntimeException('the zip could not be finished (is the disk full?)');
            }
            // close() writes a fresh file with the default mode, so restrict it NOW: the
            // archive holds Aadhaar scans and must not be world-readable.
            @chmod($partial, 0600);

            $check = $this->verifyZip($partial, $count + 1);
            if ($check !== null) {
                throw new \RuntimeException($check);
            }

            rename($partial, $final);
            $this->writeMeta($name, ['type' => self::FILES, 'files' => $count, 'uploads_bytes' => $bytes]);

            return $name;
        } catch (\Throwable $e) {
            @$zip->close();
            @unlink($partial);

            throw new \RuntimeException('Backup failed: '.$e->getMessage(), 0, $e);
        }
    }

    // ── verify ───────────────────────────────────────────────────────────

    /**
     * Re-check a stored backup: its checksum, then its contents.
     *
     * @return array{ok: bool, message: string}
     */
    public function verify(string $filename): array
    {
        $path = $this->path($filename);
        if (! $path) {
            return ['ok' => false, 'message' => 'No such backup.'];
        }

        $meta = $this->meta($filename);
        if (! empty($meta['sha256']) && ! hash_equals($meta['sha256'], hash_file('sha256', $path))) {
            return ['ok' => false, 'message' => 'The checksum does not match — the file has changed or is damaged since it was made.'];
        }

        if ($this->typeOf($filename) === self::FILES) {
            $problem = $this->verifyZip($path, isset($meta['files']) ? $meta['files'] + 1 : null);

            return $problem ? ['ok' => false, 'message' => $problem] : ['ok' => true, 'message' => 'Complete.'];
        }

        $check = (new SqlVerifier)->verify($path);

        return ['ok' => $check['ok'], 'message' => $check['ok']
            ? "Complete — {$check['tables']} tables, {$check['rows']} rows."
            : $check['message']];
    }

    /** null when the zip is sound, otherwise why not. */
    private function verifyZip(string $path, ?int $expectedEntries): ?string
    {
        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::CHECKCONS) !== true) {
            return 'the zip failed its consistency check';
        }

        $entries = $zip->numFiles;
        $zip->close();

        return $expectedEntries !== null && $entries !== $expectedEntries
            ? "the zip holds {$entries} entries but {$expectedEntries} were expected"
            : null;
    }

    // ── list / health / prune ────────────────────────────────────────────

    /**
     * Existing backups, newest first.
     *
     * @return list<array{name: string, type: string, size: int, created_at: Carbon, verified: bool|null, detail: string}>
     */
    public function list(): array
    {
        $dir = $this->directory();
        $found = array_merge(
            File::glob($dir.DIRECTORY_SEPARATOR.'hostel-ease-*.sql'),
            File::glob($dir.DIRECTORY_SEPARATOR.'hostel-ease-*.sql.gz'),
            File::glob($dir.DIRECTORY_SEPARATOR.'hostel-ease-*.zip'),
        );

        return collect($found)
            ->map(function ($file) {
                $name = basename($file);
                $meta = $this->meta($name);

                return [
                    'name' => $name,
                    'type' => $this->typeOf($name),
                    'size' => File::size($file),
                    'created_at' => isset($meta['created_at'])
                        ? Carbon::parse($meta['created_at'])
                        : Carbon::createFromTimestamp(File::lastModified($file)),
                    // true = verified when made; null = made before checksums existed.
                    'verified' => isset($meta['sha256']) ? true : null,
                    'detail' => match ($this->typeOf($name)) {
                        self::FILES => isset($meta['files']) ? $meta['files'].' files' : '',
                        default => isset($meta['tables']) ? $meta['tables'].' tables · '.number_format($meta['rows']).' rows' : '',
                    },
                ];
            })
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    public function latest(string $type): ?array
    {
        return collect($this->list())->firstWhere('type', $type);
    }

    /**
     * How fresh each kind of backup is, against the thresholds in config.
     *
     * @return array<string, array{last: Carbon|null, stale: bool}>
     */
    public function health(): array
    {
        $db = $this->latest(self::DATABASE)['created_at'] ?? null;
        $files = $this->latest(self::FILES)['created_at'] ?? null;

        return [
            self::DATABASE => [
                'last' => $db,
                'stale' => ! $db || $db->lt(now()->subHours((int) config('hostelease.backup.stale_database_hours', 36))),
            ],
            self::FILES => [
                'last' => $files,
                'stale' => ! $files || $files->lt(now()->subDays((int) config('hostelease.backup.stale_files_days', 10))),
            ],
        ];
    }

    public function path(string $filename): ?string
    {
        // Prevent path traversal — only plain backup filenames are allowed.
        if (! preg_match('/^hostel-ease-[\w\-]+\.(?:sql|sql\.gz|zip)$/', $filename)) {
            return null;
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$filename;

        return File::exists($path) ? $path : null;
    }

    public function delete(string $filename): bool
    {
        $path = $this->path($filename);
        if (! $path) {
            return false;
        }

        @unlink($this->metaPath($filename));

        return File::delete($path);
    }

    /**
     * Remove backups older than $days — but never the newest few of each kind, so a run
     * of failed backups cannot prune the last good one away.
     */
    public function prune(int $days = 30): int
    {
        $cut = Carbon::now()->subDays($days);
        $keep = max(1, (int) config('hostelease.backup.keep_min', 3));
        $removed = 0;

        foreach ([self::DATABASE, self::FILES] as $type) {
            $ofType = array_values(array_filter($this->list(), fn ($b) => $b['type'] === $type));

            foreach (array_slice($ofType, $keep) as $backup) {   // list() is newest first
                if ($backup['created_at']->lt($cut) && $this->delete($backup['name'])) {
                    $removed++;
                }
            }
        }

        return $removed;
    }

    // ── internals ────────────────────────────────────────────────────────

    public function typeOf(string $filename): string
    {
        return str_starts_with($filename, 'hostel-ease-files-') ? self::FILES : self::DATABASE;
    }

    private function metaPath(string $filename): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.$filename.'.json';
    }

    /** @return array<string, mixed> */
    private function meta(string $filename): array
    {
        $path = $this->metaPath($filename);

        return is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
    }

    private function writeMeta(string $filename, array $extra): void
    {
        $path = $this->directory().DIRECTORY_SEPARATOR.$filename;

        $meta = $this->metaPath($filename);
        file_put_contents($meta, json_encode(array_merge([
            'created_at' => now()->toIso8601String(),
            'bytes' => filesize($path),
            'sha256' => hash_file('sha256', $path),
            'verified_at' => now()->toIso8601String(),
        ], $extra), JSON_PRETTY_PRINT));
        @chmod($meta, 0600);
    }

    /** Two backups in the same second must not overwrite each other. */
    private function uniqueName(string $stem, string $ext): string
    {
        $name = $stem.$ext;
        for ($n = 2; is_file($this->directory().DIRECTORY_SEPARATOR.$name); $n++) {
            $name = $stem.'-'.$n.$ext;
        }

        return $name;
    }

    private function allowLongRun(): void
    {
        // A web request may have a short limit; a big dump should not die half-way.
        @set_time_limit(0);
    }
}
