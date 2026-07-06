{{-- Shared student form. $student is passed in by create/edit (null on create). --}}
@php $student = $student ?? null; @endphp

@push('styles')
<style>
    :root {
        --he-panel-bg: rgba(255, 255, 255, 0.85);
        --he-backdrop: blur(24px);
        --he-border: 1px solid rgba(255, 255, 255, 0.9);
        --he-shadow-premium: 0 10px 40px rgba(0, 0, 0, 0.04);
    }
    .form-layout {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    @media (min-width: 992px) {
        .form-layout {
            grid-template-columns: 320px 1fr;
            align-items: start;
        }
    }
    .premium-panel {
        background: var(--he-panel-bg);
        backdrop-filter: var(--he-backdrop);
        border: var(--he-border);
        border-radius: 1.5rem;
        box-shadow: var(--he-shadow-premium);
        overflow: hidden;
    }
    .form-control, .form-select {
        background-color: #f8fafc !important;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        transition: all 0.2s;
        font-weight: 500;
    }
    .form-control:focus, .form-select:focus {
        background-color: #fff !important;
        border-color: var(--he-primary);
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
    }
    .input-group-text {
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
    }
    .photo-upload-wrap {
        width: 140px;
        height: 140px;
        border-radius: 50%;
        border: 4px solid white;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        margin: 0 auto;
        position: relative;
        overflow: hidden;
        background: #f1f5f9;
        cursor: pointer;
    }
    .photo-upload-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    .upload-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        opacity: 0;
        transition: opacity 0.2s;
    }
    .photo-upload-wrap:hover .upload-overlay {
        opacity: 1;
    }
    .photo-upload-wrap:hover img {
        transform: scale(1.05);
    }
</style>
@endpush

