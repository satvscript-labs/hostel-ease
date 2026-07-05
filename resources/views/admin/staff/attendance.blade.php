@extends('layouts.app')
@section('title', 'Staff Attendance')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Staff Attendance</h1>
    <a href="{{ route('admin.staff.index') }}" class="btn btn-light"><i class="fa-solid fa-arrow-left me-1"></i> Staff</a>
</div>

<form method="GET" class="mb-3">
    <div class="input-group" style="max-width:280px;">
        <span class="input-group-text">Date</span>
        <input type="date" name="date" value="{{ $date }}" class="form-control" onchange="this.form.submit()">
    </div>
</form>

<form method="POST" action="{{ route('admin.staff.attendance.save') }}">
    @csrf
    <input type="hidden" name="date" value="{{ $date }}">
    <div class="card stat-card"><div class="card-body">
        @if($staff->isEmpty())
            <p class="text-muted mb-0">No active staff to mark.</p>
        @else
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Name</th><th>Designation</th><th class="text-end">Status</th></tr></thead>
                <tbody>
                @foreach($staff as $s)
                    @php($cur = $marks[$s->id]->status ?? 'present')
                    <tr>
                        <td class="fw-semibold">{{ $s->name }}</td>
                        <td>{{ $s->designation ?? '—' }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm" role="group">
                                @foreach(['present'=>'P','absent'=>'A','half_day'=>'H','leave'=>'L'] as $val => $lbl)
                                    <input type="radio" class="btn-check" name="status[{{ $s->id }}]" id="a{{ $s->id }}_{{ $val }}" value="{{ $val }}" @checked($cur===$val)>
                                    <label class="btn btn-outline-{{ ['present'=>'success','absent'=>'danger','half_day'=>'warning','leave'=>'secondary'][$val] }}" for="a{{ $s->id }}_{{ $val }}">{{ $lbl }}</label>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="text-end mt-3"><button class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save attendance</button></div>
        @endif
    </div></div>
</form>
@endsection
