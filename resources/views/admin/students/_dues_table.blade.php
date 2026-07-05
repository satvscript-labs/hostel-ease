{{-- Mobile-friendly dues display. Expects $rows (collection of due arrays). --}}
@forelse($rows as $d)
    @php $badge = $d['status'] === 'paid' ? 'success' : ($d['status'] === 'partial' ? 'warning' : 'danger'); @endphp
    <div class="due-card">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
                <div class="dc-kind text-{{ $badge }}">
                    {{ $d['kind'] }} · {{ $d['label'] }}
                </div>
                <div class="d-flex gap-3 mt-1 flex-wrap" style="font-size: var(--he-text-sm);">
                    <span class="text-muted">Billed: <span class="fw-semibold text-body">{{ hostelease_money($d['total']) }}</span></span>
                    <span class="text-success">Paid: <span class="fw-semibold">{{ hostelease_money($d['paid']) }}</span></span>
                </div>
            </div>
            <div class="text-end flex-shrink-0">
                <div class="dc-amount {{ $d['balance'] > 0 ? 'text-danger' : 'text-success' }}">
                    {{ hostelease_money($d['balance']) }}
                </div>
                <span class="badge-premium bg-{{ $badge }}-subtle text-{{ $badge }}">{{ ucfirst($d['status']) }}</span>
            </div>
        </div>
        @if(!empty($d['due_date']))
            <div class="mt-1" style="font-size: var(--he-text-xs); color: var(--he-text-muted);">
                <i class="fa-regular fa-calendar me-1"></i>Due: {{ \Carbon\Carbon::parse($d['due_date'])->format('d M Y') }}
            </div>
        @endif
    </div>
@empty
    <div class="empty-state py-4">
        <i class="fa-solid fa-receipt d-block"></i>
        <p>No dues recorded yet.</p>
    </div>
@endforelse
