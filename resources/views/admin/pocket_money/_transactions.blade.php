{{-- Wallet transaction fragment — everything inside #pwt-list so pagination
     swaps in place (§4.3). Deleting an entry is audited server-side and gated
     behind the canonical confirm dialog (owner decision, W6.4). --}}
<div class="d-flex flex-column gap-2">
    @forelse($transactions as $tx)
        @php $isDeposit = $tx->type === 'deposit'; @endphp
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="pwt-ic {{ $isDeposit ? 'is-deposit' : 'is-withdraw' }}">
                    <i class="fa-solid {{ $isDeposit ? 'fa-arrow-down' : 'fa-arrow-up' }}"></i>
                </div>
                <div class="flex-grow-1" style="min-width: 0;">
                    <div class="fw-bold text-dark text-truncate">
                        {{ $isDeposit ? __('Deposit') : __('Withdrawal') }}
                        @if($tx->note)<span class="fw-normal text-muted">· {{ $tx->note }}</span>@endif
                    </div>
                    <div class="text-muted small text-truncate">
                        {{ $tx->created_at->format('d M Y, h:i A') }}
                        @if($tx->creator) · {{ $tx->creator->name }}@endif
                    </div>
                </div>
                <div class="pwt-amount {{ $isDeposit ? 'text-success' : 'text-danger' }}">
                    {{ $isDeposit ? '+' : '−' }}{{ hostelease_money($tx->amount) }}
                </div>
                <form action="{{ route('admin.pocket-money.destroy', [$student, $tx]) }}" method="POST" class="m-0"
                      data-confirm="{{ __('Remove this :type of :amount? The wallet balance will change.', ['type' => $isDeposit ? __('deposit') : __('withdrawal'), 'amount' => hostelease_money($tx->amount)]) }}">
                    @csrf @method('DELETE')
                    <button class="he-icon-btn is-danger" title="{{ __('Remove entry') }}" aria-label="{{ __('Remove entry') }}">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
    @empty
        <x-he-empty-state icon="wallet" title="{{ __('No transactions yet') }}" subtitle="{{ __('Deposits and withdrawals for this wallet will appear here.') }}" />
    @endforelse
</div>

@if($transactions->hasPages())
    <div class="mt-4">{{ $transactions->links() }}</div>
@endif
