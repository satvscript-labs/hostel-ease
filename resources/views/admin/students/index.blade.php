@extends('layouts.app')
@section('title', 'Students')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Students</h1>
    <a href="{{ route('admin.students.create') }}" class="btn btn-primary">
        <i class="fa-solid fa-user-plus me-1"></i> Add Student
    </a>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <option value="active" @selected(request('status')==='active')>Active</option>
            <option value="left" @selected(request('status')==='left')>Left</option>
        </select>
    </div>
    <div class="col-6 col-md-3">
        <select name="occupation" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All occupations</option>
            @foreach(config('hsms.occupation_types') as $k => $label)
                <option value="{{ $k }}" @selected(request('occupation')===$k)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</form>

<div class="card stat-card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-datatable>
                <thead>
                    <tr><th>Student</th><th>Mobile</th><th>Occupation</th><th>Bed</th><th>Join</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                @foreach($students as $s)
                    @php($asg = $s->activeAssignment)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <img src="{{ $s->photo_url }}" class="avatar-sm" alt="">
                                <a href="{{ route('admin.students.show', $s) }}" class="fw-semibold text-decoration-none">{{ $s->name }}</a>
                            </div>
                        </td>
                        <td><x-mobile-link :mobile="$s->mobile" /></td>
                        <td>{{ config('hsms.occupation_types.'.$s->occupation_type) }}</td>
                        <td>{{ $asg ? $asg->bed->room->room_number.' / '.$asg->bed->bed_number : '—' }}</td>
                        <td>{{ optional($s->join_date)->format('d-m-Y') ?? '—' }}</td>
                        <td>
                            <span class="badge bg-{{ $s->status === 'active' ? 'success' : 'secondary' }}-subtle text-{{ $s->status === 'active' ? 'success' : 'secondary' }}">
                                {{ ucfirst($s->status) }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.students.show', $s) }}" class="btn btn-sm btn-light"><i class="fa-solid fa-eye"></i></a>
                            <a href="{{ route('admin.students.edit', $s) }}" class="btn btn-sm btn-light"><i class="fa-solid fa-pen"></i></a>
                            <form action="{{ route('admin.students.destroy', $s) }}" method="POST" class="d-inline"
                                  data-confirm="Delete student {{ $s->name }}?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
