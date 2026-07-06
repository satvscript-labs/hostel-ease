@extends('layouts.app')
@section('title', __('Staff Board'))

@section('content')
<div x-data="{ tab: '{{ request('tab', 'directory') }}', search: '' }" class="page-enter">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0">{{ __('Staff & Payroll') }}</h1>
            <p class="text-secondary">{{ __('Manage staff directory, payroll, and daily attendance.') }}</p>
        </div>
        <div>
            <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#staffModal" onclick="resetStaff()">
                <i class="fa-solid fa-plus me-1"></i> {{ __('Add Staff') }}
            </button>
        </div>
    </div>

    <!-- Bento Stats -->
    <div class="bento mb-4">
        <div class="bento-card">
            <div class="text-secondary small fw-medium mb-1">{{ __('Active Staff') }}</div>
            <div class="h3 mb-0">{{ $summary['active'] }} <span class="text-secondary fs-5">/ {{ $summary['total'] }}</span></div>
        </div>
        <div class="bento-card">
            <div class="text-secondary small fw-medium mb-1">{{ __('Monthly Payroll') }}</div>
            <div class="h3 mb-0 text-success">{{ hostelease_money($summary['payroll']) }}</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="he-tabs mb-4">
        <button class="he-tab" :class="{ 'active': tab === 'directory' }" @click="tab = 'directory'">
            <i class="fa-solid fa-address-book me-1"></i> {{ __('Directory & Payroll') }}
        </button>
        <button class="he-tab" :class="{ 'active': tab === 'attendance' }" @click="tab = 'attendance'">
            <i class="fa-solid fa-clipboard-user me-1"></i> {{ __('Attendance') }}
        </button>
    </div>

    <!-- Directory Tab -->
    <div x-show="tab === 'directory'" x-cloak>
        <div class="mb-4">
            <input type="text" x-model="search" class="form-control" placeholder="Search by name, role or mobile...">
        </div>

        <div class="row g-3">
            @forelse($staff as $s)
            @php($sd = ['id'=>$s->id,'name'=>$s->name,'designation'=>$s->designation,'mobile'=>$s->mobile,'monthly_salary'=>(float)$s->monthly_salary,'join_date'=>optional($s->join_date)->format('Y-m-d'),'address'=>$s->address,'is_active'=>(bool)$s->is_active,'notes'=>$s->notes])
            
            <div class="col-md-6 col-lg-4" x-show="search === '' || '{{ strtolower($s->name . ' ' . $s->designation . ' ' . $s->mobile) }}'.includes(search.toLowerCase())">
                <div class="card student-card h-100 {{ !$s->is_active ? 'opacity-75' : '' }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="d-flex gap-3 align-items-center">
                                <div class="avatar bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.2rem; font-weight: 600;">
                                    {{ substr($s->name, 0, 1) }}
                                </div>
                                <div>
                                    <h5 class="mb-1 fw-bold">
                                        <a href="{{ route('admin.staff.show', $s) }}" class="text-decoration-none text-dark stretched-link">{{ $s->name }}</a>
                                    </h5>
                                    <div class="text-secondary small">{{ $s->designation ?? 'Staff Member' }}</div>
                                </div>
                            </div>
                            <div style="position: relative; z-index: 2;">
                                @if($s->is_active)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Active</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">Inactive</span>
                                @endif
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Mobile</div>
                                <div class="fw-medium small" style="position: relative; z-index: 2;">
                                    @if($s->mobile)
                                        <x-mobile-link :mobile="$s->mobile" />
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Salary</div>
                                <div class="fw-medium small">{{ hostelease_money($s->monthly_salary) }}</div>
                            </div>
                            <div class="col-6">
                                <div class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Pres. (This Mo)</div>
                                <div class="fw-medium small">{{ $s->present_this_month }} days</div>
                            </div>
                            <div class="col-6">
                                <div class="text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Paid (This Mo)</div>
                                <div class="fw-medium small text-success">{{ hostelease_money($s->paid_this_month) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light border-0 d-flex gap-2" style="position: relative; z-index: 2;">
                        <button class="btn btn-sm btn-success flex-grow-1" data-bs-toggle="modal" data-bs-target="#salaryModal" onclick="paySalary({{ $s->id }}, @js($s->name))">
                            <i class="fa-solid fa-money-bill me-1"></i> Pay
                        </button>
                        <button class="btn btn-sm btn-white border flex-grow-1" data-bs-toggle="modal" data-bs-target="#staffModal" onclick="editStaff(@js($sd))">
                            <i class="fa-solid fa-pen me-1"></i> Edit
                        </button>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12 text-center py-5">
                <div class="empty-state">
                    <i class="fa-solid fa-id-badge text-secondary fs-1 mb-2"></i>
                    <div class="text-secondary">No staff members found.</div>
                </div>
            </div>
            @endforelse
        </div>
    </div>

    <!-- Attendance Tab -->
    <div x-show="tab === 'attendance'" x-cloak>
        <div class="card card-premium">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Daily Attendance</h5>
                <form method="GET" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="tab" value="attendance">
                    <label class="form-label mb-0 fw-medium">Date:</label>
                    <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm" style="width: 150px;" onchange="this.form.submit()">
                </form>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.staff.attendance.save') }}">
                    @csrf
                    <input type="hidden" name="date" value="{{ $date }}">
                    
                    @if($staff->where('is_active', true)->isEmpty())
                        <div class="empty-state py-5">
                            <i class="fa-solid fa-clipboard-check text-secondary fs-1 mb-2"></i>
                            <div class="text-secondary">No active staff to mark.</div>
                        </div>
                    @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Staff') }}</th>
                                    <th>{{ __('Designation') }}</th>
                                    <th class="text-end">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($staff->where('is_active', true) as $s)
                                @php($cur = $marks[$s->id]->status ?? 'present')
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $s->name }}</div>
                                    </td>
                                    <td class="text-secondary small">{{ $s->designation ?? '—' }}</td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            @foreach(['present'=>'P','absent'=>'A','half_day'=>'H','leave'=>'L'] as $val => $lbl)
                                                <input type="radio" class="btn-check" name="status[{{ $s->id }}]" id="a{{ $s->id }}_{{ $val }}" value="{{ $val }}" @checked($cur===$val)>
                                                <label class="btn btn-outline-{{ ['present'=>'success','absent'=>'danger','half_day'=>'warning','leave'=>'secondary'][$val] }} shadow-sm" for="a{{ $s->id }}_{{ $val }}">{{ $lbl }}</label>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-4">
                        <button class="btn btn-premium"><i class="fa-solid fa-save me-1"></i> Save Attendance</button>
                    </div>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Add / Edit modal --}}
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="staffForm" method="POST" action="{{ route('admin.staff.store') }}">
            @csrf
            <input type="hidden" name="_method" id="staffMethod" value="POST">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="staffTitle">Add Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" id="st_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Designation</label>
                        <input type="text" name="designation" id="st_designation" class="form-control" placeholder="Cook, Guard…">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mobile</label>
                        <div class="input-group">
                            <span class="input-group-text">+91</span>
                            <input type="tel" name="mobile" id="st_mobile" maxlength="10" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Monthly Salary *</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" name="monthly_salary" id="st_salary" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Join Date</label>
                        <input type="date" name="join_date" id="st_join" class="form-control">
                    </div>
                    <div class="col-md-6 d-flex align-items-center mt-4 pt-2">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="st_active" checked>
                            <label class="form-check-label ms-1 fs-6" for="st_active">Active Account</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" id="st_address" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-dark">Save Changes</button>
            </div>
        </form>
    </div>
