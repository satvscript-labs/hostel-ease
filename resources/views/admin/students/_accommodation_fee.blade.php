{{-- Fee plan (shared by Assign + Transfer): frequency chips + amount. The
     hidden fee_frequency input lives in each sheet's form; this only sets the
     Alpine state. --}}
<div class="mb-3">
    <div class="chip-group">
        <template x-for="(label, key) in cfg.frequencies" :key="'fq-' + key">
            <button type="button" class="chip" :class="{ active: frequency === key }" @click="frequency = key" x-text="label"></button>
        </template>
    </div>
</div>

<div class="mb-2">
    <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Amount') }} <span class="text-danger">*</span></label>
    <div class="input-group">
        <span class="input-group-text bg-light text-muted fw-bold">₹</span>
        <input type="number" name="fee_amount" x-model.number="amount" class="form-control bg-light fw-bold text-dark" min="0" step="0.01" required placeholder="0.00">
        <span class="input-group-text bg-light text-muted small" x-show="frequency" x-text="perLabel(frequency)"></span>
    </div>
</div>
