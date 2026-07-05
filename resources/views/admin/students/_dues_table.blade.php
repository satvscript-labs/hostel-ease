{{-- Display-only dues table. Expects $rows (collection of due arrays). --}}
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead>
            <tr><th>Type</th><th>Period</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Status</th></tr>
        </thead>
        <tbody>
        @forelse($rows as $d)
            @php $badge = $d['status'] === 'paid' ? 'success' : ($d['status'] === 'partial' ? 'warning' : 'danger'); @endphp
            <tr>
                <td><span class="badge bg-primary-subtle text-primary">{{ $d['kind'] }}</span></td>
                <td>{{ $d['label'] }}</td>
                <td class="text-end">{{ hsms_money($d['total']) }}</td>
                <td class="text-end text-success">{{ hsms_money($d['paid']) }}</td>
                <td class="text-end fw-semibold {{ $d['balance'] > 0 ? 'text-danger' : '' }}">{{ hsms_money($d['balance']) }}</td>
                <td><span class="badge bg-{{ $badge }}-subtle text-{{ $badge }}">{{ ucfirst($d['status']) }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-3">None.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
