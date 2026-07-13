{{-- Reusable, discount-aware charge summary for billing modals (Renew all,
     Add to cycle, Align). Purely presentational: it renders whatever a caller's
     Alpine scope exposes, so the preview always mirrors the server-side charge
     (both come from DiscountService::preview()).

     Pass the name of an Alpine expression that evaluates to:
       { rows: [ { label, amount, kind } ], finalLabel, final, note }
     where kind is 'line' (subtotal contributor), 'discount' (shown as −amount,
     green), or 'subtle' (muted intermediate like "Auto price"). See
     .agents/ui_design_guidelines.md — tokens/motion come from _premium.scss. --}}
@props(['data'])
@pushOnce('scripts')
<script>
    window.heMoney = (v) => '₹' + Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
</script>
@endPushOnce
@pushOnce('styles')
<style>
    .he-summary { background: var(--he-bg-surface); border: 1px solid rgba(15,23,42,.08); border-radius: var(--he-radius-lg); overflow: hidden; }
    .he-summary-row { display:flex; justify-content:space-between; align-items:center; gap:1rem; padding:.6rem 1rem; font-size:.9rem; }
    .he-summary-row + .he-summary-row { border-top:1px dashed rgba(15,23,42,.07); }
    .he-summary-row .he-summary-amt { font-weight:700; font-variant-numeric:tabular-nums; white-space:nowrap; }
    .he-summary-row--line { color: var(--he-text-main); font-weight:500; }
    .he-summary-row--discount { color: var(--he-success); }
    .he-summary-row--subtle { color: var(--he-text-muted); }
    .he-summary-total { display:flex; justify-content:space-between; align-items:center; gap:1rem; padding:.85rem 1rem; background: var(--he-primary-soft); border-top:1px solid rgba(79,70,229,.18); }
    .he-summary-total .he-summary-total-lbl { font-weight:700; color: var(--he-text-main); }
    .he-summary-total .he-summary-total-val { font-weight:800; font-size:1.35rem; color: var(--he-primary); font-variant-numeric:tabular-nums; letter-spacing:-.5px; }
    .he-summary-note { padding:.55rem 1rem; font-size:.75rem; color: var(--he-text-muted); background: var(--he-bg-surface-raised); border-top:1px solid rgba(15,23,42,.05); }
</style>
@endPushOnce
<div class="he-summary shadow-sm">
    <template x-for="(row, i) in ({{ $data }}.rows || [])" :key="i">
        <div class="he-summary-row" :class="'he-summary-row--' + (row.kind || 'line')">
            <span x-text="row.label"></span>
            <span class="he-summary-amt" x-text="(row.kind === 'discount' ? '−' : '') + heMoney(row.amount)"></span>
        </div>
    </template>
    <div class="he-summary-total">
        <span class="he-summary-total-lbl" x-text="{{ $data }}.finalLabel || 'Payable now'"></span>
        <span class="he-summary-total-val" x-text="heMoney({{ $data }}.final)"></span>
    </div>
    <template x-if="{{ $data }}.note">
        <div class="he-summary-note" x-text="{{ $data }}.note"></div>
    </template>
</div>
