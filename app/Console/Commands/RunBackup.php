<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * hostelease:backup           the database  (nightly)
 * hostelease:backup --files   uploaded files (weekly)
 *
 * On a host that runs this from a bare cron line, nobody reads the output (it goes to
 * /dev/null). So a failure must announce itself: it is logged AND raised as a Super
 * Admin alert that stays until the next good backup of that kind clears it.
 */
class RunBackup extends Command
{
    protected $signature = 'hostelease:backup
        {--files : Back up the uploaded files (Aadhaar scans, photos, documents) instead of the database}
        {--prune=30 : Delete backups older than N days (0 to keep all). The newest few of each kind are always kept}';

    protected $description = 'Create a verified backup (database, or uploaded files with --files) and prune old archives.';

    public function handle(BackupService $service, NotificationService $alerts): int
    {
        $type = $this->option('files') ? BackupService::FILES : BackupService::DATABASE;
        $label = $type === BackupService::FILES ? 'Uploaded files' : 'Database';

        try {
            $file = $type === BackupService::FILES ? $service->createFiles() : $service->create();
        } catch (\Throwable $e) {
            Log::error("Backup ({$type}) failed: ".$e->getMessage());
            $alerts->push(null, 'backup_failed', $type, "{$label} backup failed", $e->getMessage(), 'danger');
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $alerts->clear(null, 'backup_failed', $type);
        $alerts->clear(null, 'backup_stale', $type);
        $this->info("Backup created and verified: {$file}");

        $days = (int) $this->option('prune');
        if ($days > 0) {
            $this->info('Pruned '.$service->prune($days).' old backup(s).');
        }

        return self::SUCCESS;
    }
}
