@extends('layouts.app')
@section('title', 'Payment Modes')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Payment Modes</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modeModal" onclick="resetModeForm()">
        <i class="fa-solid fa-plus me-1"></i> Add Mode
    </button>
</div>

<p class="text-muted small">These modes appear when collecting fees. Tick <strong>“requires reference”</strong> for
modes that need a cheque/UTR/transaction number (the reference field then becomes mandatory).</p>

<div class="card stat-card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Mode</th><th>Reference required?</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($modes as $m)
                @php($md = ['id' => $m->id, 'name' => $m->name, 'requires_reference' => (bool) $m->requires_reference, 'is_active' => (bool) $m->is_active])
                <tr>
                    <td class="fw-semibold">{{ $m->name }} <small class="text-muted">({{ $m->code }})</small></td>
                    <td>{!! $m->requires_reference ? '<span class="badge bg-warning text-dark">Yes</span>' : '<span class="text-muted">No</span>' !!}</td>
                    <td><span class="badge bg-{{ $m->is_active ? 'success' : 'secondary' }}">{{ $m->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-light"
                                data-bs-toggle="modal" data-bs-target="#modeModal"
                                onclick="editMode(@js($md))">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <form action="{{ route('admin.payment-modes.destroy', $m) }}" method="POST" class="d-inline" data-confirm="Delete payment mode &quot;{{ $m->name }}&quot;?">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-4">No payment modes yet. Add one.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div></div>

<div class="modal fade" id="modeModal" tabindex="-1"><div class="modal-dialog">
    <form class="modal-content" id="modeForm" method="POST" action="{{ route('admin.payment-modes.store') }}">
        @csrf
        <input type="hidden" name="_method" id="modeMethod" value="POST">
        <div class="modal-header"><h5 class="modal-title" id="modeTitle">Add Payment Mode</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" id="modeName" class="form-control" placeholder="e.g. PhonePe, Bank Transfer" required>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="requires_reference" value="1" id="modeRef">
                <label class="form-check-label" for="modeRef">Requires a reference number (cheque/UTR/txn id)</label>
            </div>
            <div class="form-check" id="modeActiveWrap" style="display:none;">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="modeActive" checked>
                <label class="form-check-label" for="modeActive">Active</label>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
    </form>
</div></div>
@endsection

@push('scripts')
<script>
    const modeForm = document.getElementById('modeForm');
    const storeUrl = "{{ route('admin.payment-modes.store') }}";
    function resetModeForm() {
        modeForm.action = storeUrl;
        document.getElementById('modeMethod').value = 'POST';
        document.getElementById('modeTitle').textContent = 'Add Payment Mode';
        document.getElementById('modeName').value = '';
        document.getElementById('modeRef').checked = false;
        document.getElementById('modeActiveWrap').style.display = 'none';
    }
    function editMode(m) {
        modeForm.action = "{{ url('admin/payment-modes') }}/" + m.id;
        document.getElementById('modeMethod').value = 'PUT';
        document.getElementById('modeTitle').textContent = 'Edit Payment Mode';
        document.getElementById('modeName').value = m.name;
        document.getElementById('modeRef').checked = !!m.requires_reference;
        document.getElementById('modeActiveWrap').style.display = '';
        document.getElementById('modeActive').checked = !!m.is_active;
    }
</script>
@endpush
