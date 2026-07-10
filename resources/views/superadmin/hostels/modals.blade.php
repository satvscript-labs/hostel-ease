<template x-teleport="body">
    <div>
        <!-- Create Hostel Modal -->
        <div class="custom-overlay-backdrop" x-show="createModalOpen" x-transition.opacity @click.self="createModalOpen = false" x-cloak style="display: none;">
            <form method="POST" action="{{ route('superadmin.hostels.store') }}" class="custom-overlay-modal" style="max-width: 800px;" :class="{ 'is-open': createModalOpen }" x-show="createModalOpen" x-transition.opacity style="display: none;">
                @csrf
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0 text-dark">Add New Hostel / Branch</h5>
                    <button type="button" class="btn-close" @click="createModalOpen = false"></button>
                </div>
                <div class="custom-overlay-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">HOSTEL NAME <span class="text-danger">*</span></label>
                            <input type="text" name="name" value="{{ old('name') }}" class="form-control bg-white border shadow-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">OWNER NAME <span class="text-danger">*</span></label>
                            <input type="text" name="owner_name" value="{{ old('owner_name') }}" class="form-control bg-white border shadow-sm" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">MOBILE <span class="text-danger">*</span></label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white border-end-0 fw-bold text-muted">+91</span>
                                <input type="tel" name="mobile" value="{{ old('mobile') }}" class="form-control bg-white border-start-0" maxlength="10" required>
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
                        
                        <div class="col-12 mt-2" x-data="{ showAdvanced: false }">
                            <button type="button" @click="showAdvanced = !showAdvanced" class="btn btn-link text-muted fw-bold text-decoration-none p-0 d-flex align-items-center">
                                <i class="fa-solid fa-location-dot me-2"></i> Address Details
                            </button>
                            <div class="mt-3 row g-3" x-show="showAdvanced" x-transition style="display: none;">
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

                        <div class="col-12"><hr class="my-2 text-muted"></div>
                        <div class="col-12 mb-0"><h6 class="fw-bold text-dark mb-0">Initial Subscription Setup</h6></div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">PLAN PERIOD <span class="text-danger">*</span></label>
                            <div x-data="{ dropOpen: false }" class="position-relative">
                                <button type="button" @click="dropOpen = !dropOpen" class="form-control bg-white border shadow-sm d-flex justify-content-between align-items-center">
                                    <span x-text="c_plan === 'yearly' ? 'Yearly' : (c_plan === 'monthly' ? 'Monthly' : 'Trial (14 Days)')" class="text-dark fw-bold"></span>
                                    <i class="fa-solid fa-chevron-down text-muted small"></i>
                                </button>
                                <div x-show="dropOpen" @click.outside.capture="dropOpen = false" x-transition class="position-absolute w-100 bg-white shadow-lg rounded-3 mb-1 overflow-hidden border" style="display: none; z-index: 1050; bottom: 100%; top: auto;">
                                    <div @click="c_plan = 'yearly'; recalcCreate(); dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer">
                                        <div class="fw-bold" :class="c_plan === 'yearly' ? 'text-primary' : 'text-dark'">Yearly</div>
                                    </div>
                                    <div @click="c_plan = 'monthly'; recalcCreate(); dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer">
                                        <div class="fw-bold" :class="c_plan === 'monthly' ? 'text-primary' : 'text-dark'">Monthly</div>
                                    </div>
                                    <div @click="c_plan = 'trial'; recalcCreate(); dropOpen = false" class="px-3 py-2 hover-bg-light cursor-pointer">
                                        <div class="fw-bold" :class="c_plan === 'trial' ? 'text-primary' : 'text-dark'">Trial (14 Days)</div>
                                    </div>
                                </div>
                                <input type="hidden" name="plan" :value="c_plan" required>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-end mb-2">
                                <label class="form-label fw-bold small text-muted mb-0">AMOUNT (₹)</label>
                                <button type="button" @click="recalcCreate()" class="btn btn-link p-0 text-decoration-none small fw-bold" style="font-size: 0.75rem;"><i class="fa-solid fa-rotate-right"></i> Auto-calc</button>
                            </div>
                            <input type="number" step="0.01" name="amount" x-model="c_amount" class="form-control fw-bold text-dark fs-5 bg-white border shadow-sm">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">PAYMENT STATUS</label>
                            <div x-data="{ dropOpen: false }" class="position-relative">
                                <button type="button" @click="dropOpen = !dropOpen" class="form-control bg-white border shadow-sm d-flex justify-content-between align-items-center">
                                    <span x-text="c_status === 'paid' ? 'Paid' : (c_status === 'pending' ? 'Pending' : 'Failed')" class="fw-bold" :class="c_status === 'paid' ? 'text-success' : (c_status === 'pending' ? 'text-warning' : 'text-danger')"></span>
                                    <i class="fa-solid fa-chevron-down text-muted small"></i>
                                </button>
                                <div x-show="dropOpen" @click.outside.capture="dropOpen = false" x-transition class="position-absolute w-100 bg-white shadow-lg rounded-3 mb-1 overflow-hidden border" style="display: none; z-index: 1050; bottom: 100%; top: auto;">
                                    <div @click="c_status = 'paid'; dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer text-success fw-bold">Paid</div>
                                    <div @click="c_status = 'pending'; dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer text-warning fw-bold">Pending</div>
                                    <div @click="c_status = 'failed'; dropOpen = false" class="px-3 py-2 hover-bg-light cursor-pointer text-danger fw-bold">Failed</div>
                                </div>
                                <input type="hidden" name="payment_status" :value="c_status" required>
                            </div>
                        </div>

                        <!-- Add Hostel Status (Default Active) -->
                        <input type="hidden" name="status" value="active">
                    </div>
                </div>
                <div class="custom-overlay-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="createModalOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Provision Hostel</button>
                </div>
            </form>
        </div>

        <!-- Edit Hostel Modal -->
        <div class="custom-overlay-backdrop" x-show="editModalOpen" x-transition.opacity @click.self="editModalOpen = false" x-cloak style="display: none;">
            <form method="POST" :action="editUrl" class="custom-overlay-modal" style="max-width: 800px;" :class="{ 'is-open': editModalOpen }" x-show="editModalOpen" x-transition.opacity style="display: none;">
                @csrf @method('PUT')
                <input type="hidden" name="is_edit" value="1">
                <input type="hidden" name="hostel_id" x-model="e_id">
                
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0 text-dark">Edit Hostel</h5>
                    <button type="button" class="btn-close" @click="editModalOpen = false"></button>
                </div>
                <div class="custom-overlay-body">
                    <div class="row g-4">
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
                            <input type="tel" name="mobile" x-model="e_mobile" class="form-control bg-white border shadow-sm" maxlength="10" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">EMAIL</label>
                            <input type="email" name="email" x-model="e_email" class="form-control bg-white border shadow-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">GST NUMBER</label>
                            <input type="text" name="gst_number" x-model="e_gst_number" class="form-control bg-white border shadow-sm">
                        </div>
                        
                        <div class="col-12 mt-2" x-data="{ showAdvanced: false }">
                            <button type="button" @click="showAdvanced = !showAdvanced" class="btn btn-link text-muted fw-bold text-decoration-none p-0 d-flex align-items-center">
                                <i class="fa-solid fa-location-dot me-2"></i> Address Details
                            </button>
                            <div class="mt-3 row g-3" x-show="showAdvanced" x-transition style="display: none;">
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

                        <div class="col-12"><hr class="my-2 text-muted"></div>
                        <div class="col-12 mb-0"><h6 class="fw-bold text-dark mb-0">Status & Subscription Validity</h6></div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">HOSTEL STATUS</label>
                            <div x-data="{ dropOpen: false }" class="position-relative">
                                <button type="button" @click="dropOpen = !dropOpen" class="form-control bg-white border shadow-sm d-flex justify-content-between align-items-center">
                                    <span x-text="e_status === 'active' ? 'Active' : (e_status === 'expired' ? 'Expired' : 'Suspended')" class="fw-bold" :class="e_status === 'active' ? 'text-success' : (e_status === 'expired' ? 'text-danger' : 'text-warning')"></span>
                                    <i class="fa-solid fa-chevron-down text-muted small"></i>
                                </button>
                                <div x-show="dropOpen" @click.outside.capture="dropOpen = false" x-transition class="position-absolute w-100 bg-white shadow-lg rounded-3 mb-1 overflow-hidden border" style="display: none; z-index: 1050; bottom: 100%; top: auto;">
                                    <div @click="e_status = 'active'; dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer text-success fw-bold">Active</div>
                                    <div @click="e_status = 'expired'; dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer text-danger fw-bold">Expired</div>
                                    <div @click="e_status = 'suspended'; dropOpen = false" class="px-3 py-2 hover-bg-light cursor-pointer text-warning fw-bold">Suspended</div>
                                </div>
                                <input type="hidden" name="status" :value="e_status" required>
                            </div>
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
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="editModalOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Save Changes</button>
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
        
        hostels: <?php echo json_encode($hostelsJson); ?>,
        pricing: <?php echo json_encode($pricingJson); ?>,
        
        // Create form
        c_plan: {!! json_encode(old('plan', 'yearly')) !!},
        c_amount: {!! json_encode(old('amount', config('hostelease.subscription_pricing.yearly', 10000))) !!},
        c_status: {!! json_encode(old('payment_status', 'pending')) !!},
        
        recalcCreate() {
            this.c_amount = this.pricing[this.c_plan] || 0;
        },
        
        // Edit form
        e_id: {!! json_encode(old('is_edit') ? old('hostel_id', '') : '') !!},
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
        editUrl: {!! json_encode(old('is_edit') && old('hostel_id') ? url('superadmin/hostels/'.old('hostel_id')) : '') !!},
        
        openEditModal(id) {
            const h = this.hostels[id];
            if (!h) return;
            this.e_id = id;
            this.e_name = h.name;
            this.e_owner_name = h.owner_name;
            this.e_mobile = h.mobile;
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