<div class="form-layout">
    <!-- LEFT COLUMN: Photo & Actions -->
    <div class="d-flex flex-column gap-4">
        
        <div class="premium-panel p-4 text-center">
            <h3 class="h6 fw-bold mb-4 text-uppercase text-muted">Profile Photo</h3>
            <label class="photo-upload-wrap mb-3 d-block mx-auto">
                <img src="{{ $student?->photo_url ?? 'https://ui-avatars.com/api/?name=New&background=2563eb&color=fff' }}" id="photoPreview" alt="Photo">
                <div class="upload-overlay">
                    <div class="text-center">
                        <i class="fa-solid fa-camera fs-4 mb-1"></i>
                        <div class="small fw-bold">Upload</div>
                    </div>
                </div>
                <input type="file" name="photo" accept="image/*" class="d-none" onchange="document.getElementById('photoPreview').src=window.URL.createObjectURL(this.files[0])">
            </label>
            <p class="text-muted small fw-bold mb-0">JPG, PNG · max 2MB</p>
        </div>

        <div class="premium-panel p-4">
            <h3 class="h6 fw-bold mb-3 text-uppercase text-muted">Actions</h3>
            <button type="submit" class="btn btn-premium w-100 rounded-pill fw-bold shadow-sm mb-3 py-2">
                <i class="fa-solid fa-floppy-disk me-2"></i> {{ $student ? 'Update Student' : 'Save Student' }}
            </button>
            <a href="{{ $student ? route('admin.students.show', $student) : route('admin.students.index') }}" class="btn btn-light w-100 rounded-pill fw-bold py-2 border">
                Cancel
            </a>
            
            @unless($student)
                <div class="alert alert-info mt-4 mb-0 py-2 small fw-bold rounded-4 border-info-subtle">
                    <i class="fa-solid fa-info-circle me-1"></i>
                    Upload docs & assign a bed from the profile after saving.
                </div>
            @endunless
        </div>
    </div>

    <!-- RIGHT COLUMN: Form Data -->
    <div class="d-flex flex-column gap-4">
        
        <!-- Personal Details -->
        <div class="premium-panel p-4 p-md-5">
            <h4 class="h5 fw-bold mb-4 d-flex align-items-center"><i class="fa-solid fa-user text-primary me-2"></i> Personal Details</h4>
            <div class="row g-4">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-bold small">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $student?->name) }}" required placeholder="Enter full name">
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label fw-bold small">Mobile <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted fw-bold">+91</span>
                        <input type="tel" name="mobile" class="form-control border-start-0 ps-0" inputmode="numeric" maxlength="10"
                               value="{{ old('mobile', substr($student?->mobile ?? '', -10) ?: '') }}" required placeholder="9876543210">
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label fw-bold small">Occupation <span class="text-danger">*</span></label>
                    <select name="occupation_type" class="form-select" required>
                        @foreach(config('hostelease.occupation_types') as $k => $label)
                            <option value="{{ $k }}" @selected(old('occupation_type', $student?->occupation_type) === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Family Contacts -->
        <div class="premium-panel p-4 p-md-5">
            <h4 class="h5 fw-bold mb-4 d-flex align-items-center"><i class="fa-solid fa-users text-primary me-2"></i> Family Contacts</h4>
            <div class="row g-4">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold small">Father's Mobile</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted fw-bold">+91</span>
                        <input type="tel" name="father_mobile" class="form-control border-start-0 ps-0" inputmode="numeric" maxlength="10"
                               value="{{ old('father_mobile', substr($student?->father_mobile ?? '', -10) ?: '') }}" placeholder="Optional">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold small">Mother's Mobile</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted fw-bold">+91</span>
                        <input type="tel" name="mother_mobile" class="form-control border-start-0 ps-0" inputmode="numeric" maxlength="10"
                               value="{{ old('mother_mobile', substr($student?->mother_mobile ?? '', -10) ?: '') }}" placeholder="Optional">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold small">Guardian's Mobile</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted fw-bold">+91</span>
                        <input type="tel" name="guardian_mobile" class="form-control border-start-0 ps-0" inputmode="numeric" maxlength="10"
                               value="{{ old('guardian_mobile', substr($student?->guardian_mobile ?? '', -10) ?: '') }}" placeholder="Optional">
                    </div>
                </div>
            </div>
        </div>

        <!-- Identity & Address -->
        <div class="premium-panel p-4 p-md-5">
            <h4 class="h5 fw-bold mb-4 d-flex align-items-center"><i class="fa-solid fa-id-card text-primary me-2"></i> Identity & Address</h4>
            <div class="row g-4">
                <div class="col-12 col-sm-6 col-md-4">
                    <label class="form-label fw-bold small">Aadhaar Number</label>
                    <input type="text" name="aadhaar" class="form-control" inputmode="numeric" maxlength="12" pattern="\d{12}"
                           value="{{ old('aadhaar', $student?->aadhaar) }}" placeholder="12-digit number">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label fw-bold small">City</label>
                    <input type="text" name="city" class="form-control" value="{{ old('city', $student?->city) }}" placeholder="City name">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label fw-bold small">State</label>
                    <input type="text" name="state" class="form-control" value="{{ old('state', $student?->state) }}" placeholder="State name">
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold small">Full Address</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Street address, locality...">{{ old('address', $student?->address) }}</textarea>
                </div>
            </div>
        </div>

        <!-- Stay Information -->
        <div class="premium-panel p-4 p-md-5">
            <h4 class="h5 fw-bold mb-4 d-flex align-items-center"><i class="fa-solid fa-calendar-days text-primary me-2"></i> Stay Information</h4>
            <div class="row g-4">
                <div class="col-6 col-md-4">
                    <label class="form-label fw-bold small">Join Date</label>
                    <input type="date" name="join_date" class="form-control" value="{{ old('join_date', optional($student?->join_date)->format('Y-m-d')) }}">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label fw-bold small">Leave Date</label>
                    <input type="date" name="leave_date" class="form-control" value="{{ old('leave_date', optional($student?->leave_date)->format('Y-m-d')) }}">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold small">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <option value="active" @selected(old('status', $student?->status ?? 'active') === 'active')>Active</option>
                        <option value="left" @selected(old('status', $student?->status) === 'left')>Left</option>
                    </select>
                </div>
            </div>
        </div>

    </div>
</div>
