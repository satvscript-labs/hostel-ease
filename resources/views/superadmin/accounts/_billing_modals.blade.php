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

    <hr class="my-3 text-muted">
    @include('superadmin.accounts._collect_toggle', ['model' => 'renewCollect'])

    {{-- Only meaningful when the money is already in hand. A link has no payment
         instrument yet, and the controller drops these on that path anyway. --}}
    <div x-show="renewCollect === 'offline'" x-collapse>
        <label class="form-label fw-bold small text-muted">METHOD</label>
        <x-he-select name="payment_method" :submit="false" compact selected="cash" :options="$methods" />

        <label class="form-label fw-bold small text-muted mt-3">TXN / REMARKS</label>
        <input type="text" name="transaction_number" class="form-control bg-white border shadow-sm mb-2" placeholder="Reference (optional)">
    </div>
    <input type="text" name="remarks" class="form-control bg-white border shadow-sm" placeholder="Remarks (optional)">

    <x-slot:footer>
        <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="renewOpen=false">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
            <i class="fa-solid me-2" :class="renewCollect === 'link' ? 'fa-link' : 'fa-arrows-rotate'"></i>
            <span x-text="renewCollect === 'link' ? 'Create link' : 'Renew'"></span>
        </button>
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

    <hr class="my-3 text-muted">
    @include('superadmin.accounts._collect_toggle', ['model' => 'addCollect'])

    <div x-show="addCollect === 'offline'" x-collapse>
        <label class="form-label fw-bold small text-muted">METHOD</label>
        <x-he-select name="payment_method" :submit="false" compact selected="cash" :options="$methods" />
    </div>

    <div class="alert border-0 rounded-4 small mt-3 mb-0" x-show="addCollect === 'link'" x-cloak
         style="background: rgba(79,70,229,.07); color:#3730a3;">
        <i class="fa-solid fa-circle-info me-1"></i>
        The branch is <strong>not</strong> co-terminated until the link is paid — until then this is a
        quote, and the branch keeps whatever coverage it already has.
    </div>

    <x-slot:footer>
        <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="addOpen=false">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
            <i class="fa-solid me-2" :class="addCollect === 'link' ? 'fa-link' : 'fa-plus'"></i>
            <span x-text="addCollect === 'link' ? 'Create link' : 'Add branch'"></span>
        </button>
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

    <hr class="my-3 text-muted">
    @include('superadmin.accounts._collect_toggle', ['model' => 'alignCollect'])

    <div x-show="alignCollect === 'offline'" x-collapse>
        <label class="form-label fw-bold small text-muted">METHOD</label>
        <x-he-select name="payment_method" :submit="false" compact selected="cash" :options="$methods" />
    </div>

    <label class="form-label fw-bold small text-muted mt-3">REMARKS</label>
    <input type="text" name="remarks" class="form-control bg-white border shadow-sm" placeholder="Remarks (optional)">

    <x-slot:footer>
        <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="alignOpen=false">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
            <i class="fa-solid me-2" :class="alignCollect === 'link' ? 'fa-link' : 'fa-diagram-project'"></i>
            <span x-text="alignCollect === 'link' ? 'Create link' : 'Align'"></span>
        </button>
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

{{-- ══════════════════════════════════════════════════════════════════════════
     Branch removal (D11). The operator decides; the owner only ever asks.
     The confirm modal leads with the CONSEQUENCES, because the two that surprise
     people are (a) the branch keeps working — this is not a shut-off — and (b) a
     lost volume tier can make the REMAINING branches dearer.
   ══════════════════════════════════════════════════════════════════════════ --}}
{{-- STATIC action + a posted integer branch_id. `::action="..."` would NOT fill the
     component's $action prop — it lands in $attributes, the component falls back to
     rendering a <div> instead of a <form>, and the button silently does nothing. --}}
