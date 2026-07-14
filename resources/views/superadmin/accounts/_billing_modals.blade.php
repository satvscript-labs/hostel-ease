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

{{-- ── Add hostel directly to this owner ── --}}
<x-he-modal open="addHostelOpen" title="Add hostel to {{ $account->owner?->name ?? 'owner' }}" icon="building-circle-arrow-right"
    :action="route('superadmin.accounts.add-hostel', $account)" :size="620">
    <input type="hidden" name="plan" :value="ahPlan === 'trial' ? 'trial' : ahPaidPeriod">
    <p class="text-muted small mb-3">A new branch under this existing owner — no re-entered name or mobile, and its first charge runs through the account so <strong>discounts apply</strong>.</p>

    <div class="row g-3">
        <div class="col-md-7">
            <label class="form-label fw-bold small text-muted">HOSTEL NAME <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control bg-white border shadow-sm" required autocomplete="off">
        </div>
        <div class="col-md-5">
            <label class="form-label fw-bold small text-muted">EMAIL</label>
            <input type="email" name="email" value="{{ $ownerEmail }}" class="form-control bg-white border shadow-sm" placeholder="Owner email">
        </div>
    </div>

    {{-- Optional address / GST --}}
    <div class="mt-2" x-data="{ moreOpen: false }">
        <button type="button" @click="moreOpen = !moreOpen" class="btn btn-link text-muted fw-bold text-decoration-none p-0 small"><i class="fa-solid fa-location-dot me-1"></i> Address & GST <i class="fa-solid fa-chevron-down ms-1" :class="{ 'fa-rotate-180': moreOpen }" style="font-size:.7rem;"></i></button>
        <div class="row g-3 mt-1" x-show="moreOpen" x-collapse x-cloak>
            <div class="col-12"><label class="form-label fw-bold small text-muted">ADDRESS</label><textarea name="address" rows="2" class="form-control bg-white border shadow-sm"></textarea></div>
            <div class="col-md-4"><label class="form-label fw-bold small text-muted">CITY</label><input type="text" name="city" class="form-control bg-white border shadow-sm"></div>
            <div class="col-md-4"><label class="form-label fw-bold small text-muted">STATE</label><input type="text" name="state" class="form-control bg-white border shadow-sm"></div>
            <div class="col-md-4"><label class="form-label fw-bold small text-muted">GST</label><input type="text" name="gst_number" class="form-control bg-white border shadow-sm"></div>
        </div>
    </div>

    <hr class="my-3 text-muted">

    {{-- Plan: Paid co-terminates at the account's own cadence; Trial is a free 14-day clock. --}}
    <label class="form-label fw-bold small text-muted">PLAN</label>
    <div class="d-flex gap-2 mb-3">
        <button type="button" class="btn flex-fill rounded-pill fw-bold tactile-btn" :class="ahPlan==='paid'?'btn-primary':'btn-light border'" @click="ahPlan='paid'">
            Paid · co-terminate <span class="text-capitalize" x-text="'(' + ahPaidPeriod + ')'"></span>
        </button>
        <button type="button" class="btn flex-fill rounded-pill fw-bold tactile-btn" :class="ahPlan==='trial'?'btn-primary':'btn-light border'" @click="ahPlan='trial'">Trial (14 days)</button>
    </div>

    <x-he-billing-summary data="addHostelSummary" />

    {{-- Override + method only for paid plans --}}
    <div x-show="ahPlan !== 'trial'" x-cloak>
        <label class="form-label fw-bold small text-muted mt-3">AMOUNT OVERRIDE (₹) <span class="fw-normal">— optional</span></label>
        <input type="number" step="0.01" min="0" name="amount" x-model="ahOverride"
            class="form-control bg-white border shadow-sm" :placeholder="'Auto (' + heMoney(addHostelSummary.final) + ')'">
        <div class="form-text">Enter a lower amount to record a manual discount; the difference is logged on the order.</div>

        <label class="form-label fw-bold small text-muted mt-3">METHOD</label>
        <x-he-select name="payment_method" :submit="false" compact selected="cash" :options="$methods" />
    </div>

    <x-slot:footer>
        <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="addHostelOpen=false">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm"><i class="fa-solid fa-plus me-2"></i>Add hostel</button>
    </x-slot:footer>
</x-he-modal>

{{-- ── Add negotiated discount ── --}}
<x-he-modal open="discountOpen" title="Add discount" icon="percent"
    :action="route('superadmin.accounts.discounts.store', $account)" :size="560">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-bold small text-muted">APPLIES</label>
            <x-he-select name="recurrence" :submit="false" compact selected="one_time" :options="[
                'one_time' => 'One-time (next charge)',
                'one_renewal' => 'Next renewal only',
                'every_renewal' => 'Permanent (every renewal)',
            ]" />
        </div>
        <div class="col-md-6">
            <label class="form-label fw-bold small text-muted">TYPE</label>
            <x-he-select name="type" :submit="false" compact x-model="dType" :options="['percentage' => 'Percentage (%)', 'fixed' => 'Fixed (₹)']" />
        </div>
        <div class="col-md-6">
            <label class="form-label fw-bold small text-muted">VALUE <span class="text-danger">*</span></label>
            <div class="input-group shadow-sm">
                <span class="input-group-text bg-white fw-bold text-muted" x-text="dType==='percentage' ? '%' : '₹'"></span>
                <input type="number" step="0.01" min="0" name="value" class="form-control border" required>
            </div>
        </div>
        <div class="col-md-6" x-show="dType==='percentage'" x-cloak>
            <label class="form-label fw-bold small text-muted">MAX ₹ CAP <span class="fw-normal">— optional</span></label>
            <input type="number" step="0.01" min="0" name="max_amount" class="form-control bg-white border shadow-sm">
        </div>
        <div class="col-md-6"><label class="form-label fw-bold small text-muted">STARTS <span class="fw-normal">— optional</span></label><input type="date" name="starts_at" class="form-control bg-white border shadow-sm"></div>
        <div class="col-md-6"><label class="form-label fw-bold small text-muted">ENDS <span class="fw-normal">— optional</span></label><input type="date" name="ends_at" class="form-control bg-white border shadow-sm"></div>
        <div class="col-12"><label class="form-label fw-bold small text-muted">REASON <span class="text-danger">*</span></label><input type="text" name="reason" class="form-control bg-white border shadow-sm" placeholder="Negotiation context" required></div>
    </div>

    <x-slot:footer>
        <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="discountOpen=false">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm"><i class="fa-solid fa-percent me-2"></i>Add discount</button>
    </x-slot:footer>
</x-he-modal>

{{-- ── Comp (complimentary ₹0 coverage) ── --}}
<x-he-modal open="compOpen" title="Complimentary coverage" icon="gift"
    :action="route('superadmin.accounts.comp', $account)" :size="600">
    <input type="hidden" name="period" :value="compTerm">
    {{-- Selected branch ids submit as branches[] --}}
    <template x-for="id in compSelected" :key="id"><input type="hidden" name="branches[]" :value="id"></template>

    {{-- Term + multiplier --}}
    <div class="row g-3 mb-3">
        <div class="col-sm-6">
            <label class="form-label fw-bold small text-muted">TERM</label>
            <div class="d-flex gap-2">
                <button type="button" class="btn flex-fill rounded-pill fw-bold tactile-btn" :class="compTerm==='monthly'?'btn-primary':'btn-light border'" @click="compTerm='monthly'">Monthly</button>
                <button type="button" class="btn flex-fill rounded-pill fw-bold tactile-btn" :class="compTerm==='yearly'?'btn-primary':'btn-light border'" @click="compTerm='yearly'">Yearly</button>
            </div>
        </div>
        <div class="col-sm-6">
            <label class="form-label fw-bold small text-muted">HOW MANY</label>
            <div class="comp-stepper">
                <button type="button" class="comp-step tactile-btn" @click="compMultiplier = Math.max(1, (parseInt(compMultiplier)||1) - 1)"><i class="fa-solid fa-minus"></i></button>
                <input type="number" min="1" max="60" name="multiplier" x-model.number="compMultiplier" class="comp-step-input">
                <button type="button" class="comp-step tactile-btn" @click="compMultiplier = Math.min(60, (parseInt(compMultiplier)||1) + 1)"><i class="fa-solid fa-plus"></i></button>
            </div>
            <div class="form-text">= <span class="fw-bold text-primary" x-text="compMultiplierLabel"></span> of free coverage</div>
        </div>
    </div>

    {{-- Branch selector (checkbox tiles) --}}
    <div class="d-flex justify-content-between align-items-center mb-2">
        <label class="form-label fw-bold small text-muted mb-0">BRANCHES</label>
        <button type="button" class="btn btn-link btn-sm text-decoration-none fw-semibold p-0" @click="toggleCompAll()" x-text="compAllSelected ? 'Clear all' : 'Select all'"></button>
    </div>
    <div class="row g-2 mb-1">
        <template x-for="b in compBranches" :key="b.id">
            <div class="col-sm-6">
                <button type="button" class="comp-tile w-100" :class="{ 'is-selected': compSelected.includes(b.id) }" @click="toggleCompBranch(b.id)">
                    <span class="comp-tile-check"><i class="fa-solid fa-check"></i></span>
                    <span class="text-start">
                        <span class="comp-tile-name" x-text="b.name"></span>
                        <span class="comp-tile-end" x-text="'ends ' + b.endLabel"></span>
                    </span>
                </button>
            </div>
        </template>
    </div>

    {{-- Live gift preview --}}
    <div class="comp-preview mt-3" x-show="compSelected.length" x-cloak>
        <div class="comp-preview-head">
            <i class="fa-solid fa-gift text-primary me-2"></i>
            <span x-text="compSelected.length"></span> branch(es) get <span class="fw-bold" x-text="compMultiplierLabel"></span> free
            <span class="comp-preview-badge">₹0.00 · Complimentary</span>
        </div>
        <template x-for="row in compPreview" :key="row.name">
            <div class="comp-preview-row">
                <span class="fw-semibold text-dark" x-text="row.name"></span>
                <span class="small text-muted"><span x-text="row.from"></span> <i class="fa-solid fa-arrow-right-long mx-1" style="font-size:.7rem;"></i> <span class="fw-semibold text-success" x-text="row.to"></span></span>
            </div>
        </template>
    </div>
    <div class="text-danger small mt-2" x-show="!compSelected.length" x-cloak><i class="fa-solid fa-triangle-exclamation me-1"></i>Select at least one branch.</div>

    <label class="form-label fw-bold small text-muted mt-3">REASON</label>
    <input type="text" name="reason" class="form-control bg-white border shadow-sm" placeholder="Why this comp? (e.g. referred 3 customers)" required>

    <x-slot:footer>
        <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="compOpen=false">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm" :disabled="!compSelected.length"><i class="fa-solid fa-gift me-2"></i>Grant</button>
    </x-slot:footer>
</x-he-modal>

{{-- ── Custom unit price (per-period) ── --}}
<x-he-modal open="overrideOpen" title="Custom unit price" icon="tag"
    :action="route('superadmin.accounts.override', $account)" :size="520">
    <p class="text-muted small mb-3">A bespoke per-branch price for this account. Pick a term, then set its price — a custom <strong>yearly</strong> rate no longer affects <strong>monthly</strong> renewals. Leave a term's field blank to use list price for that term.</p>

    {{-- Term toggle --}}
    <div class="d-flex gap-2 mb-3">
        <button type="button" class="btn flex-fill rounded-pill fw-bold tactile-btn" :class="priceTab==='yearly'?'btn-primary':'btn-light border'" @click="priceTab='yearly'">Yearly</button>
        <button type="button" class="btn flex-fill rounded-pill fw-bold tactile-btn" :class="priceTab==='monthly'?'btn-primary':'btn-light border'" @click="priceTab='monthly'">Monthly</button>
    </div>

    <label class="form-label fw-bold small text-muted">CUSTOM <span x-text="priceTab==='monthly'?'MONTHLY':'YEARLY'"></span> PRICE <span class="fw-normal">(₹ / branch)</span></label>
    {{-- Both inputs stay in the form (only the active one is shown) so one Save persists both terms. --}}
    <div x-show="priceTab==='yearly'">
        <div class="input-group shadow-sm">
            <span class="input-group-text bg-white fw-bold text-muted">₹</span>
            <input type="number" step="0.01" min="0" name="unit_price_override_yearly" x-model="priceYearly" class="form-control border" :placeholder="'List price · ' + heMoney(listYearly)">
            <button type="button" class="btn btn-light border" @click="priceYearly=''" x-show="priceYearly!==''" x-cloak title="Reset to list"><i class="fa-solid fa-xmark"></i></button>
        </div>
    </div>
    <div x-show="priceTab==='monthly'" x-cloak>
        <div class="input-group shadow-sm">
            <span class="input-group-text bg-white fw-bold text-muted">₹</span>
            <input type="number" step="0.01" min="0" name="unit_price_override_monthly" x-model="priceMonthly" class="form-control border" :placeholder="'List price · ' + heMoney(listMonthly)">
            <button type="button" class="btn btn-light border" @click="priceMonthly=''" x-show="priceMonthly!==''" x-cloak title="Reset to list"><i class="fa-solid fa-xmark"></i></button>
        </div>
    </div>

    {{-- Effective prices for both terms --}}
    <div class="od-label mt-3 mb-2">Effective price / branch</div>
    <div class="he-summary shadow-sm">
        <div class="he-summary-row he-summary-row--line">
            <span>Yearly</span>
            <span class="he-summary-amt">
                <span x-text="heMoney(priceEffective('yearly').amount)"></span>
                <span class="badge bg-warning-subtle text-warning rounded-pill ms-1" style="font-size:.58rem;" x-show="priceEffective('yearly').custom" x-cloak>custom</span>
            </span>
        </div>
        <div class="he-summary-row he-summary-row--line">
            <span>Monthly</span>
            <span class="he-summary-amt">
                <span x-text="heMoney(priceEffective('monthly').amount)"></span>
                <span class="badge bg-warning-subtle text-warning rounded-pill ms-1" style="font-size:.58rem;" x-show="priceEffective('monthly').custom" x-cloak>custom</span>
            </span>
        </div>
    </div>

    <x-slot:footer>
        <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="overrideOpen=false">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm"><i class="fa-solid fa-check me-2"></i>Save</button>
    </x-slot:footer>
</x-he-modal>