</div>

{{-- Pay salary modal --}}
<div class="modal fade" id="salaryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="salaryForm" method="POST">
            @csrf
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Pay Salary</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 text-secondary small">Recording salary for <strong id="sal_name" class="text-dark"></strong></div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Salary Month *</label>
                        <input type="month" name="salary_month" class="form-control" value="{{ now()->format('Y-m') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Paid On *</label>
                        <input type="date" name="paid_on" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount *</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mode</label>
                        <select name="mode" class="form-select">
                            <option value="cash">Cash</option>
                            <option value="upi">UPI</option>
                            <option value="bank">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes / Reference No</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional notes...">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Record Payment</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const staffForm = document.getElementById('staffForm');
    const storeUrl = "{{ route('admin.staff.store') }}";
    
    function resetStaff() {
        staffForm.action = storeUrl; 
        document.getElementById('staffMethod').value = 'POST';
        document.getElementById('staffTitle').textContent = 'Add Staff';
        ['name','designation','mobile','salary','join','address'].forEach(f => document.getElementById('st_'+f).value = '');
        document.getElementById('st_active').checked = true;
    }
    
    function editStaff(s) {
        staffForm.action = "{{ url('admin/staff') }}/" + s.id; 
        document.getElementById('staffMethod').value = 'PUT';
        document.getElementById('staffTitle').textContent = 'Edit Staff';
        document.getElementById('st_name').value = s.name || '';
        document.getElementById('st_designation').value = s.designation || '';
        document.getElementById('st_mobile').value = (s.mobile || '').replace('+91', '');
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
