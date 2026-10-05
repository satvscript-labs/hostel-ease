<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(
        protected BackupService $backups,
        protected ActivityLogger $logger,
    ) {
    }

    public function index(): View
    {
        return view('superadmin.backups.index', [
            'backups' => $this->backups->list(),
            'health' => $this->backups->health(),
        ]);
    }

    /** "Back up now" is the database only: zipping every upload can outlast a web request, so that one runs from cron. */
    public function store(): RedirectResponse
    {
        try {
            $file = $this->backups->create();
            $this->logger->log('backup.create', "Created backup {$file}");

            return back()->with('success', "Backup created and verified: {$file}");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function verify(string $filename): RedirectResponse
    {
        $result = $this->backups->verify($filename);

        return back()->with($result['ok'] ? 'success' : 'error', "{$filename}: {$result['message']}");
    }

    public function download(string $filename): BinaryFileResponse
    {
        $path = $this->backups->path($filename);
        abort_unless($path, 404);

        $this->logger->log('backup.download', "Downloaded backup {$filename}");

        return response()->download($path);
    }

    public function destroy(string $filename): RedirectResponse
    {
        $this->backups->delete($filename);
        $this->logger->log('backup.delete', "Deleted backup {$filename}");

        return back()->with('success', 'Backup deleted.');
    }
}
