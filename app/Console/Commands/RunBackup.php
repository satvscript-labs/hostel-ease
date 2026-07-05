<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class RunBackup extends Command
{
    protected $signature = 'hsms:backup {--prune=30 : Delete backups older than N days (0 to keep all)}';

    protected $description = 'Create a database backup and prune old archives.';

    public function handle(BackupService $service): int
    {
        try {
            $file = $service->create();
            $this->info("Backup created: {$file}");
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $days = (int) $this->option('prune');
        if ($days > 0) {
            $removed = $service->prune($days);
            $this->info("Pruned {$removed} old backup(s).");
        }

        return self::SUCCESS;
    }
}
