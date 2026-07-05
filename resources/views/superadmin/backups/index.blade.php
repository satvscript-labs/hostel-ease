@extends('layouts.app')
@section('title', 'Backups')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Database Backups</h1>
    <form method="POST" action="{{ route('superadmin.backups.store') }}">@csrf
        <button class="btn btn-primary"><i class="fa-solid fa-database me-1"></i> Create Backup Now</button>
    </form>
</div>

<div class="alert alert-info py-2 small">
    <i class="fa-solid fa-circle-info me-1"></i>
    Nightly auto-backup runs at 02:00 (keeps 30 days) when the scheduler/cron is active.
    Backups are stored in <code>storage/app/backups</code>. On XAMPP, set
    <code>DB_DUMP_BINARY=D:\xampp\mysql\bin\mysqldump.exe</code> in <code>.env</code> if mysqldump isn't on PATH.
</div>

<div class="card stat-card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>File</th><th>Size</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($backups as $b)
                <tr>
                    <td class="fw-semibold">{{ $b['name'] }}</td>
                    <td>{{ number_format($b['size'] / 1024, 1) }} KB</td>
                    <td>{{ $b['created_at']->format('d M Y H:i') }}</td>
                    <td class="text-end">
                        <a href="{{ route('superadmin.backups.download', $b['name']) }}" class="btn btn-sm btn-light"><i class="fa-solid fa-download"></i></a>
                        <form action="{{ route('superadmin.backups.destroy', $b['name']) }}" method="POST" class="d-inline" data-confirm="Delete this backup?">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-4">No backups yet. Click <strong>Create Backup Now</strong>.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div></div>
@endsection
