<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class VerifyBackup extends Command
{
    protected $signature = 'hostelease:backup-verify {file? : Backup filename (default: the newest database backup)}';

    protected $description = 'Re-check a stored backup: its checksum and that its contents are complete.';

    public function handle(BackupService $service): int
    {
        $file = $this->argument('file') ?? ($service->latest(BackupService::DATABASE)['name'] ?? null);

        if (! $file) {
            $this->error('There are no database backups to verify.');

            return self::FAILURE;
        }

        $result = $service->verify($file);
        $result['ok'] ? $this->info("{$file}: {$result['message']}") : $this->error("{$file}: {$result['message']}");

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
