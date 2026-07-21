<template x-teleport="body">
    <div>
        {{-- ══ Create Hostel / Branch ══ --}}
        <div class="custom-overlay-backdrop" x-show="createModalOpen" x-transition.opacity @click.self="createModalOpen = false" x-cloak style="display: none;">
            <form method="POST" action="{{ route('superadmin.hostels.store') }}" data-ring-required class="custom-overlay-modal" style="max-width: 800px;" :class="{ 'is-open': createModalOpen }" @click.stop>
                @csrf
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-building-circle-arrow-right text-primary me-2"></i>Add New Hostel / Branch</h5>
                    <button type="button" class="btn-close" @click="createModalOpen = false"></button>
                </div>
                <div class="custom-overlay-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">HOSTEL NAME <span class="text-danger">*</span></label>
                            <input type="text" name="name" value="{{ old('name') }}" class="form-control bg-white border shadow-sm" required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">OWNER NAME <span class="text-danger">*</span></label>
                            <input type="text" name="owner_name" value="{{ old('owner_name') }}" class="form-control bg-white border shadow-sm" required autocomplete="off">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">MOBILE <span class="text-danger">*</span></label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white border-end-0 fw-bold text-muted">+91</span>
                                <input type="tel" name="mobile" value="{{ old('mobile') }}" class="form-control bg-white border-start-0" maxlength="10" inputmode="numeric" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">EMAIL</label>
                            <input type="email" name="email" value="{{ old('email') }}" class="form-control bg-white border shadow-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">GST NUMBER</label>
                            <input type="text" name="gst_number" value="{{ old('gst_number') }}" class="form-control bg-white border shadow-sm">
                        </div>

                        <div class="col-12" x-data="{ showAdvanced: false }">
                            <button type="button" @click="showAdvanced = !showAdvanced" class="btn btn-link text-muted fw-bold text-decoration-none p-0 small d-inline-flex align-items-center">
                                <i class="fa-solid fa-location-dot me-2"></i> Address Details
                                <i class="fa-solid fa-chevron-down ms-2" :class="{ 'fa-rotate-180': showAdvanced }" style="font-size:.7rem;"></i>
                            </button>
                            <div class="mt-3 row g-3" x-show="showAdvanced" x-collapse x-cloak>
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-muted">ADDRESS</label>
                                    <textarea name="address" class="form-control bg-white border shadow-sm" rows="2">{{ old('address') }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-muted">CITY</label>
                                    <input type="text" name="city" value="{{ old('city') }}" class="form-control bg-white border shadow-sm">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-muted">STATE</label>
                                    <input type="text" name="state" value="{{ old('state') }}" class="form-control bg-white border shadow-sm">
                                </div>
                            </div>
                        </div>

                        <div class="col-12"><hr class="my-1 text-muted"></div>
                        <div class="col-12"><h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-receipt text-primary me-2"></i>Initial Subscription Setup</h6></div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">PLAN PERIOD <span class="text-danger">*</span></label>
                            <x-he-select name="plan" :submit="false" compact x-model="c_plan" :options="[
                                'yearly' => 'Yearly',
                                'monthly' => 'Monthly',
                                'trial' => 'Trial (14 Days)',
                            ]" />
                        </div>

                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-end mb-2">
                                <label class="form-label fw-bold small text-muted mb-0">AMOUNT (₹)</label>
                                <button type="button" @click="recalcCreate()" class="btn btn-link p-0 text-decoration-none small fw-bold" style="font-size: 0.72rem;"><i class="fa-solid fa-rotate-right"></i> Auto-calc</button>
                            </div>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white fw-bold text-muted">₹</span>
                                <input type="number" step="0.01" name="amount" x-model="c_amount" class="form-control fw-bold text-dark border">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">PAYMENT STATUS</label>
                            <x-he-select name="payment_status" :submit="false" compact x-model="c_status" :options="[
                                'paid' => 'Paid',
                                'pending' => 'Pending',
                                'failed' => 'Failed',
                            ]" />
                        </div>

                        <input type="hidden" name="status" value="active">
                    </div>
                </div>
                <div class="custom-overlay-footer">
                    <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="createModalOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm"><i class="fa-solid fa-check me-2"></i>Provision Hostel</button>
                </div>
            </form>
        </div>

        {{-- ══ Edit Hostel ══ --}}
        <div class="custom-overlay-backdrop" x-show="editModalOpen" x-transition.opacity @click.self="editModalOpen = false" x-cloak style="display: none;">
            <form method="POST" :action="editUrl" data-ring-required class="custom-overlay-modal" style="max-width: 800px;" :class="{ 'is-open': editModalOpen }" @click.stop>
                @csrf @method('PUT')
                <input type="hidden" name="is_edit" value="1">
                {{-- Carries the hostel's OPAQUE public id (public-id hardening U4), purely so a
                     validation bounce can rebuild `editUrl` below. Deliberately NOT named
                     `hostel_id`: that name means a real integer DB id elsewhere (AdminController
                     validates `exists:hostels,id`), and this is not that. --}}
                <input type="hidden" name="hostel_public_id" x-model="e_id">

                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Hostel</h5>
                    <button type="button" class="btn-close" @click="editModalOpen = false"></button>
                </div>
                <div class="custom-overlay-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">HOSTEL NAME <span class="text-danger">*</span></label>
                            <input type="text" name="name" x-model="e_name" class="form-control bg-white border shadow-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">OWNER NAME <span class="text-danger">*</span></label>
                            <input type="text" name="owner_name" x-model="e_owner_name" class="form-control bg-white border shadow-sm" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">MOBILE <span class="text-danger">*</span></label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white border-end-0 fw-bold text-muted">+91</span>
                                <input type="tel" name="mobile" x-model="e_mobile" class="form-control bg-white border-start-0" maxlength="10" inputmode="numeric" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">EMAIL</label>
                            <input type="email" name="email" x-model="e_email" class="form-control bg-white border shadow-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">GST NUMBER</label>
                            <input type="text" name="gst_number" x-model="e_gst_number" class="form-control bg-white border shadow-sm">
                        </div>

                        <div class="col-12" x-data="{ showAdvanced: false }">
                            <button type="button" @click="showAdvanced = !showAdvanced" class="btn btn-link text-muted fw-bold text-decoration-none p-0 small d-inline-flex align-items-center">
                                <i class="fa-solid fa-location-dot me-2"></i> Address Details
                                <i class="fa-solid fa-chevron-down ms-2" :class="{ 'fa-rotate-180': showAdvanced }" style="font-size:.7rem;"></i>
                            </button>
                            <div class="mt-3 row g-3" x-show="showAdvanced" x-collapse x-cloak>
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-muted">ADDRESS</label>
                                    <textarea name="address" x-model="e_address" class="form-control bg-white border shadow-sm" rows="2"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-muted">CITY</label>
                                    <input type="text" name="city" x-model="e_city" class="form-control bg-white border shadow-sm">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-muted">STATE</label>
                                    <input type="text" name="state" x-model="e_state" class="form-control bg-white border shadow-sm">
                                </div>
                            </div>
                        </div>

                        <div class="col-12"><hr class="my-1 text-muted"></div>
                        <div class="col-12"><h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-calendar-check text-primary me-2"></i>Status &amp; Subscription Validity</h6></div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">HOSTEL STATUS</label>
                            <x-he-select name="status" :submit="false" compact x-model="e_status" :options="[
                                'active' => 'Active',
                                'expired' => 'Expired',
                                'suspended' => 'Suspended',
                            ]" />
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">VALID FROM</label>
                            <input type="date" name="subscription_start" x-model="e_start" class="form-control bg-white border shadow-sm" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">VALID UNTIL</label>
                            <input type="date" name="subscription_end" x-model="e_end" class="form-control bg-white border shadow-sm" required>
                        </div>
                    </div>
                </div>
                <div class="custom-overlay-footer">
                    <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="editModalOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm"><i class="fa-solid fa-check me-2"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('hostelsManager', () => ({
        createModalOpen: {{ $errors->any() && old('is_edit') != '1' ? 'true' : 'false' }},
        editModalOpen: {{ $errors->any() && old('is_edit') == '1' ? 'true' : 'false' }},

        // Fragment filter bar (W12) — search stays OUTSIDE the swap (§4.5).
        searchTerm: @json(request('q', '')),
        clearSearch() {
            this.searchTerm = '';
            this.$nextTick(() => this.$refs.filterForm?.requestSubmit());
        },

        hostels: <?php echo json_encode($hostelsJson); ?>,
        pricing: <?php echo json_encode($pricingJson); ?>,

        // Create form
        c_plan: {!! json_encode(old('plan', 'yearly')) !!},
        c_amount: {!! json_encode(old('amount', config('hostelease.subscription_pricing.yearly', 10000))) !!},
        c_status: {!! json_encode(old('payment_status', 'pending')) !!},

        init() {
            // Picking a plan re-prices the amount (mirrors the Auto-calc button).
            this.$watch('c_plan', () => this.recalcCreate());
        },

        recalcCreate() {
            this.c_amount = this.pricing[this.c_plan] ?? 0;
        },

        // Edit form
        e_id: {!! json_encode(old('is_edit') ? old('hostel_public_id', '') : '') !!},
        e_name: {!! json_encode(old('is_edit') ? old('name', '') : '') !!},
        e_owner_name: {!! json_encode(old('is_edit') ? old('owner_name', '') : '') !!},
        e_mobile: {!! json_encode(old('is_edit') ? old('mobile', '') : '') !!},
        e_email: {!! json_encode(old('is_edit') ? old('email', '') : '') !!},
        e_address: {!! json_encode(old('is_edit') ? old('address', '') : '') !!},
        e_city: {!! json_encode(old('is_edit') ? old('city', '') : '') !!},
        e_state: {!! json_encode(old('is_edit') ? old('state', '') : '') !!},
        e_gst_number: {!! json_encode(old('is_edit') ? old('gst_number', '') : '') !!},
        e_start: {!! json_encode(old('is_edit') ? old('subscription_start', '') : '') !!},
        e_end: {!! json_encode(old('is_edit') ? old('subscription_end', '') : '') !!},
        e_status: {!! json_encode(old('is_edit') ? old('status', '') : '') !!},
        editUrl: {!! json_encode(old('is_edit') && old('hostel_public_id') ? url('superadmin/hostels/'.old('hostel_public_id')) : '') !!},

        // W12: rows swapped in by the fragment router carry their OWN payload
        // (an object) — the page-load hostelsJson map only knows page 1, so an
        // id lookup would silently fail on any paged-in row.
        openEditModal(h) {
            if (typeof h !== 'object' || h === null) h = this.hostels[h];
            if (!h) return;
            const id = h.id ?? this.e_id;
            this.e_id = id;
            this.e_name = h.name;
            this.e_owner_name = h.owner_name;
            // Show just the 10 local digits; the +91 prefix is re-added on submit.
            this.e_mobile = (h.mobile || '').replace(/^\+91/, '').slice(-10);
            this.e_email = h.email;
            this.e_address = h.address;
            this.e_city = h.city;
            this.e_state = h.state;
            this.e_gst_number = h.gst_number;
            this.e_start = h.subscription_start;
            this.e_end = h.subscription_end;
            this.e_status = h.status;
            this.editUrl = `{{ url('superadmin/hostels') }}/${id}`;
            this.editModalOpen = true;
        }
    }));
});
</script>
