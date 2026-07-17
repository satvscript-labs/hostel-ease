{{-- Deposit list fragment — everything inside #sd-list so the filter form and
     pagination swap it wholesale (§4.3). Server-rendered: search/status/paging
     happen in SecurityDepositController — the old page loaded every deposit
     ever, with two invoice queries per row.

     Inherits from index.blade.php's scope: $pendingInvoices (student_id →
     unpaid invoices, ONE query per page).

     Rows are container-tiered (§4.9/4.10): one-line grid ≥880px, two-line
     reflow ≥640px, bespoke phone card below. --}}
@php $isFiltered = filled($search) || filled($status); @endphp

<div class="d-flex flex-column gap-3 stagger">
    @forelse($deposits as $deposit)
        @php
            $student = $deposit->student;
            $left = $student->status !== 'active';
            $held = $deposit->status === 'collected';
            $dues = $pendingInvoices->get($student->id, collect());
            $refundPayload = $held ? \Illuminate\Support\Js::from([
                'action' => route('admin.security-deposits.refund', $deposit),
                'student' => $student->name,
                'receipt' => $deposit->receipt_number,
                'amount' => (float) $deposit->amount,
                'invoices' => $dues->map(fn ($i) => ['id' => $i->id, 'title' => $i->title, 'balance' => (float) $i->balance])->values(),
            ]) : null;
            $editPayload = $held ? \Illuminate\Support\Js::from([
                'action' => route('admin.security-deposits.update', $deposit),
                'student' => $student->name,
                'amount' => (float) $deposit->amount,
                'mode_id' => $deposit->payment_mode_id,
                'collected_on' => optional($deposit->collected_on)->format('Y-m-d'),
            ]) : null;
        @endphp
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-3 p-lg-4">

                {{-- ═══ Wide / reflow row ═══ --}}
                <div class="he-cq-wide sd-row">
                    <div class="sd-c-info">
                        <div class="avatar bg-light text-primary fw-bold rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                            {{ substr($student->name, 0, 1) }}
                        </div>
                        <div class="sd-c-text">
                            <div class="sd-c-block">
                                <div class="d-block text-truncate">
                                    <a href="{{ route('admin.students.show', $student) }}" class="fw-bold text-dark text-decoration-none">{{ $student->name }}</a>
                                    @if($left)<span class="sd-left-chip"><i class="fa-solid fa-person-walking-arrow-right"></i>{{ __('Left') }}</span>@endif
                                </div>
                                <div class="text-muted small lh-sm text-truncate">{{ $deposit->receipt_number }}</div>
                            </div>
                            <div class="sd-c-block">
                                <div class="fw-semibold text-dark small lh-sm text-truncate">{{ $deposit->paymentMode?->name ?? '—' }}</div>
                                <div class="text-muted small lh-sm text-truncate">{{ __('Collected') }} {{ $deposit->collected_on->format('d M Y') }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="sd-row-money">
                        <div class="sd-row-num">
                            <div class="sd-row-lbl">{{ __('Deposit') }}</div>
                            <div class="fw-bold text-dark">{{ hostelease_money($deposit->amount) }}</div>
                        </div>
                        @if(! $held)
                            <div class="sd-row-num">
                                <div class="sd-row-lbl">{{ __('Refunded') }}</div>
                                <div class="fw-bold text-success">{{ hostelease_money($deposit->refunded_amount) }}</div>
                            </div>
                            <div class="sd-row-num">
                                <div class="sd-row-lbl">{{ __('Deducted') }}</div>
                                <div class="fw-bold {{ (float) $deposit->deducted_amount > 0 ? 'text-danger' : 'text-muted' }}">{{ hostelease_money($deposit->deducted_amount) }}</div>
                            </div>
                        @endif
                    </div>

                    <div class="sd-row-acts">
                        @if($held)
                            <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2">{{ __('Held') }}</span>
                            <button type="button" class="btn btn-sm btn-success rounded-pill fw-bold px-3 text-nowrap" style="min-height: 36px;"
                                    @click="openRefund({{ $refundPayload }})">
                                <i class="fa-solid fa-hand-holding-dollar me-1"></i>{{ __('Settle') }}
                            </button>
                            <button type="button" class="he-icon-btn" title="{{ __('Edit deposit') }}" @click="openEdit({{ $editPayload }})">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-2">{{ __('Refunded') }} {{ optional($deposit->refunded_on)->format('d M') }}</span>
                            <form action="{{ route('admin.security-deposits.revert-refund', $deposit) }}" method="POST" class="m-0"
                                  data-confirm="{{ __('Revert this refund? Deducted dues will be reinstated and the deposit held again.') }}">
                                @csrf
                                <button class="he-icon-btn is-danger" title="{{ __('Revert refund') }}"><i class="fa-solid fa-rotate-left"></i></button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- ═══ Bespoke phone card ═══ --}}
                <div class="he-cq-card">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="avatar bg-light text-primary fw-bold rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                            {{ substr($student->name, 0, 1) }}
                        </div>
                        <div class="flex-grow-1" style="min-width: 0;">
                            <div class="text-truncate">
                                <a href="{{ route('admin.students.show', $student) }}" class="fw-bold text-dark text-decoration-none">{{ $student->name }}</a>
                                @if($left)<span class="sd-left-chip"><i class="fa-solid fa-person-walking-arrow-right"></i>{{ __('Left') }}</span>@endif
                            </div>
                            <div class="text-muted small text-truncate">{{ $deposit->receipt_number }} · {{ $deposit->collected_on->format('d M') }}</div>
                        </div>
                        <span class="badge {{ $held ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} rounded-pill px-2 py-1 flex-shrink-0">
                            {{ $held ? __('Held') : __('Refunded') }}
                        </span>
                    </div>

                    <div class="fin-money-list mb-2">
                        <div class="fin-money-row">
                            <span class="fin-money-lbl">{{ __('Deposit') }}</span>
                            <span class="fw-bold">{{ hostelease_money($deposit->amount) }}</span>
                        </div>
                        @if(! $held)
                            <div class="fin-money-row">
                                <span class="fin-money-lbl">{{ __('Refunded') }}</span>
                                <span class="fw-bold text-success">{{ hostelease_money($deposit->refunded_amount) }}</span>
                            </div>
                            <div class="fin-money-row">
                                <span class="fin-money-lbl">{{ __('Deducted') }}</span>
                                <span class="fw-bold {{ (float) $deposit->deducted_amount > 0 ? 'text-danger' : 'text-muted' }}">{{ hostelease_money($deposit->deducted_amount) }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="he-act-row">
                        @if($held)
                            <button type="button" class="btn btn-success rounded-pill fw-bold px-4" style="min-height: 44px;"
                                    @click="openRefund({{ $refundPayload }})">
                                <i class="fa-solid fa-hand-holding-dollar me-1"></i>{{ __('Settle') }}
                            </button>
                            <div class="he-act-right">
                                <button type="button" class="he-icon-btn he-icon-btn--lg" title="{{ __('Edit deposit') }}" aria-label="{{ __('Edit deposit') }}" @click="openEdit({{ $editPayload }})">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                            </div>
                        @else
                            <div class="he-act-right">
                                <form action="{{ route('admin.security-deposits.revert-refund', $deposit) }}" method="POST" class="m-0"
                                      data-confirm="{{ __('Revert this refund? Deducted dues will be reinstated and the deposit held again.') }}">
                                    @csrf
                                    <button class="he-icon-btn he-icon-btn--lg is-danger" title="{{ __('Revert refund') }}" aria-label="{{ __('Revert refund') }}">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    @empty
        @if($isFiltered)
            <x-he-empty-state icon="magnifying-glass" title="{{ __('No matches') }}" subtitle="{{ __('No deposits match your search or filter.') }}" />
        @else
            <x-he-empty-state icon="shield-halved" title="{{ __('No deposits yet') }}" subtitle="{{ __('Record a security deposit to get started.') }}" />
        @endif
    @endforelse
</div>

@if($deposits->hasPages())
    <div class="mt-4">{{ $deposits->links() }}</div>
@endif
