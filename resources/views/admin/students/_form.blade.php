{{-- Shared student form. $student is passed in by create/edit (null on create). --}}
@php $student = $student ?? null; @endphp

@push('styles')
<style>
    /* No per-view :root token redeclaration (design law) — use the canonical
       tokens from _premium.scss directly. */
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
        background: var(--he-bg-surface);
        border: 1px solid rgba(0, 0, 0, 0.05);
        border-radius: var(--he-radius-lg);
        box-shadow: var(--he-shadow-sm);
    }
    .form-page-title { font-size: 1.6rem; letter-spacing: -0.01em; }

    /* Mobile sticky Save bar — teleported to <body> (fixed can't live inside
       .page-enter's transformed root; see mobile audit rule 10). Reconnects
       to the form via form="studentForm". */
    .form-mobile-bar {
        position: fixed;
        left: 0; right: 0; bottom: 0;
        z-index: 1030;
        display: flex; gap: 0.75rem;
        padding: 0.8rem 1rem calc(0.8rem + env(safe-area-inset-bottom));
        background: var(--he-bg-surface);
        border-top: 1px solid rgba(0, 0, 0, 0.08);
        box-shadow: 0 -6px 20px rgba(0, 0, 0, 0.06);
    }
    @media (min-width: 992px) { .form-mobile-bar { display: none; } }
    /* Room for the sticky bar so it never covers the last field (matches the
       bar's <992px visibility). */
    @media (max-width: 991.98px) { .form-layout { padding-bottom: 4.5rem; } }
    .form-control, .form-select {
        background-color: var(--he-bg-surface-raised) !important;
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: var(--he-radius-md);
        padding: 0.75rem 1rem;
        transition: all 0.2s var(--ease-out-expo);
        font-weight: 500;
    }
    .form-control:focus, .form-select:focus {
        background-color: var(--he-bg-surface) !important;
        border-color: var(--he-primary);
        box-shadow: 0 0 0 4px var(--he-primary-soft);
    }
    .input-group-text {
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: var(--he-radius-md);
    }
    .photo-upload-wrap {
        width: 140px;
        height: 140px;
        border-radius: 50%;
        border: 4px solid var(--he-bg-surface);
        box-shadow: var(--he-shadow-md);
        margin: 0 auto;
        position: relative;
        overflow: hidden;
        background: var(--he-bg-surface-raised);
        cursor: pointer;
    }
    .photo-upload-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s var(--ease-out-expo);
    }
    .upload-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        opacity: 0;
        transition: opacity 0.2s var(--ease-out-expo);
    }
    .photo-upload-wrap:hover .upload-overlay {
        opacity: 1;
    }
    .photo-upload-wrap:hover img {
        transform: scale(1.05);
    }

    @media (max-width: 576px) {
        .form-control, .form-select { font-size: 16px; } /* ≥16px stops iOS zoom */
        .form-page-title { font-size: 2.2rem; line-height: 1.5; } /* mobile heading standard */
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

        <div class="premium-panel p-4 text-center mt-0">
            <h3 class="h6 fw-bold mb-4 text-uppercase text-muted">Aadhaar Card <span class="text-danger">*</span></h3>
            <label class="photo-upload-wrap mb-3 d-block mx-auto" style="border-radius: 10px;">
                <img src="{{ $student?->aadhaar_file ? route('admin.files.show', ['student', $student->id, 'aadhaar_file']) : 'https://ui-avatars.com/api/?name=Doc&background=f1f5f9&color=94a3b8' }}" id="aadhaarPreview" alt="Aadhaar">
                <div class="upload-overlay" style="border-radius: 6px;">
                    <div class="text-center">
                        <i class="fa-solid fa-file-arrow-up fs-4 mb-1"></i>
                        <div class="small fw-bold">Upload</div>
                    </div>
                </div>
                <input type="file" name="aadhaar_file" accept="image/*" class="d-none" onchange="document.getElementById('aadhaarPreview').src=window.URL.createObjectURL(this.files[0])" {{ $student?->aadhaar_file ? '' : 'required' }}>
            </label>
            <p class="text-muted small fw-bold mb-0">JPG, PNG · max 4MB</p>
        </div>

        {{-- Desktop actions (mobile uses the sticky bar below). --}}
        <div class="premium-panel p-4 d-none d-lg-block">
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
    <div class="d-flex flex-column gap-4" x-data="{ occupation: '{{ old('occupation_type', $student?->occupation_type ?? '') }}', occDropdown: false }">
        
        <!-- Personal Details -->
        <div class="premium-panel p-4 p-md-5">
            <h4 class="h5 fw-bold mb-4 d-flex align-items-center"><i class="fa-solid fa-user text-primary me-2"></i> Personal Details</h4>
            <div class="row g-4">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-bold small">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $student?->name) }}" required maxlength="150" placeholder="Enter full name">
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label fw-bold small">Mobile <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted fw-bold">+91</span>
                        <input type="tel" name="mobile" class="form-control border-start-0 ps-0" inputmode="numeric" maxlength="10" minlength="10" pattern="\d{10}" title="10-digit mobile number"
                               value="{{ old('mobile', substr($student?->mobile ?? '', -10) ?: '') }}" required placeholder="9876543210">
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label fw-bold small">Occupation <span class="text-danger">*</span></label>
                    <input type="hidden" name="occupation_type" :value="occupation">
                    
                    <div class="position-relative">
                        <div class="d-flex align-items-center justify-content-between form-control bg-light" @click="occDropdown = !occDropdown" style="cursor: pointer;">
                            <span class="fw-semibold text-dark" x-text="occupation ? document.querySelector(`[data-occ='${occupation}']`).innerText : 'Select...'"></span>
                            <i class="fa-solid fa-chevron-down text-muted small transition-all" :class="{'fa-chevron-up': occDropdown}"></i>
                        </div>
                        
                        <div x-show="occDropdown" @click.outside.capture="occDropdown = false" x-transition.opacity.duration.200ms class="position-absolute bg-white border rounded-4 shadow-lg mt-2 w-100" style="display: none; z-index: 1050;">
                            <div class="list-group list-group-flush rounded-4 py-2">
                                @foreach(config('hostelease.occupation_types') as $k => $label)
                                <a href="javascript:void(0)" class="list-group-item list-group-item-action border-0 py-2 px-3 fw-medium" data-occ="{{ $k }}" :class="occupation === '{{ $k }}' ? 'active bg-primary text-white fw-bold' : 'text-dark'" @click="occupation = '{{ $k }}'; occDropdown = false;">
                                    {{ $label }}
                                </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 col-md-6" x-show="occupation === 'student'" x-transition x-cloak>
                    <label class="form-label fw-bold small">College / University <span class="text-danger">*</span></label>
                    <input type="text" name="college" class="form-control" maxlength="255" value="{{ old('college', $student?->college) }}" placeholder="e.g. ABC College" :required="occupation === 'student'">
                </div>
                
                <div class="col-12 col-md-6" x-show="occupation === 'student'" x-transition x-cloak>
                    <label class="form-label fw-bold small">Field of Study <span class="text-danger">*</span></label>
                    <input type="text" name="field_of_study" class="form-control" maxlength="255" value="{{ old('field_of_study', $student?->field_of_study) }}" placeholder="e.g. B.Tech Computer Science" :required="occupation === 'student'">
                </div>
            </div>
        </div>

        <!-- Family Contacts -->
        <div class="premium-panel p-4 p-md-5">
            <h4 class="h5 fw-bold mb-4 d-flex align-items-center"><i class="fa-solid fa-users text-primary me-2"></i> Family Contacts</h4>
            <div class="row g-4">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold small">Father's Mobile <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted fw-bold">+91</span>
                        <input type="tel" name="father_mobile" class="form-control border-start-0 ps-0" inputmode="numeric" maxlength="10" minlength="10" pattern="\d{10}" title="10-digit mobile number" required
                               value="{{ old('father_mobile', substr($student?->father_mobile ?? '', -10) ?: '') }}" placeholder="10-digit mobile number">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold small">Mother's Mobile</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted fw-bold">+91</span>
                        <input type="tel" name="mother_mobile" class="form-control border-start-0 ps-0" inputmode="numeric" maxlength="10" minlength="10" pattern="\d{10}" title="10-digit mobile number"
                               value="{{ old('mother_mobile', substr($student?->mother_mobile ?? '', -10) ?: '') }}" placeholder="Optional">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold small">Guardian's Mobile</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted fw-bold">+91</span>
                        <input type="tel" name="guardian_mobile" class="form-control border-start-0 ps-0" inputmode="numeric" maxlength="10" minlength="10" pattern="\d{10}" title="10-digit mobile number"
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
                    <label class="form-label fw-bold small">Aadhaar Number <span class="text-danger">*</span></label>
                    <input type="text" name="aadhaar" class="form-control" inputmode="numeric" maxlength="12" pattern="\d{12}" required
                           value="{{ old('aadhaar', $student?->aadhaar) }}" placeholder="12-digit number">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label fw-bold small">City <span class="text-danger">*</span></label>
                    <input type="text" name="city" class="form-control" required maxlength="100" value="{{ old('city', $student?->city) }}" placeholder="City name">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label fw-bold small">State <span class="text-danger">*</span></label>
                    <input type="text" name="state" class="form-control" required maxlength="100" value="{{ old('state', $student?->state) }}" placeholder="State name">
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold small">Full Address <span class="text-danger">*</span></label>
                    <textarea name="address" class="form-control" rows="2" required maxlength="500" placeholder="Street address, locality...">{{ old('address', $student?->address) }}</textarea>
                </div>
            </div>
        </div>

        <!-- Stay Information -->
        <div class="premium-panel p-4 p-md-5">
            <h4 class="h5 fw-bold mb-4 d-flex align-items-center"><i class="fa-solid fa-calendar-days text-primary me-2"></i> Stay Information</h4>
            <div class="row g-4">
                <div class="col-6 col-md-4">
                    <label class="form-label fw-bold small">Join Date <span class="text-danger">*</span></label>
                    <input type="date" name="join_date" class="form-control" required value="{{ old('join_date', optional($student?->join_date)->format('Y-m-d')) }}">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label fw-bold small">Leave Date</label>
                    <input type="date" name="leave_date" class="form-control" value="{{ old('leave_date', optional($student?->leave_date)->format('Y-m-d')) }}">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold small">Status <span class="text-danger">*</span></label>
                    <x-he-select name="status" icon="toggle-on" :submit="false"
                        :selected="old('status', $student?->status ?? 'active')"
                        :options="['active' => 'Active', 'left' => 'Left']" />
                </div>
            </div>
        </div>

    </div>
</div>

{{-- Mobile sticky Save bar (teleported so position:fixed anchors to the viewport). --}}
<template x-teleport="body">
    <div class="form-mobile-bar" x-data>
        <a href="{{ $student ? route('admin.students.show', $student) : route('admin.students.index') }}" class="btn btn-light border rounded-pill fw-bold px-4 py-2">Cancel</a>
        <button type="submit" form="studentForm" class="btn btn-premium rounded-pill fw-bold py-2 flex-grow-1">
            <i class="fa-solid fa-floppy-disk me-2"></i>{{ $student ? 'Update Student' : 'Save Student' }}
        </button>
    </div>
</template>
