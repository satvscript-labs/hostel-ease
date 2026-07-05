{{-- Reusable "collect payment against an obligation" modal.
     Open with: prepCollect(actionUrl, label, balance) --}}
<div class="modal fade" id="collectModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="collectForm" method="POST">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Collect Payment <small class="text-muted" id="collectLabel"></small></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3">Outstanding balance: <strong id="collectBalance"></strong></div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label">Amount (₹)</label>
                        <input type="number" step="0.01" min="1" name="amount" id="collectAmount" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Date</label>
                        <input type="date" name="paid_on" class="form-control" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Type</label>
                        <select name="payment_type" class="form-select">
                            @foreach(config('hsms.payment_types') as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Mode</label>
                        <select name="mode" id="collectMode" class="form-select">
                            @foreach(\App\Models\PaymentMode::options() as $m)<option value="{{ $m->code }}" data-ref="{{ $m->requires_reference ? 1 : 0 }}">{{ $m->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-12" id="collectRefWrap" style="display:none;">
                        <label class="form-label" id="collectRefLabel">Reference Number</label>
                        <input type="text" name="reference_number" id="collectRef" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <input type="text" name="remarks" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Collect & Receipt</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    // Instantiate the modal lazily (on click) so it doesn't depend on the
    // deferred Vite module having loaded `bootstrap` at parse time.
    function prepCollect(action, label, balance) {
        const form = document.getElementById('collectForm');
        form.action = action;
        document.getElementById('collectLabel').textContent = label;
        document.getElementById('collectBalance').textContent = '₹' + Number(balance).toFixed(2);
        document.getElementById('collectAmount').value = balance > 0 ? balance : '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('collectModal')).show();
    }
    (function () {
        const mode = document.getElementById('collectMode');
        const wrap = document.getElementById('collectRefWrap');
        const ref = document.getElementById('collectRef');
        function sync() {
            const opt = mode.options[mode.selectedIndex];
            const needs = opt && opt.dataset.ref === '1';
            wrap.style.display = needs ? '' : 'none';
            ref.required = needs;
        }
        mode.addEventListener('change', sync); sync();
    })();
</script>
@endpush
