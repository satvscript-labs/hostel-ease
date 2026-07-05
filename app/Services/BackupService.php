<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Database backup helper. Runs mysqldump into storage/app/backups and lists
 * the resulting archives for download. The dump binary is configurable so it
 * works on both XAMPP (Windows) and Hostinger.
 */
class BackupService
{
    public function directory(): string
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    /**
     * Create a new SQL dump. Returns the filename.
     *
     * @throws \RuntimeException when the dump fails.
     */
    public function create(): string
    {
        $filename = 'hsms-'.Carbon::now()->format('Y-m-d_His').'.sql';
        $path = $this->directory().DIRECTORY_SEPARATOR.$filename;

        $binary = config('hsms.dump_binary', env('DB_DUMP_BINARY', 'mysqldump'));

        $command = [
            $binary,
            '--host='.config('database.connections.mysql.host'),
            '--port='.config('database.connections.mysql.port'),
            '--user='.config('database.connections.mysql.username'),
            '--password='.config('database.connections.mysql.password'),
            '--single-transaction',
            '--skip-lock-tables',
            config('database.connections.mysql.database'),
        ];

        $process = new Process($command);
        $process->setTimeout(300);

        $handle = fopen($path, 'w');
        $process->run(function ($type, $buffer) use ($handle) {
            if ($type === Process::OUT) {
                fwrite($handle, $buffer);
            }
        });
        fclose($handle);

        if (! $process->isSuccessful()) {
            @unlink($path);
            throw new \RuntimeException('Backup failed: '.trim($process->getErrorOutput() ?: 'mysqldump not available.'));
        }

        return $filename;
    }

    /**
     * List existing backups, newest first.
     */
    public function list(): array
    {
        $dir = $this->directory();

        return collect(File::glob($dir.DIRECTORY_SEPARATOR.'*.sql'))
            ->map(fn ($f) => [
                'name' => basename($f),
                'size' => File::size($f),
                'created_at' => Carbon::createFromTimestamp(File::lastModified($f)),
            ])
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    public function path(string $filename): ?string
    {
        // Prevent path traversal — only plain backup filenames are allowed.
        if (! preg_match('/^hsms-[\w\-]+\.sql$/', $filename)) {
            return null;
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$filename;

        return File::exists($path) ? $path : null;
    }

    public function delete(string $filename): bool
    {
        $path = $this->path($filename);

        return $path ? File::delete($path) : false;
    }

    /**
     * Remove backups older than the given number of days.
     */
    public function prune(int $days = 30): int
    {
        $cut = Carbon::now()->subDays($days);
        $removed = 0;

        foreach ($this->list() as $backup) {
            if ($backup['created_at']->lt($cut)) {
                $this->delete($backup['name']) && $removed++;
            }
        }

        return $removed;
    }
}
