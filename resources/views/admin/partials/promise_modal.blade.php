{{-- Reusable "promise to pay" modal. Open with:
     prepPromise(actionUrl, label, currentDate, currentNote) --}}
<div class="modal fade" id="promiseModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="promiseForm" method="POST">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">Promise to Pay <small class="text-muted" id="promiseLabel"></small></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">Set the date the student promised to pay. You'll get a reminder
                    on the dashboard when it arrives.</p>
                <div class="mb-3">
                    <label class="form-label">Promise Date</label>
                    <input type="date" name="promise_date" id="promiseDate" class="form-control" min="{{ now()->toDateString() }}">
                </div>
                <div class="mb-1">
                    <label class="form-label">Note (optional)</label>
                    <input type="text" name="promise_note" id="promiseNote" class="form-control" placeholder="e.g. will pay after salary">
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-danger" onclick="clearPromise()"><i class="fa-solid fa-xmark me-1"></i>Clear</button>
                <div>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function prepPromise(action, label, date, note) {
        const f = document.getElementById('promiseForm');
        f.action = action;
        document.getElementById('promiseLabel').textContent = label;
        document.getElementById('promiseDate').value = date || '';
        document.getElementById('promiseNote').value = note || '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('promiseModal')).show();
    }
    function clearPromise() {
        document.getElementById('promiseDate').value = '';
        document.getElementById('promiseNote').value = '';
        document.getElementById('promiseForm').submit();
    }
</script>
@endpush
