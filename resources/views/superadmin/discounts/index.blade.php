@extends('layouts.app')
@section('title', 'Discounts')

@push('styles')
<style>
    .panel-card { background:#fff; border:1px solid rgba(0,0,0,0.05); border-radius:1.1rem; transition:all .3s cubic-bezier(.25,1,.5,1); }
    .panel-card:hover { box-shadow:0 12px 30px rgba(0,0,0,0.04); }
    .rec-pill { font-size:.68rem; font-weight:700; letter-spacing:.3px; }
    .custom-overlay-backdrop { position:fixed; inset:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(8px); z-index:9999; display:flex; align-items:center; justify-content:center; padding:1rem; }
    .custom-overlay-modal { width:100%; background:#fff; border-radius:1.25rem; box-shadow:0 25px 50px -12px rgba(0,0,0,.25); display:flex; flex-direction:column; max-height:90vh; transform:scale(.95); opacity:0; transition:all .3s cubic-bezier(.16,1,.3,1); overflow:hidden; }
    .custom-overlay-modal.is-open { transform:scale(1); opacity:1; }
    .custom-overlay-header { padding:1.25rem 1.5rem; border-bottom:1px solid rgba(0,0,0,.05); display:flex; justify-content:space-between; align-items:center; }
    .custom-overlay-body { padding:1.5rem; overflow-y:auto; background:#fafafa; }
    .custom-overlay-footer { padding:1.25rem 1.5rem; border-top:1px solid rgba(0,0,0,.05); display:flex; gap:1rem; justify-content:flex-end; }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="discountRules()">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark tracking-tight">Discounts</h1>
            <p class="text-muted mb-0 small">Automatic volume tiers by branch count, and every negotiated discount across accounts.</p>
        </div>
    </div>

    {{-- ── Volume tiers ── --}}
    <div class="panel-card shadow-sm mb-4">
        <div class="p-3 px-4 border-bottom d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-layer-group text-primary me-2"></i>Volume tiers</h6>
                <span class="badge bg-info-subtle text-info rounded-pill rec-pill px-2 py-1">Stacking: {{ $stacking === 'greater' ? 'best of' : 'sequential' }}</span>
            </div>
            <button class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-sm" @click="openCreate()"><i class="fa-solid fa-plus me-1"></i>Add tier</button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase" style="font-size:.7rem; letter-spacing:.5px;"><tr>
                    <th class="py-3 px-4 border-0">When branches ≥</th>
                    <th class="py-3 px-4 border-0">Discount</th>
                    <th class="py-3 px-4 border-0">Cap</th>
                    <th class="py-3 px-4 border-0 text-center">Status</th>
                    <th class="py-3 px-4 border-0 text-end"></th>
                </tr></thead>
                <tbody class="border-top-0">
                @forelse($rules as $rule)
                    <tr>
                        <td class="px-4 py-3 fw-bold text-dark">{{ $rule->min_quantity }}</td>
                        <td class="px-4 py-3 fw-semibold text-primary">{{ $rule->type->value === 'percentage' ? rtrim(rtrim(number_format($rule->value,2),'0'),'.').'%' : hostelease_money($rule->value) }}</td>
                        <td class="px-4 py-3 text-muted">{{ $rule->max_amount !== null ? hostelease_money($rule->max_amount) : '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <form method="POST" action="{{ route('superadmin.discounts.rules.toggle', $rule) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <button class="badge border-0 bg-{{ $rule->active ? 'success' : 'secondary' }}-subtle text-{{ $rule->active ? 'success' : 'secondary' }} rounded-pill px-3 py-2" title="Toggle">{{ $rule->active ? 'Active' : 'Off' }}</button>
                            </form>
                        </td>
                        <td class="px-4 py-3 text-end text-nowrap">
                            <button class="btn btn-sm btn-light rounded-circle shadow-sm mx-1" style="width:32px;height:32px;" title="Edit"
                                @click="openEdit({{ $rule->id }})">
                                <i class="fa-solid fa-pen text-primary"></i>
                            </button>
                            <form method="POST" action="{{ route('superadmin.discounts.rules.destroy', $rule) }}" class="d-inline" data-confirm="Delete this volume tier?">
                                @csrf @method('DELETE')<button class="btn btn-sm btn-light rounded-circle shadow-sm" style="width:32px;height:32px;" title="Delete"><i class="fa-solid fa-trash text-danger"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-0"><x-he-empty-state icon="layer-group" title="No volume tiers"
                        subtitle="Add a tier to automatically discount accounts with many branches." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Manual discounts across accounts ── --}}
    <div class="panel-card shadow-sm">
        <div class="p-3 px-4 border-bottom"><h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-handshake text-primary me-2"></i>Negotiated discounts</h6></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase" style="font-size:.7rem; letter-spacing:.5px;"><tr>
                    <th class="py-3 px-4 border-0">Owner</th>
                    <th class="py-3 px-4 border-0">Discount</th>
                    <th class="py-3 px-4 border-0">Applies</th>
                    <th class="py-3 px-4 border-0 text-center">Status</th>
                    <th class="py-3 px-4 border-0">Reason</th>
                    <th class="py-3 px-4 border-0 text-end"></th>
                </tr></thead>
                <tbody class="border-top-0">
                @forelse($manual as $discount)
                    <tr>
                        <td class="px-4 py-3 fw-bold text-dark">{{ $discount->account?->owner?->name ?? '—' }}</td>
                        <td class="px-4 py-3 fw-semibold text-primary">{{ $discount->type->value === 'percentage' ? rtrim(rtrim(number_format($discount->value,2),'0'),'.').'%' : hostelease_money($discount->value) }}</td>
                        <td class="px-4 py-3"><span class="badge bg-primary-subtle text-primary rounded-pill rec-pill px-2 py-1">{{ $discount->recurrence->label() }}</span></td>
                        <td class="px-4 py-3 text-center"><span class="badge bg-{{ $discount->status->value === 'active' ? 'success' : 'secondary' }}-subtle text-{{ $discount->status->value === 'active' ? 'success' : 'secondary' }} rounded-pill px-3 py-1">{{ $discount->status->label() }}</span></td>
                        <td class="px-4 py-3 text-muted small text-truncate" style="max-width:220px;">{{ $discount->reason }}</td>
                        <td class="px-4 py-3 text-end">
                            @if($discount->account)
                            <a href="{{ route('superadmin.accounts.show', $discount->account) }}" class="btn btn-sm btn-light text-primary rounded-pill px-3 fw-semibold shadow-sm">Open</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-0"><x-he-empty-state icon="handshake" title="No negotiated discounts"
                        subtitle="Discounts added on an Account 360 page appear here." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($manual->hasPages())<div class="p-3 border-top">{{ $manual->links() }}</div>@endif
    </div>

    {{-- ══ Add/Edit tier modal ══ --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="modalOpen" x-transition.opacity @click.self="modalOpen=false" x-cloak style="display:none;">
            <form method="POST" :action="formAction" class="custom-overlay-modal" style="max-width:480px;" :class="{'is-open':modalOpen}">
                @csrf
                <input type="hidden" name="_method" :value="editId ? 'PUT' : 'POST'">
                <div class="custom-overlay-header"><h5 class="fw-bold mb-0" x-text="editId ? 'Edit volume tier' : 'Add volume tier'"></h5><button type="button" class="btn-close" @click="modalOpen=false"></button></div>
                <div class="custom-overlay-body">
                    <label class="form-label fw-bold small text-muted">APPLY WHEN BRANCHES ≥</label>
                    <input type="number" min="1" name="min_quantity" x-model="f.min_quantity" class="form-control bg-white border shadow-sm mb-3" required>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted">TYPE</label>
                            <select name="type" x-model="f.type" class="form-select bg-white border shadow-sm"><option value="percentage">Percentage (%)</option><option value="fixed">Fixed (₹)</option></select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted">VALUE</label>
                            <input type="number" step="0.01" name="value" x-model="f.value" class="form-control bg-white border shadow-sm" required>
                        </div>
                        <div class="col-12" x-show="f.type==='percentage'">
                            <label class="form-label fw-bold small text-muted">MAX ₹ CAP <span class="fw-normal">— optional</span></label>
                            <input type="number" step="0.01" name="max_amount" x-model="f.max_amount" class="form-control bg-white border shadow-sm">
                        </div>
                    </div>
                </div>
                <div class="custom-overlay-footer"><button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="modalOpen=false">Cancel</button><button class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Save</button></div>
            </form>
        </div>
    </template>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('discountRules', () => ({
        modalOpen: false,
        editId: null,
        rulesBase: @json(url('superadmin/discounts/rules')),
        storeUrl: @json(route('superadmin.discounts.rules.store')),
        rules: {
            @foreach($rules as $r)
            {{ $r->id }}: { min_quantity: {{ $r->min_quantity }}, type: @json($r->type->value), value: {{ (float) $r->value }}, max_amount: {{ $r->max_amount !== null ? (float) $r->max_amount : 'null' }} },
            @endforeach
        },
        f: { min_quantity: '', type: 'percentage', value: '', max_amount: '' },
        get formAction() { return this.editId ? (this.rulesBase + '/' + this.editId) : this.storeUrl; },
        openCreate() {
            this.editId = null;
            this.f = { min_quantity: '', type: 'percentage', value: '', max_amount: '' };
            this.modalOpen = true;
        },
        openEdit(id) {
            const rule = this.rules[id];
            if (!rule) return;
            this.editId = id;
            this.f = { min_quantity: rule.min_quantity, type: rule.type, value: rule.value, max_amount: rule.max_amount ?? '' };
            this.modalOpen = true;
        },
    }));
});
</script>
@endpush
