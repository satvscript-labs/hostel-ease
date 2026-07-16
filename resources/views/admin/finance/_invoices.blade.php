{{-- Invoice list fragment — everything inside #invoice-list, so the filter
     form and pagination swap it wholesale (design law §4.3). Server-rendered:
     search/status/paging all happen in FinanceController, not the browser.

     Each row renders TWO layouts: a desktop grid (d-none d-lg-block) and a
     bespoke phone card (d-lg-none) — mobile rule 1: re-arranged, not shrunk.
     The three money values stack as label/value rows on phones (rule 3). --}}
@php $isFiltered = filled($search) || filled($status); @endphp

<div class="d-flex flex-column gap-3 stagger">
    @forelse($invoices as $invoice)
        @php
            $collectPayload = \Illuminate\Support\Js::from([
                'action' => route('admin.students.collect', $invoice->student),
                'student' => $invoice->student->name,
                'invoice' => $invoice->title,
                'balance' => (float) $invoice->balance,
                'credit' => (float) $invoice->student->credit_balance,
            ]);
            $editPayload = \Illuminate\Support\Js::from([
                'action' => route('admin.invoices.update', $invoice),
                'title' => $invoice->title,
                'amount' => (float) $invoice->amount,
                'due' => optional($invoice->due_date)->format('Y-m-d'),
                'paid' => (float) $invoice->paid_amount,
                'student' => $invoice->student->name,
            ]);
            $statusBadge = match ($invoice->status) {
                'paid' => ['success', __('Paid')],
                'partial' => ['warning', __('Partial')],
                default => ['danger', __('Pending')],
            };
        @endphp
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-3 p-lg-4">

                {{-- ═══ Wide / reflow row (container-tiered, §4.9/§4.10) ═══
                     Three zones: info (flexible, truncates — but the ROW
                     reflows to two lines before info can crush), money (fixed
                     tabular cells so figures align down the list), actions.
                     Every text line that can truncate carries text-truncate —
                     the per-character phone-number wrap was a missing nowrap. --}}
                <div class="he-cq-wide fin-row">
                    <div class="fin-c-info">
                        <div class="avatar bg-light text-primary fw-bold rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                            {{ substr($invoice->student->name, 0, 1) }}
                        </div>
                        <div class="fin-c-text">
                            <div class="fin-c-block">
                                <div class="fw-bold text-dark lh-sm text-truncate">{{ $invoice->student->name }}</div>
                                <div class="text-muted small lh-sm text-truncate">{{ hostelease_phone($invoice->student->mobile) }}</div>
                            </div>
                            <div class="fin-c-block">
                                <div class="text-dark fw-semibold lh-sm text-truncate">{{ $invoice->title }}</div>
                                <div class="text-muted small text-uppercase lh-sm text-truncate" style="letter-spacing: 0.04em;">{{ $invoice->type }} &bull; {{ $invoice->created_at->format('d M Y') }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="fin-row-money">
                        <div class="fin-row-num">
                            <div class="fin-row-lbl">{{ __('Amount') }}</div>
                            <div class="fw-bold text-dark">{{ hostelease_money($invoice->amount) }}</div>
                        </div>
                        <div class="fin-row-num">
                            <div class="fin-row-lbl">{{ __('Paid') }}</div>
                            <div class="fw-bold text-success">{{ hostelease_money($invoice->paid_amount) }}</div>
                        </div>
                        <div class="fin-row-num">
                            <div class="fin-row-lbl">{{ __('Balance') }}</div>
                            <div class="fw-bold {{ $invoice->balance > 0 ? 'text-danger' : 'text-muted' }}">{{ hostelease_money($invoice->balance) }}</div>
                        </div>
                    </div>

                    <div class="fin-row-acts">
                        <span class="badge bg-{{ $statusBadge[0] }}-subtle text-{{ $statusBadge[0] }} rounded-pill px-3 py-2">{{ $statusBadge[1] }}</span>

                        @if($invoice->balance > 0)
                            <button type="button" class="btn btn-sm btn-success rounded-pill fw-bold px-3 text-nowrap"
                                    style="min-height: 36px;" @click="openCollect({{ $collectPayload }})">
                                <i class="fa-solid fa-indian-rupee-sign me-1"></i>{{ __('Collect') }}
                            </button>
                        @endif

                        <button type="button" class="he-icon-btn" title="{{ __('Edit invoice') }}" @click="openEdit({{ $editPayload }})">
                            <i class="fa-solid fa-pen"></i>
                        </button>

                        <form action="{{ route('admin.invoices.destroy', $invoice) }}" method="POST" class="m-0"
                              data-confirm="{{ __('Delete this invoice? This cannot be undone.') }}">
                            @csrf @method('DELETE')
                            <button class="he-icon-btn is-danger" title="{{ __('Delete') }}">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>

                {{-- ═══ Bespoke phone card (container <640px, §4.9) ═══ --}}
                <div class="he-cq-card">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="avatar bg-light text-primary fw-bold rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                            {{ substr($invoice->student->name, 0, 1) }}
                        </div>
                        <div class="flex-grow-1" style="min-width: 0;">
                            <div class="fw-bold text-dark text-truncate">{{ $invoice->student->name }}</div>
                            <div class="text-muted small text-truncate">{{ $invoice->title }} · {{ $invoice->created_at->format('d M') }}</div>
                        </div>
                        <span class="badge bg-{{ $statusBadge[0] }}-subtle text-{{ $statusBadge[0] }} rounded-pill px-2 py-1 flex-shrink-0">{{ $statusBadge[1] }}</span>
                    </div>

                    {{-- Money: one value per row, full width (mobile rule 3). --}}
                    <div class="fin-money-list mb-2">
                        <div class="fin-money-row">
                            <span class="fin-money-lbl">{{ __('Amount') }}</span>
                            <span class="fw-bold">{{ hostelease_money($invoice->amount) }}</span>
                        </div>
                        <div class="fin-money-row">
                            <span class="fin-money-lbl">{{ __('Paid') }}</span>
                            <span class="fw-bold text-success">{{ hostelease_money($invoice->paid_amount) }}</span>
                        </div>
                        <div class="fin-money-row">
                            <span class="fin-money-lbl">{{ __('Balance') }}</span>
                            <span class="fw-bold {{ $invoice->balance > 0 ? 'text-danger' : 'text-muted' }}">{{ hostelease_money($invoice->balance) }}</span>
                        </div>
                    </div>

                    {{-- Collect left (capped, never full-bleed), edit + delete
                         right. When the balance makes the label too long for one
                         line, data-fit-label drops the amount rather than
                         wrapping it — "₹ Collect ₹1,25,000.00" becomes
                         "Collect" (§4.8). A paid invoice renders NO left button
                         at all, rather than an empty pill holding the space. --}}
                    <div class="he-act-row">
                        @if($invoice->balance > 0)
                            <button type="button" class="btn btn-success rounded-pill fw-bold fin-collect" data-fit-label
                                    @click="openCollect({{ $collectPayload }})">
                                <i class="fa-solid fa-indian-rupee-sign"></i>
                                <span>{{ __('Collect') }}</span>
                                <span class="fin-collect-amt">{{ hostelease_money($invoice->balance) }}</span>
                            </button>
                        @endif
                        <div class="he-act-right">
                            <button type="button" class="he-icon-btn he-icon-btn--lg"
                                    title="{{ __('Edit invoice') }}" aria-label="{{ __('Edit invoice') }}" @click="openEdit({{ $editPayload }})">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <form action="{{ route('admin.invoices.destroy', $invoice) }}" method="POST" class="m-0"
                                  data-confirm="{{ __('Delete this invoice? This cannot be undone.') }}">
                                @csrf @method('DELETE')
                                <button class="he-icon-btn he-icon-btn--lg is-danger" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    @empty
        @if($isFiltered)
            <x-he-empty-state icon="magnifying-glass" title="{{ __('No matches') }}" subtitle="{{ __('No invoices match your search or filter.') }}" />
        @else
            <x-he-empty-state icon="file-invoice" title="{{ __('No invoices found') }}" subtitle="{{ __('Create a new invoice to get started.') }}" />
        @endif
    @endforelse
</div>

@if($invoices->hasPages())
    <div class="mt-4">{{ $invoices->links() }}</div>
@endif
