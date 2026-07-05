@extends('layouts.app')
@section('title', $staff->name)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">{{ $staff->name }} <small class="text-muted">{{ $staff->designation }}</small></h1>
    <a href="{{ route('admin.staff.index') }}" class="btn btn-light"><i class="fa-solid fa-arrow-left me-1"></i> Staff</a>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card stat-card"><div class="card-body">
            <p class="mb-1"><strong>Salary:</strong> {{ hostelease_money($staff->monthly_salary) }}/month</p>
            @if($staff->mobile)<p class="mb-1"><strong>Mobile:</strong> <x-mobile-link :mobile="$staff->mobile" /></p>@endif
            @if($staff->join_date)<p class="mb-1"><strong>Joined:</strong> {{ $staff->join_date->format('d-m-Y') }}</p>@endif
            <p class="mb-0"><strong>Status:</strong> <span class="badge bg-{{ $staff->is_active ? 'success' : 'secondary' }}">{{ $staff->is_active ? 'Active' : 'Inactive' }}</span></p>
        </div></div>
        <div class="card stat-card mt-3"><div class="card-body">
            <h6 class="fw-bold">Attendance this month</h6>
            <div class="d-flex gap-3">
                <div><span class="h5 text-success">{{ $counts['present'] }}</span><div class="small text-muted">Present</div></div>
                <div><span class="h5 text-danger">{{ $counts['absent'] }}</span><div class="small text-muted">Absent</div></div>
                <div><span class="h5 text-warning">{{ $counts['half_day'] }}</span><div class="small text-muted">Half</div></div>
                <div><span class="h5 text-secondary">{{ $counts['leave'] }}</span><div class="small text-muted">Leave</div></div>
            </div>
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="card stat-card"><div class="card-body">
            <h6 class="fw-bold">Salary payments</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Month</th><th>Amount</th><th>Mode</th><th>Paid on</th><th></th></tr></thead>
                    <tbody>
                    @forelse($payments as $p)
                        <tr>
                            <td>{{ $p->salary_month->format('M Y') }}</td>
                            <td>{{ hostelease_money($p->amount) }}</td>
                            <td>{{ ucfirst($p->mode) }}</td>
                            <td>{{ $p->paid_on->format('d-m-Y') }}</td>
                            <td class="text-end"><form action="{{ route('admin.staff.salary.destroy', [$staff, $p]) }}" method="POST" data-confirm="Delete this salary entry?">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No salary paid yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>
@endsection

