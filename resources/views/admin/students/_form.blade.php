{{-- Shared student form. $student is passed in by create/edit (null on create). --}}
@php $student = $student ?? null; @endphp

<div class="row g-4">
    {{-- Photo Upload --}}
    <div class="col-12">
        <div class="d-flex align-items-center gap-3">
            <img src="{{ $student?->photo_url ?? 'https://ui-avatars.com/api/?name=New&background=2563eb&color=fff' }}"
                 class="rounded-3 border" style="width:72px;height:72px;object-fit:cover;" id="photoPreview" alt="">
            <div>
                <label class="btn btn-sm btn-outline-secondary mb-0">
                    <i class="fa-solid fa-camera me-1"></i> {{ $student ? 'Change Photo' : 'Upload Photo' }}
                    <input type="file" name="photo" accept="image/*" class="d-none"
                           onchange="document.getElementById('photoPreview').src=window.URL.createObjectURL(this.files[0])">
                </label>
                <p class="text-muted mb-0 mt-1" style="font-size: var(--he-text-xs);">JPG, PNG · max 2MB</p>
            </div>
        </div>
    </div>

    {{-- Personal Details --}}
    <div class="col-12">
        <div class="section-header"><i class="fa-solid fa-user"></i> Personal Details</div>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $student?->name) }}" required placeholder="Enter full name">
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label">Mobile <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">+91</span>
                    <input type="tel" name="mobile" class="form-control" inputmode="numeric" maxlength="10"
                           value="{{ old('mobile', substr($student?->mobile ?? '', -10) ?: '') }}" required placeholder="9876543210">
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label">Occupation <span class="text-danger">*</span></label>
                <select name="occupation_type" class="form-select" required>
                    @foreach(config('hostelease.occupation_types') as $k => $label)
                        <option value="{{ $k }}" @selected(old('occupation_type', $student?->occupation_type) === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Family & Emergency Contacts --}}
    <div class="col-12">
        <div class="section-header"><i class="fa-solid fa-address-book"></i> Family & Emergency Contacts</div>
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label">Father's Mobile</label>
                <div class="input-group">
                    <span class="input-group-text">+91</span>
                    <input type="tel" name="father_mobile" class="form-control" inputmode="numeric" maxlength="10"
                           value="{{ old('father_mobile', substr($student?->father_mobile ?? '', -10) ?: '') }}" placeholder="Optional">
                </div>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Mother's Mobile</label>
                <div class="input-group">
                    <span class="input-group-text">+91</span>
                    <input type="tel" name="mother_mobile" class="form-control" inputmode="numeric" maxlength="10"
                           value="{{ old('mother_mobile', substr($student?->mother_mobile ?? '', -10) ?: '') }}" placeholder="Optional">
                </div>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Guardian's Mobile</label>
                <div class="input-group">
                    <span class="input-group-text">+91</span>
                    <input type="tel" name="guardian_mobile" class="form-control" inputmode="numeric" maxlength="10"
                           value="{{ old('guardian_mobile', substr($student?->guardian_mobile ?? '', -10) ?: '') }}" placeholder="Optional">
                </div>
            </div>
        </div>
    </div>

    {{-- Identity & Address --}}
    <div class="col-12">
        <div class="section-header"><i class="fa-solid fa-id-card"></i> Identity & Address</div>
        <div class="row g-3">
            <div class="col-12 col-sm-6 col-md-4">
                <label class="form-label">Aadhaar Number</label>
                <input type="text" name="aadhaar" class="form-control" inputmode="numeric" maxlength="12" pattern="\d{12}"
                       value="{{ old('aadhaar', $student?->aadhaar) }}" placeholder="12-digit number">
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control" value="{{ old('city', $student?->city) }}" placeholder="City">
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label">State</label>
                <input type="text" name="state" class="form-control" value="{{ old('state', $student?->state) }}" placeholder="State">
            </div>
            <div class="col-12">
                <label class="form-label">Full Address</label>
                <textarea name="address" class="form-control" rows="2" placeholder="Street address, locality...">{{ old('address', $student?->address) }}</textarea>
            </div>
        </div>
    </div>

    {{-- Stay Information --}}
    <div class="col-12">
        <div class="section-header"><i class="fa-solid fa-calendar-days"></i> Stay Information</div>
        <div class="row g-3">
            <div class="col-6 col-md-4">
                <label class="form-label">Join Date</label>
                <input type="date" name="join_date" class="form-control" value="{{ old('join_date', optional($student?->join_date)->format('Y-m-d')) }}">
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label">Leave Date</label>
                <input type="date" name="leave_date" class="form-control" value="{{ old('leave_date', optional($student?->leave_date)->format('Y-m-d')) }}">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Status <span class="text-danger">*</span></label>
                <select name="status" class="form-select" required>
                    <option value="active" @selected(old('status', $student?->status ?? 'active') === 'active')>Active</option>
                    <option value="left" @selected(old('status', $student?->status) === 'left')>Left</option>
                </select>
            </div>
        </div>
    </div>
</div>
