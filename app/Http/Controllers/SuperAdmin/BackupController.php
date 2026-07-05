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
        return view('superadmin.backups.index', ['backups' => $this->backups->list()]);
    }

    public function store(): RedirectResponse
    {
        try {
            $file = $this->backups->create();
            $this->logger->log('backup.create', "Created backup {$file}");

            return back()->with('success', "Backup created: {$file}");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function download(string $filename): BinaryFileResponse
    {
        $path = $this->backups->path($filename);
        abort_unless($path, 404);

        return response()->download($path);
    }

    public function destroy(string $filename): RedirectResponse
    {
        $this->backups->delete($filename);
        $this->logger->log('backup.delete', "Deleted backup {$filename}");

        return back()->with('success', 'Backup deleted.');
    }
}
