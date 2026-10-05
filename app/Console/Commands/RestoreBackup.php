<?php

namespace App\Console\Commands;

use App\Services\Backup\SqlRestorer;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the database with a backup. Pure PHP, so it works where there is no `mysql`
 * client and proc_open is off. Destructive, so: it verifies the file first, takes a
 * safety backup of what is there now, and wants the database name typed (or --force).
 */
class RestoreBackup extends Command
{
    protected $signature = 'hostelease:backup-restore
        {file : Backup filename from storage/app/backups (a .sql or .sql.gz database backup)}
        {--connection= : Database connection (default: the one the app uses)}
        {--no-safety : Do not take a safety backup of the current database first}
        {--force : Skip the typed confirmation}';

    protected $description = 'Replace the database with a backup. DESTRUCTIVE: every table in the backup is dropped and re-created.';

    public function handle(BackupService $service): int
    {
        $name = (string) $this->argument('file');
        $path = $service->path($name);

        if (! $path || $service->typeOf($name) !== BackupService::DATABASE) {
            $this->error("No database backup called [{$name}].");

            return self::FAILURE;
        }

        $connection = $this->option('connection') ?: null;
        $database = basename((string) DB::connection($connection)->getDatabaseName());

        $check = $service->verify($name);
        if (! $check['ok']) {
            $this->error("Refusing to restore: {$check['message']}");

            return self::FAILURE;
        }

        $this->warn("This REPLACES the database [{$database}] with {$name}.");
        $this->line('  Everything entered since that backup was made will be lost, and every user is signed out.');

        if (! $this->option('force') && $this->ask("Type the database name ({$database}) to continue") !== $database) {
            $this->line('Cancelled. Nothing was changed.');

            return self::FAILURE;
        }

        if (! $this->option('no-safety')) {
            try {
                $this->info('Safety backup of the current database: '.$service->create($connection));
            } catch (\Throwable $e) {
                $this->error('Could not take the safety backup ('.$e->getMessage().'). Use --no-safety if you accept that.');

                return self::FAILURE;
            }
        }

        try {
            $done = (new SqlRestorer($connection))->restore($path, fn ($table) => $this->line("  restoring {$table}"));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Restored {$done['tables']} tables, {$done['rows']} rows.");

        return self::SUCCESS;
    }
}
