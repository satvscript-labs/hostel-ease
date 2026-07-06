@extends('layouts.app')
@section('title', 'System Backups')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1 text-dark tracking-tight">System Backups</h1>
        <p class="text-muted mb-0 small">Secure database snapshots and automated recovery points.</p>
    </div>
    <div>
        <form method="POST" action="{{ route('superadmin.backups.store') }}" class="d-inline">@csrf
            <button class="btn btn-primary shadow-sm rounded-pill px-4">
                <i class="fa-solid fa-cloud-arrow-up me-2"></i> Create Backup Now
            </button>
        </form>
    </div>
</div>

@php
    $totalSize = collect($backups)->sum('size');
    $lastBackup = count($backups) > 0 ? $backups[0] : null;
    $statusClass = $lastBackup && $lastBackup['created_at']->diffInHours(now()) < 24 ? 'success' : 'warning';
    $statusText = $lastBackup && $lastBackup['created_at']->diffInHours(now()) < 24 ? 'Healthy' : 'Needs Backup';
@endphp

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card stat-card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4 d-flex flex-column justify-content-center">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h2 class="h5 fw-bold mb-0 text-dark">Storage Health</h2>
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fa-solid fa-hard-drive"></i>
                    </div>
                </div>
                <div class="d-flex align-items-end gap-2 mb-2">
                    <div class="fs-2 fw-bold text-dark lh-1">{{ number_format($totalSize / 1024 / 1024, 2) }}</div>
                    <div class="text-muted fw-semibold mb-1">MB Used</div>
                </div>
                <div class="progress mt-2" style="height: 6px; border-radius: 10px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ min(100, ($totalSize / 1024 / 1024 / 100) * 100) }}%"></div>
                </div>
                <div class="small text-muted mt-2">{{ count($backups) }} snapshot(s) retained.</div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <div class="card stat-card border-0 shadow-sm rounded-4 h-100 position-relative overflow-hidden">
            <div class="position-absolute top-0 end-0 p-4 opacity-10">
                <i class="fa-solid fa-shield-halved" style="font-size: 6rem;"></i>
            </div>
            <div class="card-body p-4 d-flex flex-column justify-content-center position-relative z-1">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h2 class="h5 fw-bold mb-0 text-dark">Last Backup Status</h2>
                    <span class="badge bg-{{ $statusClass }}-subtle text-{{ $statusClass }} border border-{{ $statusClass }}-subtle rounded-pill px-3 py-2">
                        <i class="fa-solid fa-circle me-1" style="font-size: 0.5rem; vertical-align: middle;"></i> {{ $statusText }}
                    </span>
                </div>
                @if($lastBackup)
                    <div class="fs-3 fw-bold text-dark lh-1 mb-2">{{ $lastBackup['created_at']->diffForHumans() }}</div>
                    <div class="text-muted mb-0">{{ $lastBackup['created_at']->format('l, j M Y \a\t H:i') }} ({{ number_format($lastBackup['size'] / 1024, 1) }} KB)</div>
                @else
                    <div class="fs-4 fw-bold text-muted lh-1 mb-2">No backups found</div>
                    <div class="text-muted mb-0">Run a manual backup to secure your database.</div>
                @endif
                <div class="mt-4 alert alert-info py-2 small mb-0 border-0 bg-info-subtle text-info-emphasis rounded-3">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Nightly auto-backup runs at 02:00 (keeps 30 days). Backups stored in <code>storage/app/backups</code>.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card stat-card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                    <tr>
                        <th class="py-3 px-4 text-muted fw-semibold border-0">Snapshot File</th>
                        <th class="py-3 px-4 text-muted fw-semibold border-0">Size</th>
                        <th class="py-3 px-4 text-muted fw-semibold border-0">Created At</th>
                        <th class="py-3 px-4 text-end border-0">Actions</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                @forelse($backups as $b)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center text-muted" style="width: 36px; height: 36px;">
                                    <i class="fa-solid fa-file-zipper"></i>
                                </div>
                                <div class="fw-semibold text-dark font-monospace small">{{ $b['name'] }}</div>
                            </div>
                        </td>
                        <td class="px-4 py-3 fw-medium text-dark">{{ number_format($b['size'] / 1024 / 1024, 2) }} MB</td>
                        <td class="px-4 py-3 text-muted">
                            {{ $b['created_at']->format('d M Y H:i') }}
                            <span class="small ms-1 opacity-75">({{ $b['created_at']->diffForHumans() }})</span>
                        </td>
                        <td class="px-4 py-3 text-end">
                            <a href="{{ route('superadmin.backups.download', $b['name']) }}" class="btn btn-sm btn-primary rounded-circle shadow-sm mx-1" style="width: 32px; height: 32px;" title="Download">
                                <i class="fa-solid fa-download"></i>
                            </a>
                            <form action="{{ route('superadmin.backups.destroy', $b['name']) }}" method="POST" class="d-inline" data-confirm="Delete this snapshot permanently?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-light rounded-circle shadow-sm" style="width: 32px; height: 32px;" title="Delete">
                                    <i class="fa-solid fa-trash text-danger"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-5">
                        <i class="fa-solid fa-ghost fs-1 text-light mb-3"></i>
                        <p class="mb-0">No backups yet. Click <strong>Create Backup Now</strong> to get started.</p>
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
