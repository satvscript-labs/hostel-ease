@extends('layouts.app')
@section('title', 'Complaints')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Complaints / Tickets</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#cmpModal"><i class="fa-solid fa-plus me-1"></i> Log Complaint</button>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-danger">{{ $counts['open'] }}</div><div class="stat-label">Open</div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-warning">{{ $counts['in_progress'] }}</div><div class="stat-label">In Progress</div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-success">{{ $counts['resolved'] }}</div><div class="stat-label">Resolved</div></div></div></div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All statuses</option>
            @foreach(config('hostelease.complaint_statuses') as $k=>$l)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <div class="col-6 col-md-3">
        <select name="priority" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All priorities</option>
            @foreach(config('hostelease.complaint_priorities') as $k=>$l)<option value="{{ $k }}" @selected(request('priority')===$k)>{{ $l }}</option>@endforeach
        </select>
    </div>
</form>

<div class="card stat-card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Title</th><th>Student</th><th>Category</th><th>Priority</th><th>Status</th><th>Logged</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($complaints as $c)
                <tr>
                    <td class="fw-semibold">{{ $c->title }}</td>
                    <td>{{ $c->student?->name ?? '—' }}</td>
                    <td>{{ config('hostelease.complaint_categories.'.$c->category, $c->category) }}</td>
                    <td><span class="badge bg-{{ $c->priority==='high'?'danger':($c->priority==='medium'?'warning text-dark':'secondary') }}">{{ ucfirst($c->priority) }}</span></td>
                    <td><span class="badge bg-{{ $c->status==='resolved'||$c->status==='closed'?'success':($c->status==='in_progress'?'warning text-dark':'danger') }}">{{ config('hostelease.complaint_statuses.'.$c->status) }}</span></td>
                    <td class="small">{{ $c->created_at->format('d M Y') }}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#upd{{ $c->id }}"><i class="fa-solid fa-pen"></i></button>
                        <form action="{{ route('admin.complaints.destroy', $c) }}" method="POST" class="d-inline" data-confirm="Delete this complaint?">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                {{-- Update status modal --}}
                <div class="modal fade" id="upd{{ $c->id }}" tabindex="-1"><div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('admin.complaints.update', $c) }}">@csrf @method('PATCH')
                        <div class="modal-header"><h5 class="modal-title">Update — {{ $c->title }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            @if($c->description)<p class="text-muted small">{{ $c->description }}</p>@endif
                            <div class="mb-3"><label class="form-label">Status</label>
                                <select name="status" class="form-select">@foreach(config('hostelease.complaint_statuses') as $k=>$l)<option value="{{ $k }}" @selected($c->status===$k)>{{ $l }}</option>@endforeach</select></div>
                            <div class="mb-1"><label class="form-label">Resolution / Notes</label><textarea name="resolution" class="form-control" rows="2">{{ $c->resolution }}</textarea></div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
                    </form>
                </div></div>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No complaints logged.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div></div>

<div class="modal fade" id="cmpModal" tabindex="-1"><div class="modal-dialog">
    <form class="modal-content" method="POST" action="{{ route('admin.complaints.store') }}">@csrf
        <div class="modal-header"><h5 class="modal-title">Log Complaint</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-12"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div>
                <div class="col-6"><label class="form-label">Student (optional)</label>
                    <select name="student_id" class="form-select" data-select2><option value="">—</option>@foreach($students as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label">Category</label>
                    <select name="category" class="form-select" required>@foreach(config('hostelease.complaint_categories') as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label">Priority</label>
                    <select name="priority" class="form-select" required>@foreach(config('hostelease.complaint_priorities') as $k=>$l)<option value="{{ $k }}" @selected($k==='medium')>{{ $l }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Log</button></div>
    </form>
</div></div>
@endsection

