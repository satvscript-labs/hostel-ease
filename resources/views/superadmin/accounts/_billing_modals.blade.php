{{-- Redesigned billing modals for Account 360: Renew all, Add to cycle, Align.
     All three share the premium <x-he-modal> shell and the live, discount-aware
     <x-he-billing-summary>. Driven by the account360() Alpine scope in show.blade.php.
     Placed OUTSIDE the page's shared x-teleport wrapper so each modal teleports
     itself (no nested <template x-teleport>). --}}

@php $methods = ['cash' => 'Cash', 'upi' => 'UPI', 'cheque' => 'Cheque', 'rtgs' => 'RTGS / NEFT', 'online' => 'Online']; @endphp

{{-- ── Renew all ── --}}
<x-he-modal open="renewOpen" title="Renew all branches" icon="arrows-rotate"
    :action="route('superadmin.accounts.renew', $account)" :size="560">
    <input type="hidden" name="period" :value="period">

    <div class="d-flex gap-2 mb-4">
        <button type="button" class="btn flex-fill rounded-pill fw-bold tactile-btn" :class="period==='yearly'?'btn-primary':'btn-light border'" @click="period='yearly'">Yearly</button>
        <button type="button" class="btn flex-fill rounded-pill fw-bold tactile-btn" :class="period==='monthly'?'btn-primary':'btn-light border'" @click="period='monthly'">Monthly</button>
    </div>

    <x-he-billing-summary data="renewSummary" />

    <label class="form-label fw-bold small text-muted mt-3">AMOUNT OVERRIDE (₹) <span class="fw-normal">— optional</span></label>
    <input type="number" step="0.01" min="0" name="amount" x-model="renewOverride"
        class="form-control bg-white border shadow-sm" :placeholder="'Auto (' + heMoney(renewSummary.final) + ')'">
    <div class="form-text">Enter a lower amount to record a manual discount; the difference is logged on the order.</div>

    <label class="form-label fw-bold small text-muted mt-3">METHOD</label>
    <x-he-select name="payment_method" :submit="false" compact selected="cash" :options="$methods" />

    <label class="form-label fw-bold small text-muted mt-3">TXN / REMARKS</label>
    <input type="text" name="transaction_number" class="form-control bg-white border shadow-sm mb-2" placeholder="Reference (optional)">
    <input type="text" name="remarks" class="form-control bg-white border shadow-sm" placeholder="Remarks (optional)">

    <x-slot:footer>
        <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="renewOpen=false">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm"><i class="fa-solid fa-arrows-rotate me-2"></i>Renew</button>
    </x-slot:footer>
</x-he-modal>

{{-- ── Add branch to cycle ── --}}
<x-he-modal open="addOpen" title="Add branch to cycle" icon="plus"
    :action="route('superadmin.accounts.add-branch', $account)" :size="480">
    <input type="hidden" name="branch_id" :value="addBranchId">

    <p class="text-muted small mb-3">
        Co-terminates <span class="fw-bold text-dark" x-text="addBranchName"></span>
        on the renewal date (<span class="fw-semibold" x-text="addQuote.anchor"></span>) with a prorated top-up.
    </p>

    <x-he-billing-summary data="addSummary" />

    <label class="form-label fw-bold small text-muted mt-3">AMOUNT OVERRIDE (₹) <span class="fw-normal">— optional</span></label>
    <input type="number" step="0.01" min="0" name="amount" x-model="addOverride"
        class="form-control bg-white border shadow-sm" :placeholder="'Auto (' + heMoney(addSummary.final) + ')'">
    <div class="form-text">Enter a lower amount to record a manual discount; the difference is logged on the order.</div>

    <label class="form-label fw-bold small text-muted mt-3">METHOD</label>
    <x-he-select name="payment_method" :submit="false" compact selected="cash" :options="$methods" />

    <x-slot:footer>
        <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="addOpen=false">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm"><i class="fa-solid fa-plus me-2"></i>Add branch</button>
    </x-slot:footer>
</x-he-modal>

{{-- ── Align staggered branches ── --}}
<x-he-modal open="alignOpen" title="Align branches to renewal date" icon="diagram-project"
    :action="route('superadmin.accounts.align', $account)" :size="520">
    <p class="text-muted small mb-3">
        Tops up <span class="fw-bold text-dark" x-text="alignQuote.count"></span> branch(es) that end before the
        renewal date (<span class="fw-semibold" x-text="alignQuote.anchor"></span>), prorated per branch.
    </p>

    <x-he-billing-summary data="alignSummary" />

    <label class="form-label fw-bold small text-muted mt-3">AMOUNT OVERRIDE (₹) <span class="fw-normal">— optional</span></label>
    <input type="number" step="0.01" min="0" name="amount" x-model="alignOverride"
        class="form-control bg-white border shadow-sm" :placeholder="'Auto (' + heMoney(alignSummary.final) + ')'">
    <div class="form-text">Enter a lower total to record a manual discount, spread across the branches above.</div>

    <label class="form-label fw-bold small text-muted mt-3">METHOD</label>
    <x-he-select name="payment_method" :submit="false" compact selected="cash" :options="$methods" />

    <label class="form-label fw-bold small text-muted mt-3">REMARKS</label>
    <input type="text" name="remarks" class="form-control bg-white border shadow-sm" placeholder="Remarks (optional)">

    <x-slot:footer>
        <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="alignOpen=false">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm"><i class="fa-solid fa-diagram-project me-2"></i>Align</button>
    </x-slot:footer>
</x-he-modal>