<x-he-modal open="cancelOpen" title="Remove a branch from billing" icon="circle-minus" :size="620"
    :action="route('superadmin.accounts.branches.cancel', $account)" method="POST">
    <input type="hidden" name="branch_id" :value="cancelBranchId">
    <div class="od-label mb-2">Branch</div>
    <div class="he-summary shadow-sm mb-3">
        <div class="he-summary-row he-summary-row--line">
            <span x-text="cancelBranchName"></span>
            <span class="he-summary-amt" x-text="cancelImpact ? ('covered to ' + cancelImpact.covered_to) : ''"></span>
        </div>
    </div>

    <div class="alert border-0 rounded-4 small mb-3" style="background: rgba(79,70,229,.07); color:#3730a3;">
        <i class="fa-solid fa-circle-info me-1"></i>
        <strong>It keeps working.</strong> The branch stays active until the coverage it has already paid for runs out — it is simply not billed again. No refund, no credit.
    </div>

    <template x-if="cancelImpact && cancelImpact.tier_lost">
        <div class="alert border-0 rounded-4 small mb-3" style="background: rgba(234,88,12,.1); color:#9a3412;">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            <strong>This loses a volume discount.</strong>
            Per-branch price goes from <span class="fw-bold" x-text="heMoney(cancelImpact.per_branch_now)"></span>
            to <span class="fw-bold" x-text="heMoney(cancelImpact.per_branch_after)"></span> —
            the branches that stay get <em>more</em> expensive. Worth a conversation first.
        </div>
    </template>

    <template x-if="cancelImpact && cancelImpact.closes_account">
        <div class="alert border-0 rounded-4 small mb-3" style="background: rgba(220,38,38,.1); color:#991b1b;">
            <i class="fa-solid fa-circle-exclamation me-1"></i>
            <strong>This is the last billable branch.</strong> The account will have nothing left to renew.
        </div>
    </template>

    <template x-if="cancelImpact && cancelImpact.pending">
        <div class="alert border-0 rounded-4 small mb-3" style="background: rgba(234,88,12,.1); color:#9a3412;">
            <i class="fa-solid fa-file-invoice me-1"></i>
            There <span x-text="cancelImpact.pending === 1 ? 'is' : 'are'"></span>
            <span class="fw-bold" x-text="cancelImpact.pending"></span>
            unpaid <span x-text="cancelImpact.pending === 1 ? 'charge' : 'charges'"></span> against this branch.
            Decide whether to collect or void <span class="fw-semibold">it</span> in the Orders list.
        </div>
    </template>

    <div class="he-summary shadow-sm mb-3">
        <div class="he-summary-row he-summary-row--line">
            <span>Branches billed now</span>
            <span class="he-summary-amt" x-text="cancelImpact ? cancelImpact.quantity_now : ''"></span>
        </div>
        <div class="he-summary-row he-summary-row--line">
            <span>After removal</span>
            <span class="he-summary-amt" x-text="cancelImpact ? cancelImpact.quantity_after : ''"></span>
        </div>
        <div class="he-summary-row he-summary-row--total">
            <span>Next renewal</span>
            <span class="he-summary-amt">
                <span class="text-muted text-decoration-line-through me-2" x-text="cancelImpact ? heMoney(cancelImpact.total_now) : ''"></span>
                <span x-text="cancelImpact ? heMoney(cancelImpact.total_after) : ''"></span>
            </span>
        </div>
    </div>

    <label class="form-label fw-bold small text-muted">REASON <span class="text-danger">*</span></label>
    <input type="text" name="reason" class="form-control bg-white border shadow-sm" required maxlength="255"
           placeholder="e.g. owner closing the property / consolidating branches">
    <div class="form-text">Recorded on the branch and in the audit log — it is the churn reason you will want later.</div>

    <x-slot:footer>
        <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="cancelOpen=false">Keep billing</button>
        <button type="submit" class="btn btn-danger rounded-pill px-5 fw-bold shadow-sm"><i class="fa-solid fa-circle-minus me-2"></i>Remove from billing</button>
    </x-slot:footer>
</x-he-modal>

<x-he-modal open="declineOpen" title="Close the removal request" icon="hand" :size="560"
    :action="route('superadmin.accounts.branches.decline-removal', $account)" method="POST">
    <input type="hidden" name="branch_id" :value="declineBranchId">
    <p class="small text-muted mb-3">
        <span class="fw-bold text-dark" x-text="declineBranchName"></span> stays on the plan and keeps being billed.
        The owner is notified that the request was closed, so the ask does not just disappear on them.
    </p>
    <label class="form-label fw-bold small text-muted">NOTE TO THE OWNER</label>
    <input type="text" name="note" class="form-control bg-white border shadow-sm" maxlength="255"
           placeholder="e.g. agreed a discount instead — keeping the branch">
    <x-slot:footer>
        <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="declineOpen=false">Back</button>
        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm"><i class="fa-solid fa-check me-2"></i>Close request</button>
    </x-slot:footer>
</x-he-modal>

{{-- Void an order (S1 / D8) — the "write off a mistaken record" capability the
     retired legacy page used to hold. Never a delete: the row and its invoice
     number survive. Voiding a PAID order WITHDRAWS the coverage it granted, which
     is the one routine path allowed to shorten a branch's coverage, so the warning
     is explicit and the reason mandatory. --}}
{{-- method="PATCH" is a PROP: the component emits the @method spoof itself. --}}
<x-he-modal open="voidOpen" title="Void this order" icon="ban" :size="560"
    :action="route('superadmin.accounts.orders.void', $account)" method="PATCH">
    <input type="hidden" name="order_id" :value="voidOrderId">
    <div class="he-summary shadow-sm mb-3">
        <div class="he-summary-row he-summary-row--line">
            <span>Order</span>
            <span class="he-summary-amt" x-text="voidOrderLabel"></span>
        </div>
    </div>

    <template x-if="voidWasPaid">
        <div class="alert border-0 rounded-4 small mb-3" style="background: rgba(220,38,38,.1); color:#991b1b;">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            <strong>This order is paid.</strong> Voiding it withdraws the coverage it granted, so the
            branches on it may lose access. Use this only for a record that should never have existed —
            not for a refund.
        </div>
    </template>

    <p class="small text-muted mb-3">
        The order stays in the ledger marked <strong>voided</strong>, keeping its invoice number. It
        stops counting as revenue and stops being owed.
    </p>

    <label class="form-label fw-bold small text-muted">REASON <span class="text-danger">*</span></label>
    <input type="text" name="reason" class="form-control bg-white border shadow-sm" required maxlength="255"
           placeholder="e.g. recorded against the wrong customer">

    <x-slot:footer>
        <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="voidOpen=false">Cancel</button>
        <button type="submit" class="btn btn-danger rounded-pill px-5 fw-bold shadow-sm"><i class="fa-solid fa-ban me-2"></i>Void order</button>
    </x-slot:footer>
