@extends('layouts.app')
@section('title', 'Bed Assignments')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Bed Assignments</h1>
    <a href="{{ route('admin.assignments.create') }}" class="btn btn-primary">
        <i class="fa-solid fa-bed-pulse me-1"></i> Assign Student
    </a>
</div>

<div class="card stat-card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-datatable>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th class="d-none d-md-table-cell">Mobile</th>
                        <th>Floor / Room / Bed</th>
                        <th class="d-none d-sm-table-cell">Join Date</th>
                        <th>Fee</th>
                        <th class="d-none d-lg-table-cell">Days</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($assignments as $a)
                    <tr>
                        <td><a href="{{ route('admin.students.show', $a->student) }}" class="fw-semibold text-decoration-none">{{ $a->student->name }}</a></td>
                        <td class="d-none d-md-table-cell"><x-mobile-link :mobile="$a->student->mobile" /></td>
                        <td>{{ $a->bed->room->floor->name }} · {{ $a->bed->room->room_number }} · <span class="badge bg-danger-subtle text-danger">{{ $a->bed->bed_number }}</span></td>
                        <td class="d-none d-sm-table-cell">{{ $a->join_date->format('d-m-Y') }}</td>
                        <td class="text-nowrap">{{ hostelease_money($a->fee_amount) }} <small class="text-muted">/ {{ $a->feeFrequencyLabel() }}</small></td>
                        <td class="d-none d-lg-table-cell">{{ $a->durationInDays() }}</td>
                        <td class="text-end">
                            @php
                                $relData = [
                                    'id' => $a->id,
                                    'name' => $a->student->name,
                                    'bed' => $a->bed->room->room_number.' / '.$a->bed->bed_number,
                                    'join' => $a->join_date->toDateString(),
                                ];
                            @endphp
                            <div class="d-inline-flex gap-1 flex-nowrap justify-content-end">
                                <button class="btn btn-sm btn-outline-primary text-nowrap"
                                        data-bs-toggle="modal" data-bs-target="#feeModal" title="Edit fee"
                                        onclick="prepFee(@js(['id' => $a->id, 'name' => $a->student->name, 'amount' => (float) $a->fee_amount, 'frequency' => $a->fee_frequency]))">
                                    <i class="fa-solid fa-pen"></i><span class="d-none d-sm-inline ms-1">Edit fee</span>
                                </button>
                                <button class="btn btn-sm btn-outline-danger text-nowrap"
                                        data-bs-toggle="modal" data-bs-target="#releaseModal" title="Release"
                                        onclick="prepRelease(@js($relData))">
                                    <i class="fa-solid fa-right-from-bracket"></i><span class="d-none d-sm-inline ms-1">Release</span>
                                </button>
                                <a href="{{ route('admin.beds.history', $a->bed) }}" class="btn btn-sm btn-light" title="Bed history"><i class="fa-solid fa-clock-rotate-left"></i></a>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Release modal --}}
<div class="modal fade" id="releaseModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="releaseForm" method="POST">
            @csrf @method('PATCH')
            <div class="modal-header"><h5 class="modal-title">Release Student</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="mb-3">Release <strong id="relName"></strong> from bed <strong id="relBed"></strong>? The bed becomes empty and this stay is kept in history.</p>
                <div class="mb-3">
                    <label class="form-label">Leave Date</label>
                    <input type="date" name="leave_date" id="relDate" class="form-control" value="{{ now()->toDateString() }}">
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="mark_student_left" value="1" id="markLeft">
                    <label class="form-check-label" for="markLeft">Also mark the student as <strong>Left</strong> (vacating the hostel)</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Release</button>
            </div>
        </form>
    </div>
</div>
{{-- Edit fee modal --}}
<div class="modal fade" id="feeModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="feeForm" method="POST">
            @csrf @method('PATCH')
            <div class="modal-header"><h5 class="modal-title">Edit Fee — <span id="feeName"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3"><i class="fa-solid fa-circle-info me-1"></i> Corrects this assignment's fee and updates the linked due. Frequency can't change once a payment was collected.</div>
                <div class="mb-3">
                    <label class="form-label">Fee Amount (₹)</label>
                    <input type="number" step="0.01" min="0" name="fee_amount" id="feeAmount" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Fee Frequency</label>
                    <select name="fee_frequency" id="feeFreq" class="form-select" required onchange="feeToggleSem()">
                        @foreach(config('hostelease.fee_frequencies') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div class="mb-1" id="feeSemWrap">
                    <label class="form-label">Semester</label>
                    <select name="semester" id="feeSem" class="form-select">
                        @foreach(config('hostelease.semesters') as $s)<option value="{{ $s }}">Semester {{ $s }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const relBase = "{{ url('admin/assignments') }}";
    function prepRelease(a) {
        document.getElementById('releaseForm').action = relBase + '/' + a.id + '/release';
        document.getElementById('relName').textContent = a.name;
        document.getElementById('relBed').textContent = a.bed;
        const d = document.getElementById('relDate');
        d.min = a.join;
    }
    function prepFee(a) {
        document.getElementById('feeForm').action = relBase + '/' + a.id + '/fee';
        document.getElementById('feeName').textContent = a.name;
        document.getElementById('feeAmount').value = a.amount;
        document.getElementById('feeFreq').value = a.frequency;
        feeToggleSem();
    }
    function feeToggleSem() {
        const f = document.getElementById('feeFreq').value;
        document.getElementById('feeSemWrap').style.display = (f === 'semester') ? '' : 'none';
    }
</script>
@endpush

