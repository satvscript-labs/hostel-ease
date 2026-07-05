@extends('layouts.app')
@section('title', 'Bed History')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('admin.beds.layout') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
    <h1 class="h4 fw-bold mb-0">
        Bed History — {{ $bed->room->floor->name }} · Room {{ $bed->room->room_number }} · Bed {{ $bed->bed_number }}
    </h1>
    <span class="badge bg-{{ $bed->status === 'occupied' ? 'danger' : ($bed->status === 'empty' ? 'success' : 'secondary') }}">
        {{ ucfirst($bed->status) }}
    </span>
</div>

<div class="card stat-card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Student</th><th>Mobile</th><th>Join</th><th>Leave</th><th>Duration</th><th>Fee</th><th>Paid (this stay)</th><th></th></tr>
                </thead>
                <tbody>
                @forelse($assignments as $a)
                    <tr class="{{ $a->is_active ? 'table-success' : '' }}">
                        <td>
                            <a href="{{ route('admin.students.show', $a->student) }}" class="fw-semibold text-decoration-none">{{ $a->student->name }}</a>
                            @if($a->is_active)<span class="badge bg-success ms-1">Current</span>@endif
                        </td>
                        <td><x-mobile-link :mobile="$a->student->mobile" /></td>
                        <td>{{ $a->join_date->format('d-m-Y') }}</td>
                        <td>{{ optional($a->leave_date)->format('d-m-Y') ?? '—' }}</td>
                        <td>{{ $a->durationInDays() }} days</td>
                        <td>{{ hsms_money($a->fee_amount) }} <small class="text-muted">/ {{ $a->feeFrequencyLabel() }}</small></td>
                        <td>{{ hsms_money($a->window_paid) }}</td>
                        <td>{{ $a->remarks }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">This bed has never been occupied.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
