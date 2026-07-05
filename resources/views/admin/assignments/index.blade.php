@extends('layouts.app')
@section('title', 'Bed Assignments')

@section('content')
<div x-data="assignmentList()" class="page-enter">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 fw-bold mb-0">Bed Assignments</h1>
        <a href="{{ route('admin.property.index') }}" class="btn btn-premium d-none d-md-inline-flex">
            <i class="fa-solid fa-building me-1"></i> Property Board
        </a>
    </div>

    <!-- Search -->
    <input type="text" class="form-control mb-3" placeholder="Search by student, room, bed..." x-model="query"
           style="border-radius: var(--he-radius-md);">

    <!-- Assignment Cards -->
    <div class="stagger">
        @forelse($assignments as $a)
        <div class="due-card assignment-item mb-2"
             x-show="matchesSearch('{{ strtolower(addslashes($a->student->name)) }}', '{{ strtolower($a->bed->room->room_number) }}', '{{ strtolower($a->bed->bed_number) }}')"
             x-transition.opacity.duration.200ms>
            <div class="d-flex align-items-start gap-3">
                <!-- Student Info -->
                <a href="{{ route('admin.students.show', $a->student) }}" class="flex-shrink-0">
                    <img src="{{ $a->student->photo_url }}" class="rounded-3" style="width:42px;height:42px;object-fit:cover;" alt="">
                </a>
                <div class="flex-grow-1 min-width-0">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="min-width-0">
                            <a href="{{ route('admin.students.show', $a->student) }}" class="fw-semibold text-decoration-none text-truncate d-block" style="color: var(--he-text-main);">
                                {{ $a->student->name }}
                            </a>
                            <div style="font-size: var(--he-text-xs); color: var(--he-text-muted);">
                                <x-mobile-link :mobile="$a->student->mobile" />
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <div class="fw-bold" style="font-size: var(--he-text-sm);">{{ hostelease_money($a->fee_amount) }}</div>
                            <div style="font-size: var(--he-text-xs); color: var(--he-text-muted);">/ {{ $a->feeFrequencyLabel() }}</div>
                        </div>
                    </div>

                    <!-- Location & Date Row -->
                    <div class="d-flex flex-wrap gap-2 mt-2" style="font-size: var(--he-text-xs);">
                        <span class="badge-premium bg-primary-subtle text-primary">
                            <i class="fa-solid fa-bed me-1"></i>
                            {{ $a->bed->room->floor->name }} · {{ $a->bed->room->room_number }} · {{ $a->bed->bed_number }}
                        </span>
                        <span class="text-muted">
                            <i class="fa-regular fa-calendar me-1"></i>{{ $a->join_date->format('d M Y') }} · {{ $a->durationInDays() }}d
                        </span>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex gap-1 mt-2 flex-wrap">
                        @php
                            $relData = [
                                'id' => $a->id,
                                'name' => $a->student->name,
                                'bed' => $a->bed->room->room_number.' / '.$a->bed->bed_number,
                                'join' => $a->join_date->toDateString(),
                            ];
                        @endphp
                        <button class="btn btn-sm btn-outline-primary" style="border-radius: var(--he-radius-sm); font-size: var(--he-text-xs);"
                                data-bs-toggle="modal" data-bs-target="#feeModal"
                                onclick="prepFee(@js(['id' => $a->id, 'name' => $a->student->name, 'amount' => (float) $a->fee_amount, 'frequency' => $a->fee_frequency]))">
                            <i class="fa-solid fa-pen me-1"></i>Fee
                        </button>
                        <button class="btn btn-sm btn-outline-danger" style="border-radius: var(--he-radius-sm); font-size: var(--he-text-xs);"
                                data-bs-toggle="modal" data-bs-target="#releaseModal"
                                onclick="prepRelease(@js($relData))">
                            <i class="fa-solid fa-right-from-bracket me-1"></i>Release
                        </button>
                        <a href="{{ route('admin.beds.history', $a->bed) }}" class="btn btn-sm btn-light" style="border-radius: var(--he-radius-sm); font-size: var(--he-text-xs);">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="empty-state">
            <i class="fa-solid fa-bed d-block"></i>
            <p>No active bed assignments yet.</p>
            <a href="{{ route('admin.property.index') }}" class="btn btn-premium mt-2">
                <i class="fa-solid fa-building me-1"></i> Go to Property Board
            </a>
        </div>
        @endforelse
    </div>

    <!-- Mobile FAB -->
    <a href="{{ route('admin.property.index') }}" class="fab" title="Assign Student">
        <i class="fa-solid fa-plus"></i>
    </a>
</div>

{{-- Release modal --}}
<div class="modal fade" id="releaseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="releaseForm" method="POST" style="border-radius: var(--he-radius-lg); overflow: hidden;">
            @csrf @method('PATCH')
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Release Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
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
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: var(--he-radius-sm);">Cancel</button>
                <button type="submit" class="btn btn-danger" style="border-radius: var(--he-radius-sm);">Release</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit fee modal --}}
<div class="modal fade" id="feeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="feeForm" method="POST" style="border-radius: var(--he-radius-lg); overflow: hidden;">
            @csrf @method('PATCH')
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Fee — <span id="feeName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3" style="border-radius: var(--he-radius-sm);">
                    <i class="fa-solid fa-circle-info me-1"></i> Corrects this assignment's fee and updates the linked due.
                </div>
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
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: var(--he-radius-sm);">Cancel</button>
                <button type="submit" class="btn btn-premium">Save</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('assignmentList', () => ({
        query: '',
        matchesSearch(name, room, bed) {
            if (!this.query) return true;
            const q = this.query.toLowerCase().trim();
            return name.includes(q) || room.includes(q) || bed.includes(q);
        }
    }));
});

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
function prepTransfer(a) {
    document.getElementById('transferForm').action = relBase + '/' + a.id + '/transfer';
    document.getElementById('trName').textContent = a.name;
}
function feeToggleSem() {
    const f = document.getElementById('feeFreq').value;
    document.getElementById('feeSemWrap').style.display = (f === 'semester') ? '' : 'none';
}
</script>
@endpush
@endsection
