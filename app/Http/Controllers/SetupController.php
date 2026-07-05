<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * One-time, token-guarded web installer for shared hosting without SSH.
 *
 * Visit /__install/<SETUP_TOKEN> once after uploading. Runs migrations + seed,
 * links storage and caches config/views. Disable afterwards by blanking
 * SETUP_TOKEN in .env (an empty token returns 404).
 */
class SetupController extends Controller
{
    public function install(string $token): Response
    {
        $expected = (string) config('app.setup_token', env('SETUP_TOKEN'));

        abort_if($expected === '' || ! hash_equals($expected, $token), 404);

        $log = [];
        $run = function (string $command, array $params = []) use (&$log) {
            try {
                Artisan::call($command, $params);
                $log[] = "✔ {$command} ".json_encode($params);
                $log[] = trim(Artisan::output());
            } catch (\Throwable $e) {
                $log[] = "✘ {$command}: ".$e->getMessage();
            }
        };

        // Always ensure the schema is present.
        $run('migrate', ['--force' => true]);

        // Seed only when the database is empty — so importing a SQL dump first
        // (with your real data) is never overwritten.
        try {
            $hasData = DB::table('users')->exists();
        } catch (\Throwable $e) {
            $hasData = false;
        }
        if (! $hasData) {
            $run('db:seed', ['--force' => true]);
            $log[] = 'ℹ Empty DB → seeded demo data + Super Admin.';
        } else {
            $log[] = 'ℹ Existing data found → skipped seeding (using your imported database).';
        }

        // Shared hosts disable symlink()/exec(), so `storage:link` fails there.
        // The public disk writes straight to public/storage — just ensure it exists.
        try {
            File::ensureDirectoryExists(public_path('storage'));
            $log[] = '✔ public/storage ready (no symlink needed)';
        } catch (\Throwable $e) {
            $log[] = '✘ public/storage: '.$e->getMessage();
        }

        $run('config:cache');
        $run('view:cache');

        // Quick sanity check.
        try {
            $hostels = DB::table('hostels')->count();
            $users = DB::table('users')->count();
            $log[] = "DB OK — hostels: {$hostels}, users: {$users}";
        } catch (\Throwable $e) {
            $log[] = 'DB check failed: '.$e->getMessage();
        }

        $log[] = '';
        $log[] = '== DONE. Now blank SETUP_TOKEN in .env and run config:cache (or re-deploy) to disable this page. ==';

        return response('<pre style="font:14px/1.5 monospace;padding:24px;">'
            .e(implode("\n", $log)).'</pre>');
    }
}
