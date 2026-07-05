@extends('layouts.app')
@section('title', 'Staff')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Staff
        <span class="badge bg-primary-subtle text-primary">{{ $summary['active'] }}/{{ $summary['total'] }} active</span>
        <span class="badge bg-success-subtle text-success">Payroll {{ hsms_money($summary['payroll']) }}/mo</span>
    </h1>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.staff.attendance') }}" class="btn btn-outline-primary"><i class="fa-solid fa-clipboard-check me-1"></i> Attendance</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#staffModal" onclick="resetStaff()"><i class="fa-solid fa-user-plus me-1"></i> Add Staff</button>
    </div>
</div>

<div class="card stat-card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-datatable>
            <thead><tr><th>Name</th><th>Designation</th><th>Mobile</th><th>Salary</th><th>Present (mo)</th><th>Paid (mo)</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @foreach($staff as $s)
                @php($sd = ['id'=>$s->id,'name'=>$s->name,'designation'=>$s->designation,'mobile'=>$s->mobile,'monthly_salary'=>(float)$s->monthly_salary,'join_date'=>optional($s->join_date)->format('Y-m-d'),'address'=>$s->address,'is_active'=>(bool)$s->is_active,'notes'=>$s->notes])
                <tr>
                    <td class="fw-semibold"><a href="{{ route('admin.staff.show', $s) }}">{{ $s->name }}</a></td>
                    <td>{{ $s->designation ?? '—' }}</td>
                    <td>@if($s->mobile)<x-mobile-link :mobile="$s->mobile" />@else — @endif</td>
                    <td>{{ hsms_money($s->monthly_salary) }}</td>
                    <td>{{ $s->present_this_month }}</td>
                    <td>{{ hsms_money($s->paid_this_month) }}</td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#salaryModal" onclick="paySalary({{ $s->id }}, @js($s->name))"><i class="fa-solid fa-money-bill"></i></button>
                        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#staffModal" onclick="editStaff(@js($sd))"><i class="fa-solid fa-pen"></i></button>
                        <form action="{{ route('admin.staff.destroy', $s) }}" method="POST" class="d-inline" data-confirm="Remove {{ $s->name }}?">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div></div>

{{-- Add / Edit modal --}}
<div class="modal fade" id="staffModal" tabindex="-1"><div class="modal-dialog">
    <form class="modal-content" id="staffForm" method="POST" action="{{ route('admin.staff.store') }}">@csrf
        <input type="hidden" name="_method" id="staffMethod" value="POST">
        <div class="modal-header"><h5 class="modal-title" id="staffTitle">Add Staff</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="row g-3">
            <div class="col-6"><label class="form-label">Name *</label><input type="text" name="name" id="st_name" class="form-control" required></div>
            <div class="col-6"><label class="form-label">Designation</label><input type="text" name="designation" id="st_designation" class="form-control" placeholder="Cook, Guard…"></div>
            <div class="col-6"><label class="form-label">Mobile</label><div class="input-group"><span class="input-group-text">+91</span><input type="tel" name="mobile" id="st_mobile" maxlength="10" class="form-control"></div></div>
            <div class="col-6"><label class="form-label">Monthly salary *</label><input type="number" step="0.01" name="monthly_salary" id="st_salary" class="form-control" required></div>
            <div class="col-6"><label class="form-label">Join date</label><input type="date" name="join_date" id="st_join" class="form-control"></div>
            <div class="col-6 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="st_active" checked><label class="form-check-label" for="st_active">Active</label></div></div>
            <div class="col-12"><label class="form-label">Address</label><input type="text" name="address" id="st_address" class="form-control"></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
    </form>
</div></div>

{{-- Pay salary modal --}}
<div class="modal fade" id="salaryModal" tabindex="-1"><div class="modal-dialog">
    <form class="modal-content" id="salaryForm" method="POST">@csrf
        <div class="modal-header"><h5 class="modal-title">Pay Salary — <span id="sal_name"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="row g-3">
            <div class="col-6"><label class="form-label">Salary month *</label><input type="month" name="salary_month" class="form-control" value="{{ now()->format('Y-m') }}" required></div>
            <div class="col-6"><label class="form-label">Amount *</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
            <div class="col-6"><label class="form-label">Mode</label><select name="mode" class="form-select"><option value="cash">Cash</option><option value="upi">UPI</option><option value="bank">Bank transfer</option></select></div>
            <div class="col-6"><label class="form-label">Paid on *</label><input type="date" name="paid_on" class="form-control" value="{{ now()->format('Y-m-d') }}" required></div>
            <div class="col-12"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control"></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Record</button></div>
    </form>
</div></div>
@endsection

@push('scripts')
<script>
    const staffForm = document.getElementById('staffForm');
    const storeUrl = "{{ route('admin.staff.store') }}";
    function resetStaff() {
        staffForm.action = storeUrl; document.getElementById('staffMethod').value = 'POST';
        document.getElementById('staffTitle').textContent = 'Add Staff';
        ['name','designation','mobile','salary','join','address'].forEach(f => document.getElementById('st_'+f).value = '');
        document.getElementById('st_active').checked = true;
    }
    function editStaff(s) {
        staffForm.action = "{{ url('admin/staff') }}/" + s.id; document.getElementById('staffMethod').value = 'PUT';
        document.getElementById('staffTitle').textContent = 'Edit Staff';
        document.getElementById('st_name').value = s.name || '';
        document.getElementById('st_designation').value = s.designation || '';
        document.getElementById('st_mobile').value = (s.mobile || '').slice(-10);
        document.getElementById('st_salary').value = s.monthly_salary || '';
        document.getElementById('st_join').value = s.join_date || '';
        document.getElementById('st_address').value = s.address || '';
        document.getElementById('st_active').checked = !!s.is_active;
    }
    function paySalary(id, name) {
        document.getElementById('salaryForm').action = "{{ url('admin/staff') }}/" + id + "/salary";
        document.getElementById('sal_name').textContent = name;
    }
</script>
@endpush
