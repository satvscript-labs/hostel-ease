{{-- Expense list fragment — everything inside #expense-list, so the filter
     form, the category chips and pagination swap it wholesale (§4.3).
     Server-rendered: search/category/date-window/paging all happen in
     ExpenseController — the old page filtered client-side over an unbounded
     ->get(), which broke the moment the list outgrew one screen.

     Inherits from index.blade.php's scope: $catIcons (category → fa icon) and
     $modeNames (payment-mode code → display name).

     Each row renders TWO layouts (mobile rule 1: re-arranged, not shrunk):
     a desktop grid with fixed money/date tracks so values align down the
     whole list, and a bespoke phone card.

     Salary mirrors (staff_salary_payment_id set) are READ-ONLY here: they
     reflect a salary payment recorded on the Staff page, so their action is
     a link to that staff member — not edit/delete buttons that would desync
     the pair. --}}
@php $isFiltered = filled($search) || filled($category); @endphp

<div class="d-flex flex-column gap-3 stagger">
    @forelse($expenses as $expense)
        @php
            $icon = $catIcons[$expense->category] ?? 'receipt';
            $catLabel = config('hostelease.expense_categories.'.$expense->category, ucfirst($expense->category));
            $modeLabel = $modeNames[$expense->mode] ?? ucfirst($expense->mode);
            $salaryStaff = $expense->isSalaryLinked() ? $expense->salaryPayment?->staff : null;
            $editPayload = $expense->isSalaryLinked() ? null : \Illuminate\Support\Js::from([
                'action' => route('admin.expenses.update', $expense),
                'category' => $expense->category,
                'title' => $expense->title,
                'amount' => (float) $expense->amount,
                'date' => $expense->expense_date->format('Y-m-d'),
                'paid_to' => (string) $expense->paid_to,
                'mode' => $expense->mode,
                'reference' => (string) $expense->reference_number,
                'notes' => (string) $expense->notes,
            ]);
        @endphp
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-3 p-lg-4">

                {{-- ═══ Wide / reflow row (container-tiered, §4.9/§4.10) ═══
                     Info flexes with a floor; the meta cells are grid columns
                     when wide (display:contents) and one flex line when the
                     row reflows; the amount never wraps. --}}
                <div class="he-cq-wide exp-row">
                    <div class="exp-c-info">
                        <div class="exp-cat-avatar flex-shrink-0"><i class="fa-solid fa-{{ $icon }}"></i></div>
                        <div style="min-width: 0;">
                            <div class="fw-bold text-dark lh-sm text-truncate">{{ $expense->title }}</div>
                            <div class="text-muted small lh-sm text-truncate">
                                @if($expense->paid_to){{ $expense->paid_to }}@endif
                                @if($expense->paid_to && $expense->reference_number) &middot; @endif
                                @if($expense->reference_number)#{{ $expense->reference_number }}@endif
                                @if(!$expense->paid_to && !$expense->reference_number)<span class="opacity-50">—</span>@endif
                            </div>
                        </div>
                    </div>

                    <div class="exp-row-meta">
                        <div class="exp-cell-date">
                            <div class="exp-row-lbl">{{ __('Date') }}</div>
                            <div class="fw-semibold text-dark small text-nowrap">{{ $expense->expense_date->format('d M Y') }}</div>
                        </div>

                        <div class="exp-cell-mode" style="min-width: 0;">
                            <div class="exp-row-lbl">{{ __('Mode') }}</div>
                            <div class="fw-semibold text-dark small text-truncate">{{ $modeLabel }}</div>
                        </div>

                        <div class="exp-cell-cat text-nowrap">
                            <span class="badge bg-light text-secondary border rounded-pill px-3 py-2">
                                <i class="fa-solid fa-{{ $icon }} me-1 opacity-75"></i>{{ $catLabel }}
                            </span>
                            @if($expense->isSalaryLinked())
                                <span class="exp-auto-chip" title="{{ __('Mirrors a staff salary payment') }}">
                                    <i class="fa-solid fa-rotate"></i>{{ __('Auto') }}
                                </span>
                            @endif
                        </div>

                        <div class="exp-row-num">
                            <div class="exp-row-lbl">{{ __('Amount') }}</div>
                            <div class="fw-bold text-danger">&minus;{{ hostelease_money($expense->amount) }}</div>
                        </div>
                    </div>

                    <div class="exp-row-acts">
                        @if($expense->isSalaryLinked())
                            @if($salaryStaff)
                                <a href="{{ route('admin.staff.show', $salaryStaff) }}" class="he-icon-btn"
                                   title="{{ __('Manage from the staff page') }}"><i class="fa-solid fa-user-tie"></i></a>
                            @else
                                <span class="he-icon-btn opacity-50" title="{{ __('Salary mirror — staff record removed') }}"><i class="fa-solid fa-user-tie"></i></span>
                            @endif
                        @else
                            <button type="button" class="he-icon-btn" title="{{ __('Edit expense') }}" @click="openEdit({{ $editPayload }})">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <form action="{{ route('admin.expenses.destroy', $expense) }}" method="POST" class="m-0"
                                  data-confirm="{{ __('Delete this expense? This cannot be undone.') }}">
                                @csrf @method('DELETE')
                                <button class="he-icon-btn is-danger" title="{{ __('Delete') }}"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- ═══ Bespoke phone card (container <640px, §4.9) ═══ --}}
                <div class="he-cq-card">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="exp-cat-avatar exp-cat-avatar--sm flex-shrink-0"><i class="fa-solid fa-{{ $icon }}"></i></div>
                        <div class="flex-grow-1" style="min-width: 0;">
                            <div class="fw-bold text-dark text-truncate">{{ $expense->title }}</div>
                            <div class="text-muted small text-truncate">
                                {{ $expense->expense_date->format('d M') }} &middot; {{ $modeLabel }}
                                @if($expense->paid_to) &middot; {{ $expense->paid_to }}@endif
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0" style="font-feature-settings: 'tnum';">
                            <div class="fw-bold text-danger">&minus;{{ hostelease_money($expense->amount) }}</div>
                        </div>
                    </div>

                    <div class="he-act-row">
                        <span class="badge bg-light text-secondary border rounded-pill px-3 py-2">
                            <i class="fa-solid fa-{{ $icon }} me-1 opacity-75"></i>{{ $catLabel }}
                        </span>
                        @if($expense->isSalaryLinked())
                            <span class="exp-auto-chip"><i class="fa-solid fa-rotate"></i>{{ __('Auto') }}</span>
                            <div class="he-act-right">
                                @if($salaryStaff)
                                    <a href="{{ route('admin.staff.show', $salaryStaff) }}" class="he-icon-btn he-icon-btn--lg"
                                       title="{{ __('Manage from the staff page') }}" aria-label="{{ __('Manage from the staff page') }}">
                                        <i class="fa-solid fa-user-tie"></i>
                                    </a>
                                @endif
                            </div>
                        @else
                            <div class="he-act-right">
                                <button type="button" class="he-icon-btn he-icon-btn--lg" title="{{ __('Edit expense') }}"
                                        aria-label="{{ __('Edit expense') }}" @click="openEdit({{ $editPayload }})">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form action="{{ route('admin.expenses.destroy', $expense) }}" method="POST" class="m-0"
                                      data-confirm="{{ __('Delete this expense? This cannot be undone.') }}">
                                    @csrf @method('DELETE')
                                    <button class="he-icon-btn he-icon-btn--lg is-danger" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}">
                                        <i class="fa-solid fa-trash"></i>
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
            <x-he-empty-state icon="magnifying-glass" title="{{ __('No matches') }}" subtitle="{{ __('No expenses match your search or filter in this period.') }}" />
        @else
            <x-he-empty-state icon="money-bill-trend-up" title="{{ __('No expenses logged') }}" subtitle="{{ __('Expenses for the selected period will appear here.') }}" />
        @endif
    @endforelse
</div>

@if($expenses->hasPages())
    <div class="mt-4">{{ $expenses->links() }}</div>
@endif
