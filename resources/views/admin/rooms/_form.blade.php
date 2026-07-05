{{-- Shared room form fields. Expects $room (nullable) and $floors. --}}
@php($room = $room ?? null)
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Floor <span class="text-danger">*</span></label>
        <select name="floor_id" class="form-select" required>
            <option value="">Select floor…</option>
            @foreach($floors as $floor)
                <option value="{{ $floor->id }}" @selected(old('floor_id', $room?->floor_id) == $floor->id)>{{ $floor->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Room Number <span class="text-danger">*</span></label>
        <input type="text" name="room_number" class="form-control"
               value="{{ old('room_number', $room?->room_number) }}" placeholder="e.g. 101" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Room Type <span class="text-danger">*</span></label>
        <select name="room_type" class="form-select" required>
            @foreach(config('hsms.room_types') as $key => $label)
                <option value="{{ $key }}" @selected(old('room_type', $room?->room_type) === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Sharing <span class="text-danger">*</span></label>
        <select name="sharing" class="form-select" required>
            @foreach(config('hsms.sharing_options') as $n)
                <option value="{{ $n }}" @selected(old('sharing', $room?->sharing) == $n)>{{ $n }} Sharing</option>
            @endforeach
        </select>
        <small class="text-muted">Beds (B1…Bn) are created automatically.</small>
    </div>
</div>
<p class="text-muted small mt-3 mb-0">
    <i class="fa-solid fa-circle-info me-1"></i>
    Fees aren't set on the room. You'll choose each student's <strong>fee amount &amp; frequency</strong>
    (Monthly / Semester) when assigning them to a bed.
</p>
