{{-- Transaction list fragment — everything inside #transaction-list (§4.3).

     W6.1 surfaces the receipt actions (routes existed since day one, no UI):
       · PDF      — plain GET link, opens the download in a new tab
       · WhatsApp — POST (controller redirects to wa.me), so a form with
                    target="_blank" keeps this page alive
       · Email    — needs an address, so it opens the small email sheet
     Reverse-payment keeps its destructive styling + data-confirm (rule 6a). --}}
@php $isFiltered = filled($search); @endphp

<div class="d-flex flex-column gap-3 stagger">
    @forelse($payments as $payment)
        @php
            $emailPayload = \Illuminate\Support\Js::from([
                'action' => route('admin.payments.email', $payment),
                'receipt' => $payment->receipt_number,
                'student' => $payment->student->name,
            ]);
        @endphp
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-3 p-lg-4">

                {{-- ═══ Desktop / tablet-landscape row ═══ --}}
                <div class="d-none d-lg-flex row align-items-center m-0 w-100">
                    <div class="col-lg-4 d-flex align-items-center gap-3 p-0">
                        <div class="avatar bg-success-subtle text-success fw-bold rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                            <i class="fa-solid fa-arrow-down"></i>
                        </div>
                        <div style="min-width: 0;">
                            <div class="fw-bold text-dark lh-1 mb-1 text-truncate">{{ $payment->student->name }}</div>
                            <div class="text-muted small letter-spacing-1 lh-1">{{ __('Receipt') }}: {{ $payment->receipt_number }}</div>
                        </div>
                    </div>

                    <div class="col-lg-2 p-0">
                        <div class="text-dark fw-bold text-uppercase lh-1 mb-1">{{ $payment->mode }}</div>
                        <div class="text-muted small letter-spacing-1 lh-1">{{ $payment->paid_on->format('d M Y') }}</div>
                    </div>

                    <div class="col-lg-3 text-end p-0 pe-3" style="font-feature-settings: 'tnum';">
                        <div class="text-success fw-bold h5 mb-0">+{{ hostelease_money($payment->amount) }}</div>
                        @if((float) $payment->credit_used > 0)
                            <div class="text-muted small">{{ __('+ credit') }} {{ hostelease_money($payment->credit_used) }}</div>
                        @endif
                    </div>

                    <div class="col-lg-3 d-flex align-items-center justify-content-end gap-2 p-0">
                        <a href="{{ route('admin.payments.pdf', $payment) }}" target="_blank" rel="noopener"
                           class="btn btn-sm btn-light rounded-circle border shadow-sm text-secondary" style="width: 36px; height: 36px;" title="{{ __('Download PDF receipt') }}">
                            <i class="fa-solid fa-file-pdf"></i>
                        </a>
                        <form action="{{ route('admin.payments.whatsapp', $payment) }}" method="POST" target="_blank" class="m-0">
                            @csrf
                            <button class="btn btn-sm btn-light rounded-circle border shadow-sm text-success" style="width: 36px; height: 36px;" title="{{ __('Send receipt on WhatsApp') }}">
                                <i class="fa-brands fa-whatsapp"></i>
                            </button>
                        </form>
                        <button type="button" class="btn btn-sm btn-light rounded-circle border shadow-sm text-primary" style="width: 36px; height: 36px;"
                                title="{{ __('Email receipt') }}" @click="openEmail({{ $emailPayload }})">
                            <i class="fa-solid fa-envelope"></i>
                        </button>
                        <form action="{{ route('admin.payments.destroy', $payment) }}" method="POST" class="m-0 ms-1"
                              data-confirm="{{ __('Reverse this payment? Invoice balances will be restored.') }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-light rounded-circle text-danger shadow-sm" style="width: 36px; height: 36px;" title="{{ __('Reverse transaction') }}">
                                <i class="fa-solid fa-rotate-left"></i>
                            </button>
                        </form>
                    </div>
                </div>

                {{-- ═══ Bespoke phone card ═══ --}}
                <div class="d-lg-none">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <div class="avatar bg-success-subtle text-success fw-bold rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                            <i class="fa-solid fa-arrow-down"></i>
                        </div>
                        <div class="flex-grow-1" style="min-width: 0;">
                            <div class="fw-bold text-dark text-truncate">{{ $payment->student->name }}</div>
                            <div class="text-muted small text-truncate">{{ $payment->receipt_number }}</div>
                        </div>
                        <div class="text-end flex-shrink-0" style="font-feature-settings: 'tnum';">
                            <div class="text-success fw-bold">+{{ hostelease_money($payment->amount) }}</div>
                            <div class="text-muted small text-uppercase">{{ $payment->mode }} · {{ $payment->paid_on->format('d M') }}</div>
                        </div>
                    </div>

                    {{-- Receipt actions: icon-only, thumb-sized (44px). The
                         labels were doing no work — a PDF glyph, the WhatsApp
                         mark and an envelope are unmistakable, and spelling
                         them out was eating the row. The three SEND actions sit
                         together on the left; reverse is destructive and
                         unrelated, so it keeps its distance on the right. --}}
                    <div class="fin-act-row mt-2">
                        <a href="{{ route('admin.payments.pdf', $payment) }}" target="_blank" rel="noopener"
                           class="fin-icon-btn fin-icon-btn--lg" title="{{ __('Download PDF receipt') }}" aria-label="{{ __('Download PDF receipt') }}">
                            <i class="fa-solid fa-file-pdf"></i>
                        </a>
                        <form action="{{ route('admin.payments.whatsapp', $payment) }}" method="POST" target="_blank" class="m-0">
                            @csrf
                            <button class="fin-icon-btn fin-icon-btn--lg is-whatsapp" title="{{ __('Send receipt on WhatsApp') }}" aria-label="{{ __('Send receipt on WhatsApp') }}">
                                <i class="fa-brands fa-whatsapp"></i>
                            </button>
                        </form>
                        <button type="button" class="fin-icon-btn fin-icon-btn--lg" title="{{ __('Email receipt') }}" aria-label="{{ __('Email receipt') }}"
                                @click="openEmail({{ $emailPayload }})">
                            <i class="fa-solid fa-envelope"></i>
                        </button>

                        <div class="fin-act-right">
                            <form action="{{ route('admin.payments.destroy', $payment) }}" method="POST" class="m-0"
                                  data-confirm="{{ __('Reverse this payment? Invoice balances will be restored.') }}">
                                @csrf @method('DELETE')
                                <button class="fin-icon-btn fin-icon-btn--lg is-danger" title="{{ __('Reverse transaction') }}" aria-label="{{ __('Reverse transaction') }}">
                                    <i class="fa-solid fa-rotate-left"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    @empty
        @if($isFiltered)
            <x-he-empty-state icon="magnifying-glass" title="{{ __('No matches') }}" subtitle="{{ __('No transactions match your search.') }}" />
        @else
            <x-he-empty-state icon="money-bill-transfer" title="{{ __('No transactions found') }}" subtitle="{{ __('No payments have been recorded yet.') }}" />
        @endif
    @endforelse
</div>

@if($payments->hasPages())
    <div class="mt-4">{{ $payments->links() }}</div>
@endif
