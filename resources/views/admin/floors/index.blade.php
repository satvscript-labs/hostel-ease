@extends('layouts.app')
@section('title', 'Floors')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Floors</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#floorModal" onclick="resetFloorForm()">
        <i class="fa-solid fa-plus me-1"></i> Add Floor
    </button>
</div>

<div class="card stat-card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>#</th><th>Floor Name</th><th>Rooms</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                @forelse($floors as $floor)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="fw-semibold">{{ $floor->name }}</td>
                        <td><span class="badge bg-primary-subtle text-primary">{{ $floor->rooms_count }}</span></td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-light"
                                    data-bs-toggle="modal" data-bs-target="#floorModal"
                                    onclick='editFloor(@json($floor->only(["id","name","sort_order"])))'>
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <form action="{{ route('admin.floors.destroy', $floor) }}" method="POST" class="d-inline"
                                  data-confirm="Delete floor &quot;{{ $floor->name }}&quot;?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">
                        No floors yet. Add your first floor to begin building rooms.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Create / Edit modal --}}
<div class="modal fade" id="floorModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="floorForm" method="POST" action="{{ route('admin.floors.store') }}">
            @csrf
            <input type="hidden" name="_method" id="floorMethod" value="POST">
            <div class="modal-header">
                <h5 class="modal-title" id="floorModalTitle">Add Floor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Floor Name</label>
                    <input type="text" name="name" id="floorName" class="form-control" placeholder="e.g. Ground Floor" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" id="floorSortOrder" class="form-control" placeholder="0" min="0">
                    <div class="form-text">Lower numbers appear first in the list.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Floor</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const floorForm = document.getElementById('floorForm');
    const storeUrl = "{{ route('admin.floors.store') }}";
    function resetFloorForm() {
        floorForm.action = storeUrl;
        document.getElementById('floorMethod').value = 'POST';
        document.getElementById('floorModalTitle').textContent = 'Add Floor';
        document.getElementById('floorName').value = '';
        document.getElementById('floorSortOrder').value = '';
    }
    function editFloor(f) {
        floorForm.action = "{{ url('admin/floors') }}/" + f.id;
        document.getElementById('floorMethod').value = 'PUT';
        document.getElementById('floorModalTitle').textContent = 'Edit Floor';
        document.getElementById('floorName').value = f.name;
        document.getElementById('floorSortOrder').value = f.sort_order;
    }
</script>
@endpush
