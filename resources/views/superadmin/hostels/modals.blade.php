<template x-teleport="body">
    <div>
        {{-- ══ Create Hostel / Branch ══ --}}
        <div class="custom-overlay-backdrop" x-show="createModalOpen" x-transition.opacity @click.self="createModalOpen = false" x-cloak style="display: none;">
            <form method="POST" action="{{ route('superadmin.hostels.store') }}" data-ring-required class="custom-overlay-modal" style="max-width: 800px;" :class="{ 'is-open': createModalOpen }" @click.stop>
                @csrf
                <div class="custom-overlay-header">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-building-circle-arrow-right text-primary me-2"></i>New customer</h5>
                        <div class="small text-muted">Their first hostel, login and plan. For an existing customer, add the hostel from their account.</div>
                    </div>
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
                                <input type="tel" name="mobile" value="{{ old('mobile') }}" class="form-control bg-white border-start-0" maxlength="10" inputmode="numeric" required
                                       @input.debounce.400ms="lookupOwner($event.target.value)">
                            </div>
                            @error('mobile')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
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

                        {{-- ALREADY A CUSTOMER: this form is for new customers only. Their
                             hostel is added from their account, where it joins their plan. --}}
                        <div class="col-12" x-show="owner.exists" x-transition.opacity x-cloak>
                            <div class="d-flex flex-wrap align-items-center gap-3 p-3 rounded-4" style="background: var(--he-warning-soft, #fef3c7); color:#92400e;">
                                <i class="fa-solid fa-user-check fs-5"></i>
                                <div class="flex-grow-1 small">
                                    <div class="fw-bold text-dark" x-text="owner.name + ' is already a customer'"></div>
                                    <span x-text="owner.branches + ' branch(es) on their plan.'"></span> Add this hostel from their account so it joins their plan — prorated to their renewal date, at their price.
                                </div>
                                <a :href="owner.account_url" x-show="owner.account_url" class="btn btn-sm btn-dark rounded-pill px-3 fw-semibold">Open their account <i class="fa-solid fa-arrow-right ms-1"></i></a>
                            </div>
                        </div>

                        <div class="col-12" x-show="!owner.exists"><hr class="my-1 text-muted"></div>
                        <div class="col-12" x-show="!owner.exists">
                            <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-receipt text-primary me-2"></i>Plan</h6>
                            <input type="hidden" name="plan" :value="c_plan">
                            <div class="d-flex gap-2 mb-3">
                                <button type="button" class="btn flex-fill rounded-pill fw-bold tactile-btn" :class="c_plan==='yearly'?'btn-primary':'btn-light border'" @click="c_plan='yearly'">Yearly</button>
                                <button type="button" class="btn flex-fill rounded-pill fw-bold tactile-btn" :class="c_plan==='monthly'?'btn-primary':'btn-light border'" @click="c_plan='monthly'">Monthly</button>
                                <button type="button" class="btn flex-fill rounded-pill fw-bold tactile-btn" :class="c_plan==='trial'?'btn-primary':'btn-light border'" @click="c_plan='trial'">Free trial (14 days)</button>
                            </div>

                            <x-he-billing-summary data="createSummary" />

                            <div class="row g-3 mt-1" x-show="c_plan !== 'trial'" x-cloak>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-muted">AMOUNT OVERRIDE (₹) <span class="fw-normal">— optional</span></label>
                                    <input type="number" step="0.01" min="0" name="amount" x-model="c_override" :disabled="c_plan === 'trial'"
                                           class="form-control bg-white border shadow-sm" :placeholder="'Auto (' + heMoney(createSummary.final) + ')'">
                                    <div class="form-text">Lower only — the difference is logged as a discount.</div>
                                </div>
                                <div class="col-md-6">
                                    @include('superadmin.accounts._collect_toggle', ['model' => 'c_collect'])
                                    <div x-show="c_collect === 'offline'" x-collapse>
                                        <label class="form-label fw-bold small text-muted">METHOD</label>
                                        <x-he-select name="payment_method" :submit="false" compact selected="cash" :options="['cash' => 'Cash', 'upi' => 'UPI', 'cheque' => 'Cheque', 'rtgs' => 'RTGS / NEFT', 'online' => 'Online']" />
                                        <input type="text" name="transaction_number" class="form-control bg-white border shadow-sm mt-2" placeholder="Reference (optional)" maxlength="100">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="status" value="active">
                    </div>
                </div>
                <div class="custom-overlay-footer">
                    <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="createModalOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm" :disabled="owner.exists">
                        <i class="fa-solid me-2" :class="c_plan !== 'trial' && c_collect === 'link' ? 'fa-link' : 'fa-check'"></i>
                        <span x-text="c_plan === 'trial' ? 'Start free trial' : (c_collect === 'link' ? 'Set up & create link' : 'Set up & record payment')"></span>
                    </button>
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

                        {{-- Coverage dates are not edited here (doc 22): they follow the
                             account's one renewal date. Gifts and corrections live on the
                             customer's account (Give free time · Add to cycle · Void). --}}
                        <div class="col-md-8 d-flex align-items-end">
                            <div class="small text-muted"><i class="fa-solid fa-calendar-check me-1"></i>Coverage <span x-text="e_end ? 'until ' + e_end : ''"></span> follows the customer's renewal date — change it from their account.</div>
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

        // ── New customer (Provision) ──
        // Prices come from the server's engine (newCustomerQuotes); the browser only
        // chooses the term and how it is collected, and may LOWER the total.
        quotes: @json($newCustomerQuotes),
        c_plan: @json(old('plan', 'yearly')),
        c_override: @json(old('amount', '')),
        c_collect: @json(old('collect', 'offline')),
        owner: { exists: false, name: '', branches: 0, account_url: null },

        get createSummary() {
            if (this.c_plan === 'trial') {
                return { rows: [{ label: '14-day free trial', amount: 0, kind: 'line' }], finalLabel: 'Payable now', final: 0, note: 'Branches they add during the trial join it; everything is billed when they subscribe.' };
            }
            const q = this.quotes[this.c_plan] || { unit: 0, volume: 0, auto: 0 };
            const rows = [{ label: '1 branch × ' + heMoney(q.unit) + (this.c_plan === 'monthly' ? '/mo' : '/yr'), amount: q.unit, kind: 'line' }];
            if (q.volume > 0) rows.push({ label: 'Volume tier', amount: q.volume, kind: 'discount' });
            let final = q.auto;
            const ov = parseFloat(this.c_override);
            if (this.c_override !== '' && !isNaN(ov) && ov < q.auto) {
                rows.push({ label: 'Manual adjustment', amount: Math.round((q.auto - ov) * 100) / 100, kind: 'discount' });
                final = ov;
            }
            return { rows, finalLabel: this.c_collect === 'link' ? 'Link amount' : 'Payable now', final,
                note: this.c_collect === 'link' ? 'The hostel goes live once the link is paid.' : 'Live from today for one ' + (this.c_plan === 'monthly' ? 'month' : 'year') + '.' };
        },

        async lookupOwner(value) {
            const digits = (value || '').replace(/\D/g, '');
            if (digits.length !== 10) { this.owner = { exists: false, name: '', branches: 0, account_url: null }; return; }
            try {
                const res = await fetch(@json(route('superadmin.hostels.owner-lookup')) + '?mobile=' + digits, { headers: { 'Accept': 'application/json' } });
                this.owner = await res.json();
            } catch (e) { /* the server refuses an existing owner anyway */ }
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
        e_end: '',
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
            this.e_end = h.subscription_end;
            this.e_status = h.status;
            this.editUrl = `{{ url('superadmin/hostels') }}/${id}`;
            this.editModalOpen = true;
        }
    }));
});
</script>