</x-he-modal>

{{-- ══════════════════════════════════════════════════════════════════════════
     Payment links (S2). Issuing is NOT here — it is the `collect` toggle above,
     deliberately on the same route that records the charge offline, so the two
     can never price it differently. What lives here is the lifecycle: share the
     link you just made, and kill one that should not be payable any more.
   ══════════════════════════════════════════════════════════════════════════ --}}

{{-- Share. Opens by itself after a link is created (the URL is the deliverable —
     making the operator hunt for it would be the whole point missed), and on
     demand from any live link in the Orders list. --}}
<x-he-modal open="shareOpen" title="Send this payment link" icon="paper-plane" :size="560">
    <div class="he-summary shadow-sm mb-3">
        <div class="he-summary-row he-summary-row--line">
            <span x-text="share.invoice"></span>
            <span class="he-summary-amt" x-text="heMoney(share.amount)"></span>
        </div>
        <div class="he-summary-row he-summary-row--line" x-show="share.expires" x-cloak>
            <span>Expires</span>
            <span class="he-summary-amt" x-text="share.expires"></span>
        </div>
    </div>

    <label class="form-label fw-bold small text-muted">LINK</label>
    <div class="input-group shadow-sm mb-3">
        <input type="text" class="form-control bg-white border font-monospace" :value="share.url" readonly
               @click="$event.target.select()">
        <button type="button" class="btn btn-primary fw-bold px-3" @click="copyShare()">
            <i class="fa-solid fa-copy me-1"></i>Copy
        </button>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-light border rounded-pill px-3 fw-semibold" target="_blank" rel="noopener"
           :href="waHref()" x-show="share.url" x-cloak>
            <i class="fa-brands fa-whatsapp text-success me-1"></i>WhatsApp
        </a>
        <a class="btn btn-light border rounded-pill px-3 fw-semibold" :href="mailHref()" x-show="share.url" x-cloak>
            <i class="fa-regular fa-envelope text-primary me-1"></i>Email
        </a>
        <a class="btn btn-light border rounded-pill px-3 fw-semibold" target="_blank" rel="noopener"
           :href="share.url" x-show="share.url" x-cloak>
            <i class="fa-solid fa-arrow-up-right-from-square text-muted me-1"></i>Open
        </a>
    </div>

    <p class="small text-muted mt-3 mb-0">
        Razorpay has already sent this to the owner by SMS (and email, if one is on file) and will
        chase it on its own. Sending it yourself as well is belt and braces, not a duplicate charge —
        the link can only be paid once.
    </p>

    <x-slot:footer>
        <button type="button" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm" @click="shareOpen=false">Done</button>
    </x-slot:footer>
</x-he-modal>

{{-- Cancel a live link. STATIC action + posted integer order_id — `::action` would
     land in $attributes and the component would render a <div>, giving a button
     that silently does nothing (08_S1_VERIFICATION.md §2). --}}
<x-he-modal open="linkCancelOpen" title="Cancel this payment link" icon="link-slash" :size="560"
    :action="route('superadmin.accounts.orders.link.cancel', $account)" method="POST">
    <input type="hidden" name="order_id" :value="linkOrderId">

    <div class="he-summary shadow-sm mb-3">
        <div class="he-summary-row he-summary-row--line">
            <span>Charge</span>
            <span class="he-summary-amt" x-text="linkOrderLabel"></span>
        </div>
    </div>

    <div class="alert border-0 rounded-4 small mb-3" style="background: rgba(79,70,229,.07); color:#3730a3;">
        <i class="fa-solid fa-circle-info me-1"></i>
        <strong>The charge stays owed.</strong> Only the link dies — the order remains unpaid and in
        receivables, and you can issue a fresh link or record the money offline at any time.
    </div>

    <label class="form-label fw-bold small text-muted">REASON <span class="fw-normal">— optional</span></label>
    <input type="text" name="reason" class="form-control bg-white border shadow-sm" maxlength="255"
           placeholder="e.g. customer is paying by bank transfer instead">

    <x-slot:footer>
        <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="linkCancelOpen=false">Keep it live</button>
        <button type="submit" class="btn btn-danger rounded-pill px-5 fw-bold shadow-sm"><i class="fa-solid fa-link-slash me-2"></i>Cancel link</button>
    </x-slot:footer>
</x-he-modal>
