{{-- Shared student form. $student is passed in by create/edit (null on create). --}}
@php $student = $student ?? null; @endphp

<div class="row g-3 align-items-start">
    {{-- Photo --}}
    <div class="col-md-3 text-center">
        <img src="{{ $student?->photo_url ?? 'https://ui-avatars.com/api/?name=New&background=2563eb&color=fff' }}"
             class="rounded-4 mb-2 border" style="width:130px;height:130px;object-fit:cover;" id="photoPreview" alt="">
        <label class="form-label d-block small text-muted">Photo</label>
        <input type="file" name="photo" accept="image/*" class="form-control form-control-sm"
               onchange="document.getElementById('photoPreview').src=window.URL.createObjectURL(this.files[0])">
    </div>

    {{-- Basic details --}}
    <div class="col-md-9">
        <div class="row g-3">
            <div class="col-12"><div class="text-uppercase fw-bold text-secondary" style="font-size:.72rem;letter-spacing:.06em">Details</div><hr class="mt-1 mb-0"></div>
            <div class="col-md-6">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $student?->name) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Mobile <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">+91</span>
                    <input type="tel" name="mobile" class="form-control" inputmode="numeric" maxlength="10"
                           value="{{ old('mobile', substr($student?->mobile ?? '', -10) ?: '') }}" required>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Occupation <span class="text-danger">*</span></label>
                <select name="occupation_type" class="form-select" required>
                    @foreach(config('hsms.occupation_types') as $k => $label)
                        <option value="{{ $k }}" @selected(old('occupation_type', $student?->occupation_type) === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Contacts --}}
    <div class="col-12 mt-2"><div class="text-uppercase fw-bold text-secondary" style="font-size:.72rem;letter-spacing:.06em">Contacts</div><hr class="mt-1 mb-0"></div>
    <div class="col-md-4">
        <label class="form-label">Father's Mobile</label>
        <div class="input-group"><span class="input-group-text">+91</span>
            <input type="tel" name="father_mobile" class="form-control" inputmode="numeric" maxlength="10" value="{{ old('father_mobile', substr($student?->father_mobile ?? '', -10) ?: '') }}"></div>
    </div>
    <div class="col-md-4">
        <label class="form-label">Mother's Mobile</label>
        <div class="input-group"><span class="input-group-text">+91</span>
            <input type="tel" name="mother_mobile" class="form-control" inputmode="numeric" maxlength="10" value="{{ old('mother_mobile', substr($student?->mother_mobile ?? '', -10) ?: '') }}"></div>
    </div>
    <div class="col-md-4">
        <label class="form-label">Guardian's Mobile</label>
        <div class="input-group"><span class="input-group-text">+91</span>
            <input type="tel" name="guardian_mobile" class="form-control" inputmode="numeric" maxlength="10" value="{{ old('guardian_mobile', substr($student?->guardian_mobile ?? '', -10) ?: '') }}"></div>
    </div>

    {{-- Identity & address --}}
    <div class="col-12 mt-2"><div class="text-uppercase fw-bold text-secondary" style="font-size:.72rem;letter-spacing:.06em">Identity &amp; Address</div><hr class="mt-1 mb-0"></div>
    <div class="col-md-4">
        <label class="form-label">Aadhaar Number</label>
        <input type="text" name="aadhaar" class="form-control" inputmode="numeric" maxlength="12" pattern="\d{12}"
               value="{{ old('aadhaar', $student?->aadhaar) }}" placeholder="12 digits">
    </div>
    <div class="col-md-4">
        <label class="form-label">City</label>
        <input type="text" name="city" class="form-control" value="{{ old('city', $student?->city) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">State</label>
        <input type="text" name="state" class="form-control" value="{{ old('state', $student?->state) }}">
    </div>
    <div class="col-12">
        <label class="form-label">Address</label>
        <textarea name="address" class="form-control" rows="2">{{ old('address', $student?->address) }}</textarea>
    </div>

    {{-- Stay --}}
    <div class="col-12 mt-2"><div class="text-uppercase fw-bold text-secondary" style="font-size:.72rem;letter-spacing:.06em">Stay</div><hr class="mt-1 mb-0"></div>
    <div class="col-md-4">
        <label class="form-label">Join Date</label>
        <input type="date" name="join_date" class="form-control" value="{{ old('join_date', optional($student?->join_date)->format('Y-m-d')) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Leave Date</label>
        <input type="date" name="leave_date" class="form-control" value="{{ old('leave_date', optional($student?->leave_date)->format('Y-m-d')) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Status <span class="text-danger">*</span></label>
        <select name="status" class="form-select" required>
            <option value="active" @selected(old('status', $student?->status ?? 'active') === 'active')>Active</option>
            <option value="left" @selected(old('status', $student?->status) === 'left')>Left</option>
        </select>
    </div>
</div>
