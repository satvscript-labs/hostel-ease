{{-- Assign + Transfer sheets for the student profile (W10 UX fix). Same
     endpoints and validation as the Property Board move-flow; the student is
     fixed here, so you pick a BED instead of picking a student. redirect_to=
     profile keeps the operator on this page. --}}

{{-- ══ Assign ══ --}}
<template x-teleport="body">
    <div class="custom-overlay-backdrop" x-show="assignOpen" x-transition.opacity @click="close()" x-cloak style="display:none;">
        <form method="POST" :action="cfg.assignUrl" data-ring-required
              class="custom-overlay-modal" :class="{ 'is-open': assignOpen }" x-show="assignOpen" x-transition.opacity @click.stop style="display:none;">
            @csrf
            <input type="hidden" name="redirect_to" value="profile">
            <input type="hidden" name="student_id" :value="cfg.studentId">
            <input type="hidden" name="bed_id" :value="bed?.id">
            <input type="hidden" name="fee_frequency" :value="frequency">

            <div class="custom-overlay-header">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-bed" style="color: var(--he-primary);"></i><span class="ms-1">{{ __('Assign a Bed') }}</span></h5>
                <button type="button" class="btn-close" @click="close()"></button>
            </div>

            <div class="custom-overlay-body">
                @include('admin.students._accommodation_bed_picker')

                <div class="row gx-3">
                    <div class="col-md-6 mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Join Date') }} <span class="text-danger">*</span></label>
                        <input type="date" name="join_date" x-model="joinDate" class="form-control bg-light" max="{{ now()->toDateString() }}" required>
                    </div>
                    {{-- AC meter required only when the chosen bed's room is AC. --}}
                    <div class="col-md-6 mb-4" x-show="bed?.is_ac" x-cloak>
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1"><i class="fa-solid fa-bolt text-warning me-1"></i>{{ __('AC Meter Now') }} <span class="text-danger">*</span></label>
                        <input type="number" name="meter_reading" x-model.number="meter" class="form-control bg-light fw-bold" min="0" step="0.01" :required="bed?.is_ac" :disabled="!bed?.is_ac" placeholder="{{ __('Read the room meter') }}">
                    </div>
                </div>

                <hr class="opacity-10 my-3">
                <h6 class="fw-bold text-muted text-uppercase mb-1" style="font-size:.75rem; letter-spacing:1px;">{{ __('Fee Plan') }}</h6>
                <p class="text-muted small mb-3">{{ __('What will this student pay, and how often? Each room has its own cost.') }}</p>
                @include('admin.students._accommodation_fee')

                <div class="mv-note" x-show="cfg.fee <= 0" x-cloak style="background:var(--he-primary-soft); color:var(--he-primary-hover,#4338ca); border-radius:var(--he-radius-md); padding:.6rem .8rem; font-size:.82rem; font-weight:600;">
                    <i class="fa-solid fa-receipt me-1"></i>{{ __('Their first invoice is raised on this plan when you confirm.') }}
                </div>
            </div>

            <div class="custom-overlay-footer bg-light">
                <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="close()">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-premium fw-semibold rounded-pill px-4 shadow-sm tactile-btn" :class="{ 'is-locked': !bed }">
                    <i class="fa-solid fa-check me-2"></i>{{ __('Confirm Assignment') }}
                </button>
            </div>
        </form>
    </div>
</template>

{{-- ══ Transfer ══ --}}
<template x-teleport="body">
    <div class="custom-overlay-backdrop" x-show="transferOpen" x-transition.opacity @click="close()" x-cloak style="display:none;">
        <form method="POST" :action="cfg.transferUrl" data-ring-required
              class="custom-overlay-modal" :class="{ 'is-open': transferOpen }" x-show="transferOpen" x-transition.opacity @click.stop style="display:none;">
            @csrf @method('PATCH')
            <input type="hidden" name="redirect_to" value="profile">
            <input type="hidden" name="bed_id" :value="bed?.id">
            <input type="hidden" name="fee_frequency" :value="frequency">

            <div class="custom-overlay-header">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-right-left" style="color: var(--he-primary);"></i><span class="ms-1">{{ __('Transfer Bed') }}</span></h5>
                <button type="button" class="btn-close" @click="close()"></button>
            </div>

            <div class="custom-overlay-body">
                @include('admin.students._accommodation_bed_picker')

                <div class="row gx-3">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Move Date') }} <span class="text-danger">*</span></label>
                        <input type="date" name="join_date" x-model="joinDate" class="form-control bg-light" max="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-md-6 mb-3" x-show="bed?.is_ac" x-cloak>
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1"><i class="fa-solid fa-bolt text-warning me-1"></i>{{ __('New room meter') }} <span class="text-danger">*</span></label>
                        <input type="number" name="meter_reading" x-model.number="meter" class="form-control bg-light fw-bold" min="0" step="0.01" :required="bed?.is_ac" :disabled="!bed?.is_ac" placeholder="{{ __('Read the new room meter') }}">
                    </div>
                    {{-- The room being LEFT: its reading caps the AC share here. --}}
                    <div class="col-md-6 mb-3" x-show="cfg.currentRoomIsAc" x-cloak>
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1"><i class="fa-solid fa-bolt text-warning me-1"></i>{{ __('Old room meter') }} (<span x-text="cfg.currentRoom"></span>) <span class="text-danger">*</span></label>
                        <input type="number" name="old_meter_reading" x-model.number="oldMeter" class="form-control bg-light fw-bold" min="0" step="0.01" :required="cfg.currentRoomIsAc" placeholder="{{ __('Read the old room meter') }}">
                    </div>
                </div>

                <hr class="opacity-10 my-3">
                <h6 class="fw-bold text-muted text-uppercase mb-1" style="font-size:.75rem; letter-spacing:1px;">{{ __('Fee Plan') }}</h6>
                <p class="text-muted small mb-3">{{ __('The new room has its own cost — confirm what they pay from this move.') }}</p>
                @include('admin.students._accommodation_fee')

                <div class="mv-note" x-cloak style="background:var(--he-info-soft); color:#0369a1; border-radius:var(--he-radius-md); padding:.6rem .8rem; font-size:.82rem; font-weight:600;">
                    <i class="fa-solid fa-circle-info me-1"></i>{{ __('The new rate applies from the next billing cycle. The current invoice stands as issued — no proration.') }}
                </div>
            </div>

            <div class="custom-overlay-footer bg-light">
                <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="close()">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-premium fw-semibold rounded-pill px-4 shadow-sm tactile-btn" :class="{ 'is-locked': !bed }">
                    <i class="fa-solid fa-check me-2"></i>{{ __('Confirm Transfer') }}
                </button>
            </div>
        </form>
    </div>
</template>

@once
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('accommodation', (cfg) => ({
        cfg,
        assignOpen: false,
        transferOpen: false,
        bedSearch: '',
        bed: null,
        joinDate: '{{ now()->toDateString() }}',
        meter: null,
        oldMeter: null,
        frequency: cfg.frequency || '',
        amount: cfg.fee > 0 ? cfg.fee : null,

        get filteredBeds() {
            const q = this.bedSearch.trim().toLowerCase();
            if (!q) return this.cfg.beds;
            return this.cfg.beds.filter((b) =>
                (b.room + ' ' + b.bed + ' ' + (b.floor || '')).toLowerCase().includes(q));
        },
        perLabel(freq) {
            return { monthly: '{{ __('/ mo') }}', semester: '{{ __('/ sem') }}', yearly: '{{ __('/ yr') }}' }[freq] || '';
        },

        reset() {
            this.bed = null;
            this.bedSearch = '';
            this.meter = null;
            this.oldMeter = null;
            this.joinDate = '{{ now()->toDateString() }}';
            this.frequency = this.cfg.frequency || '';
            this.amount = this.cfg.fee > 0 ? this.cfg.fee : null;
        },
        openAssign() {
            if (!this.cfg.beds.length) { window.Swal?.fire({ toast: true, position: 'top-end', icon: 'info', title: '{{ __('No vacant beds right now.') }}', showConfirmButton: false, timer: 3000 }); return; }
            this.reset();
            this.assignOpen = true;
            document.body.style.overflow = 'hidden';
        },
        openTransfer() {
            if (!this.cfg.beds.length) { window.Swal?.fire({ toast: true, position: 'top-end', icon: 'info', title: '{{ __('No other vacant beds to transfer into.') }}', showConfirmButton: false, timer: 3000 }); return; }
            this.reset();
            this.transferOpen = true;
            document.body.style.overflow = 'hidden';
        },
        pickBed(b) { this.bed = b; if (!b.is_ac) this.meter = null; },
        close() {
            this.assignOpen = this.transferOpen = false;
            document.body.style.overflow = '';
        },
    }));
});
</script>
@endpush
@endonce
