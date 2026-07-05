@extends('layouts.app')
@section('title', 'Semester Fees')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Semester Fees</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#feeModal"><i class="fa-solid fa-plus me-1"></i> Add Fee</button>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value">{{ hsms_money($summary['total']) }}</div><div class="stat-label">Total Fees</div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-success">{{ hsms_money($summary['paid']) }}</div><div class="stat-label">Collected</div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-danger">{{ hsms_money($summary['due']) }}</div><div class="stat-label">Outstanding</div></div></div></div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All statuses</option>
            @foreach(['pending','partial','paid'] as $st)<option value="{{ $st }}" @selected(request('status')===$st)>{{ ucfirst($st) }}</option>@endforeach
        </select>
    </div>
    <div class="col-6 col-md-3">
        <select name="semester" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All semesters</option>
            @foreach(config('hsms.semesters') as $n)<option value="{{ $n }}" @selected(request('semester')==$n)>Semester {{ $n }}</option>@endforeach
        </select>
    </div>
</form>

<div class="card stat-card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-datatable>
            <thead><tr><th>Student</th><th>Sem</th><th>Total</th><th>Paid</th><th>Balance</th><th>Due Date</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @foreach($fees as $fee)
                <tr>
                    <td><a href="{{ route('admin.students.show', $fee->student) }}" class="text-decoration-none">{{ $fee->student->name }}</a></td>
                    <td>@if(($fee->period_type ?? 'semester') === 'yearly')<span class="badge bg-primary-subtle text-primary">Year {{ $fee->semester }}</span>@else{{ $fee->semester }}@endif</td>
                    <td>{{ hsms_money($fee->total_fee) }}</td>
                    <td class="text-success">{{ hsms_money($fee->paid_amount) }}</td>
                    <td class="text-danger">{{ hsms_money($fee->balance) }}</td>
                    <td>{{ optional($fee->due_date)->format('d-m-Y') ?? '—' }}</td>
                    <td>
                        <span class="badge bg-{{ $fee->status==='paid'?'success':($fee->status==='partial'?'warning text-dark':'danger') }}">{{ ucfirst($fee->status) }}</span>
                        @if($fee->promise_date && $fee->status !== 'paid')
                            <span class="badge bg-info-subtle text-info" title="{{ $fee->promise_note }}"><i class="fa-solid fa-clock me-1"></i>{{ $fee->promise_date->format('d-m') }}</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($fee->status !== 'paid')
                            <button class="btn btn-sm btn-primary" onclick="prepCollect('{{ route('admin.semester-fees.collect', $fee) }}', 'Sem {{ $fee->semester }} · {{ $fee->student->name }}', {{ $fee->balance }})">
                                <i class="fa-solid fa-indian-rupee-sign me-1"></i> Collect
                            </button>
                            <button class="btn btn-sm btn-light" title="Promise to pay"
                                onclick="prepPromise('{{ route('admin.promise.update', ['semester_fee', $fee->id]) }}', '{{ $fee->student->name }}', '{{ optional($fee->promise_date)->format('Y-m-d') }}', @js($fee->promise_note))">
                                <i class="fa-solid fa-calendar-check"></i>
                            </button>
                        @endif
                        <form action="{{ route('admin.semester-fees.destroy', $fee) }}" method="POST" class="d-inline" data-confirm="Delete this fee record?">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div></div>

{{-- Add fee modal --}}
<div class="modal fade" id="feeModal" tabindex="-1"><div class="modal-dialog">
    <form class="modal-content" method="POST" action="{{ route('admin.semester-fees.store') }}">@csrf
        <div class="modal-header"><h5 class="modal-title">Add Semester Fee</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            @if($students->isEmpty())
                <p class="text-muted mb-0">No college students found. Add a student with occupation "Student" first.</p>
            @else
            <div class="mb-3">
                <label class="form-label">Student</label>
                <select name="student_id" class="form-select" data-select2 required>
                    <option value="">Select…</option>
                    @foreach($students as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                </select>
            </div>
            <div class="row g-3">
                <div class="col-4"><label class="form-label">Semester</label>
                    <select name="semester" class="form-select" required>@foreach(config('hsms.semesters') as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach</select></div>
                <div class="col-4"><label class="form-label">Total Fee (₹)</label><input type="number" step="0.01" min="0" name="total_fee" class="form-control" required></div>
                <div class="col-4"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control"></div>
            </div>
            @endif
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" @disabled($students->isEmpty())>Add</button></div>
    </form>
</div></div>

@include('admin.partials.collect_modal')
@include('admin.partials.promise_modal')
@endsection
