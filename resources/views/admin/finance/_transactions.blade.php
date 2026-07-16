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

                {{-- ═══ Wide / reflow row (container-tiered, §4.9/§4.10) ═══
                     Same three-zone grammar as the invoice rows: info flexes
                     and truncates (the row reflows before it can crush),
                     the amount is a fixed tabular cell, actions sit behind
                     the hairline. The old Bootstrap columns divided the
                     VIEWPORT's idea of the row and crushed beside the
                     sidebar. --}}
                <div class="he-cq-wide fin-row">
                    <div class="fin-c-info">
                        <div class="avatar bg-success-subtle text-success fw-bold rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                            <i class="fa-solid fa-arrow-down"></i>
                        </div>
                        <div class="fin-c-text">
                            <div class="fin-c-block">
                                <div class="fw-bold text-dark lh-sm text-truncate">{{ $payment->student->name }}</div>
                                <div class="text-muted small lh-sm text-truncate">{{ $payment->receipt_number }}</div>
                            </div>
                            <div class="fin-c-block">
                                <div class="text-dark fw-semibold text-uppercase lh-sm text-truncate">{{ $payment->mode }}</div>
                                <div class="text-muted small lh-sm text-truncate">{{ $payment->paid_on->format('d M Y') }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="fin-row-money">
                        <div class="fin-row-num">
                            <div class="fin-row-lbl">{{ __('Received') }}</div>
                            <div class="fw-bold text-success">+{{ hostelease_money($payment->amount) }}</div>
                            @if((float) $payment->credit_used > 0)
                                <div class="text-muted small text-nowrap">{{ __('+ credit') }} {{ hostelease_money($payment->credit_used) }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="fin-row-acts">
                        <a href="{{ route('admin.payments.pdf', $payment) }}" target="_blank" rel="noopener"
                           class="he-icon-btn" title="{{ __('Download PDF receipt') }}" aria-label="{{ __('Download PDF receipt') }}">
                            <i class="fa-solid fa-file-pdf"></i>
                        </a>
                        <form action="{{ route('admin.payments.whatsapp', $payment) }}" method="POST" target="_blank" class="m-0">
                            @csrf
                            <button class="he-icon-btn is-whatsapp" title="{{ __('Send receipt on WhatsApp') }}" aria-label="{{ __('Send receipt on WhatsApp') }}">
                                <i class="fa-brands fa-whatsapp"></i>
                            </button>
                        </form>
                        <button type="button" class="he-icon-btn" title="{{ __('Email receipt') }}" aria-label="{{ __('Email receipt') }}"
                                @click="openEmail({{ $emailPayload }})">
                            <i class="fa-solid fa-envelope"></i>
                        </button>
                        <form action="{{ route('admin.payments.destroy', $payment) }}" method="POST" class="m-0 ms-1"
                              data-confirm="{{ __('Reverse this payment? Invoice balances will be restored.') }}">
                            @csrf @method('DELETE')
                            <button class="he-icon-btn is-danger" title="{{ __('Reverse transaction') }}" aria-label="{{ __('Reverse transaction') }}">
                                <i class="fa-solid fa-rotate-left"></i>
                            </button>
                        </form>
                    </div>
                </div>

                {{-- ═══ Bespoke phone card (container <640px, §4.9) ═══ --}}
                <div class="he-cq-card">
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
                    <div class="he-act-row mt-2">
                        <a href="{{ route('admin.payments.pdf', $payment) }}" target="_blank" rel="noopener"
                           class="he-icon-btn he-icon-btn--lg" title="{{ __('Download PDF receipt') }}" aria-label="{{ __('Download PDF receipt') }}">
                            <i class="fa-solid fa-file-pdf"></i>
                        </a>
                        <form action="{{ route('admin.payments.whatsapp', $payment) }}" method="POST" target="_blank" class="m-0">
                            @csrf
                            <button class="he-icon-btn he-icon-btn--lg is-whatsapp" title="{{ __('Send receipt on WhatsApp') }}" aria-label="{{ __('Send receipt on WhatsApp') }}">
                                <i class="fa-brands fa-whatsapp"></i>
                            </button>
                        </form>
                        <button type="button" class="he-icon-btn he-icon-btn--lg" title="{{ __('Email receipt') }}" aria-label="{{ __('Email receipt') }}"
                                @click="openEmail({{ $emailPayload }})">
                            <i class="fa-solid fa-envelope"></i>
                        </button>

                        <div class="he-act-right">
                            <form action="{{ route('admin.payments.destroy', $payment) }}" method="POST" class="m-0"
                                  data-confirm="{{ __('Reverse this payment? Invoice balances will be restored.') }}">
                                @csrf @method('DELETE')
                                <button class="he-icon-btn he-icon-btn--lg is-danger" title="{{ __('Reverse transaction') }}" aria-label="{{ __('Reverse transaction') }}">
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
