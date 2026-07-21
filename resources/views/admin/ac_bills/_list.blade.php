{{-- AC bill list fragment — everything inside #ac-list, swapped wholesale by
     the month/floor filters and pagination (§4.3).

     Every row is EXPANDABLE (W6.3): collapsed it still tells the story — room,
     month, units·rate, amount, collected progress, occupants, status; expanded
     it retells the day-ledger split from the PERSISTED breakdown (who, which
     days, what share, what's paid), with profile links. Bills generated before
     the breakdown existed fall back to their invoices (share/paid, no days).

     Container-tiered per §4.9/§4.10: one-line collapsed row when wide, the
     info/money reflow in the middle band, a bespoke phone card below 640px.
     The expanded panel is shared by all tiers. --}}
@php $isFiltered = filled($filterFloor); @endphp

<div class="d-flex flex-column gap-3 stagger">
    @forelse($bills as $bill)
        @php
            $total = (float) $bill->total_amount;
            $collected = (float) $bill->collected;
            $pct = $total > 0 ? min(100, round($collected / $total * 100)) : 0;
            $status = $collected >= $total && $total > 0 ? ['success', __('Paid')]
                : ($collected > 0 ? ['warning', __('Partial')] : ['danger', __('Due')]);
            $breakdown = $bill->split_breakdown;
            $invoicesByStudent = $bill->invoices->keyBy('student_id');
            $sharesCount = $breakdown['students'] ?? null ? count($breakdown['students']) : $bill->invoices->count();
            $editPayload = \Illuminate\Support\Js::from([
                'action' => route('admin.ac-bills.update', $bill),
                'room' => (string) $bill->room->room_number,
                'month' => $bill->bill_month->format('M Y'),
                'prev' => (float) $bill->previous_reading,
                'curr' => (float) $bill->current_reading,
                'rate' => (float) $bill->unit_price,
                // Meter-floor: the highest reading recorded BEFORE this bill's
                // month — the honest minimum for previous_reading.
                'floor' => $editFloors[$bill->id] ?? null,
            ]);
        @endphp
        <div class="card border-0 shadow-sm rounded-4" x-data="{ open: false }">
            <div class="card-body p-3 p-lg-4">

                {{-- ═══ Wide / reflow collapsed row ═══ --}}
                <div class="he-cq-wide ac-row" role="button" @click="open = !open">
                    <div class="ac-c-info">
                        <div class="ac-avatar flex-shrink-0"><i class="fa-solid fa-snowflake"></i></div>
                        <div class="ac-c-text">
                            <div class="ac-c-block">
                                <div class="fw-bold text-dark lh-sm text-truncate">{{ __('Room') }} {{ $bill->room->room_number }}</div>
                                <div class="text-muted small lh-sm text-truncate">{{ $bill->room->floor?->name ?? '—' }}</div>
                            </div>
                            <div class="ac-c-block">
                                <div class="fw-semibold text-dark lh-sm text-nowrap">{{ $bill->bill_month->format('M Y') }}</div>
                                <div class="text-muted small lh-sm text-nowrap" style="font-feature-settings: 'tnum';">
                                    {{ rtrim(rtrim(number_format($bill->total_units, 2), '0'), '.') }} {{ __('units') }} · {{ hostelease_money($bill->unit_price) }}/u
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ac-row-money">
                        <div class="ac-row-num ac-cell-amount">
                            <div class="ac-row-lbl">{{ __('Amount') }}</div>
                            <div class="fw-bold text-dark">{{ hostelease_money($total) }}</div>
                        </div>
                        <div class="ac-row-num ac-cell-collected">
                            <div class="ac-row-lbl">{{ __('Collected') }}</div>
                            <div class="fw-bold text-success">{{ hostelease_money($collected) }}</div>
                            <div class="ac-progress"><div class="ac-progress-fill" style="width: {{ $pct }}%;"></div></div>
                        </div>
                        <div class="ac-row-num ac-cell-split">
                            <div class="ac-row-lbl">{{ __('Split') }}</div>
                            <div class="fw-semibold text-secondary text-nowrap">{{ $sharesCount }} {{ __('students') }}</div>
                        </div>
                    </div>

                    <div class="ac-row-acts" @click.stop>
                        <span class="badge bg-{{ $status[0] }}-subtle text-{{ $status[0] }} rounded-pill px-3 py-2">{{ $status[1] }}</span>
                        <button type="button" class="he-icon-btn" title="{{ __('Edit readings / rate') }}" aria-label="{{ __('Edit readings / rate') }}"
                                @click="openEdit({{ $editPayload }})">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <form action="{{ route('admin.ac-bills.destroy', $bill) }}" method="POST" class="m-0"
                              data-confirm="{{ __('Delete this AC bill and its pending invoices?') }}">
                            @csrf @method('DELETE')
                            <button class="he-icon-btn is-danger" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                        <button type="button" class="he-icon-btn ac-chevron" :class="{ 'is-open': open }"
                                @click="open = !open" :title="open ? '{{ __('Collapse') }}' : '{{ __('Expand') }}'"
                                :aria-label="open ? '{{ __('Collapse') }}' : '{{ __('Expand') }}'" :aria-expanded="open">
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                    </div>
                </div>

                {{-- ═══ Bespoke phone card (container <640px) ═══ --}}
                <div class="he-cq-card">
                    <div class="d-flex align-items-center gap-2" role="button" @click="open = !open">
                        <div class="ac-avatar ac-avatar--sm flex-shrink-0"><i class="fa-solid fa-snowflake"></i></div>
                        <div class="flex-grow-1" style="min-width: 0;">
                            <div class="fw-bold text-dark text-truncate">{{ __('Room') }} {{ $bill->room->room_number }} · {{ $bill->bill_month->format('M Y') }}</div>
                            <div class="text-muted small text-truncate" style="font-feature-settings: 'tnum';">
                                {{ rtrim(rtrim(number_format($bill->total_units, 2), '0'), '.') }} {{ __('units') }} · {{ $sharesCount }} {{ __('students') }}
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0" style="font-feature-settings: 'tnum';">
                            <div class="fw-bold text-dark">{{ hostelease_money($total) }}</div>
                            <span class="badge bg-{{ $status[0] }}-subtle text-{{ $status[0] }} rounded-pill px-2">{{ $status[1] }}</span>
                        </div>
                    </div>
                    <div class="ac-progress mt-2"><div class="ac-progress-fill" style="width: {{ $pct }}%;"></div></div>
                    <div class="he-act-row mt-2">
                        <button type="button" class="he-icon-btn he-icon-btn--lg ac-chevron" :class="{ 'is-open': open }" @click="open = !open"
                                :aria-expanded="open" aria-label="{{ __('Details') }}">
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <span class="text-muted small">{{ __('Collected') }} <span class="fw-bold text-success">{{ hostelease_money($collected) }}</span></span>
                        <div class="he-act-right">
                            <button type="button" class="he-icon-btn he-icon-btn--lg" title="{{ __('Edit readings / rate') }}"
                                    aria-label="{{ __('Edit readings / rate') }}" @click="openEdit({{ $editPayload }})">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <form action="{{ route('admin.ac-bills.destroy', $bill) }}" method="POST" class="m-0"
                                  data-confirm="{{ __('Delete this AC bill and its pending invoices?') }}">
                                @csrf @method('DELETE')
                                <button class="he-icon-btn he-icon-btn--lg is-danger" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- ═══ Expanded: the bill explains itself ═══ --}}
                <div x-show="open" x-transition.opacity x-cloak class="ac-detail">

                    {{-- Meter strip --}}
                    <div class="ac-meter">
                        <div class="ac-meter-cell">
                            <span class="ac-row-lbl">{{ __('Meter') }}</span>
                            <span class="fw-bold text-dark" style="font-feature-settings: 'tnum';">
                                {{ rtrim(rtrim(number_format($bill->previous_reading, 2), '0'), '.') }}
                                <i class="fa-solid fa-arrow-right-long mx-1 text-muted small"></i>
                                {{ rtrim(rtrim(number_format($bill->current_reading, 2), '0'), '.') }}
                            </span>
                        </div>
                        <div class="ac-meter-cell">
                            <span class="ac-row-lbl">{{ __('Units × Rate') }}</span>
                            <span class="fw-bold text-dark" style="font-feature-settings: 'tnum';">
                                {{ rtrim(rtrim(number_format($bill->total_units, 2), '0'), '.') }} × {{ hostelease_money($bill->unit_price) }}
                            </span>
                        </div>
                        <div class="ac-meter-cell">
                            <span class="ac-row-lbl">{{ __('Generated') }}</span>
                            <span class="fw-semibold text-dark">{{ $bill->created_at->format('d M Y') }}</span>
                        </div>
                    </div>

                    @if($breakdown['note'] ?? null)
                        <div class="ac-note"><i class="fa-solid fa-circle-info me-1"></i>{{ $breakdown['note'] }}</div>
                    @endif

                    {{-- Metered segments (W6.3): which stretch of the meter each
                         occupant set consumed — the proof behind every share.
                         One full-month segment says nothing the row doesn't. --}}
                    @if(count($breakdown['segments'] ?? []) > 1)
                        <div class="ac-segments">
                            @foreach($breakdown['segments'] as $seg)
                                <div class="ac-segment">
                                    <span class="fw-semibold text-dark text-nowrap">{{ $seg['from'] }} – {{ $seg['to'] }}</span>
                                    <span class="text-muted text-nowrap" style="font-feature-settings: 'tnum';">
                                        @if($seg['units'] !== null){{ rtrim(rtrim(number_format($seg['units'], 2), '0'), '.') }} {{ __('units') }} · @endif{{ hostelease_money($seg['amount']) }}
                                    </span>
                                    <span class="text-muted text-nowrap">{{ $seg['occupants'] }} {{ __('occupants') }}</span>
                                    <span class="ac-seg-chip {{ $seg['estimated'] ? 'is-est' : '' }}">
                                        {{ $seg['estimated'] ? __('estimated by days') : __('metered') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Per-student shares: the day-ledger, retold --}}
                    <div class="ac-shares">
                        @if(!empty($breakdown['students']))
                            @foreach($breakdown['students'] as $s)
                                @php $inv = $invoicesByStudent->get($s['student_id']); @endphp
                                <div class="ac-share-row">
                                    <a href="{{ $inv?->student ? route('admin.students.show', $inv->student) : '#' }}" class="ac-share-who">
                                        <span class="ac-share-avatar">{{ mb_substr($s['name'], 0, 1) }}</span>
                                        <span style="min-width: 0;">
                                            <span class="d-block fw-semibold text-dark text-truncate">{{ $s['name'] }}</span>
                                            <span class="d-block small text-muted text-truncate">
                                                {{ $s['days'] }} {{ __('of') }} {{ $breakdown['days_in_month'] }} {{ __('days') }} ({{ $s['from'] }} – {{ $s['to'] }})
                                                @if($s['joined_mid']) · <span class="text-info">{{ __('joined mid-month') }}</span>@endif
                                                @if($s['left']) · <span class="text-warning-emphasis">{{ __('left') }}</span>@endif
                                            </span>
                                        </span>
                                    </a>
                                    <div class="ac-share-num">
                                        <span class="ac-row-lbl">{{ __('Share') }}</span>
                                        <span class="fw-bold text-dark">{{ hostelease_money($s['share']) }}</span>
                                    </div>
                                    <div class="ac-share-num">
                                        <span class="ac-row-lbl">{{ __('Paid') }}</span>
                                        <span class="fw-bold {{ ($inv?->paid_amount ?? 0) > 0 ? 'text-success' : 'text-muted' }}">{{ hostelease_money($inv?->paid_amount ?? 0) }}</span>
                                    </div>
                                    <span class="badge rounded-pill flex-shrink-0 bg-{{ ($inv?->status ?? 'pending') === 'paid' ? 'success' : (($inv?->status ?? 'pending') === 'partial' ? 'warning' : 'danger') }}-subtle text-{{ ($inv?->status ?? 'pending') === 'paid' ? 'success' : (($inv?->status ?? 'pending') === 'partial' ? 'warning' : 'danger') }}">
                                        {{ ucfirst($inv?->status ?? 'pending') }}
                                    </span>
                                </div>
                            @endforeach
                        @else
                            {{-- Legacy bill (pre-breakdown): the invoices still tell most of it. --}}
                            @foreach($bill->invoices as $inv)
                                <div class="ac-share-row">
                                    <a href="{{ $inv->student ? route('admin.students.show', $inv->student) : '#' }}" class="ac-share-who">
                                        <span class="ac-share-avatar">{{ mb_substr($inv->student?->name ?? '?', 0, 1) }}</span>
                                        <span style="min-width: 0;">
                                            <span class="d-block fw-semibold text-dark text-truncate">{{ $inv->student?->name ?? __('(removed)') }}</span>
                                            <span class="d-block small text-muted">{{ __('equal split (generated before day-wise billing)') }}</span>
                                        </span>
                                    </a>
                                    <div class="ac-share-num">
                                        <span class="ac-row-lbl">{{ __('Share') }}</span>
                                        <span class="fw-bold text-dark">{{ hostelease_money($inv->amount) }}</span>
                                    </div>
                                    <div class="ac-share-num">
                                        <span class="ac-row-lbl">{{ __('Paid') }}</span>
                                        <span class="fw-bold {{ (float) $inv->paid_amount > 0 ? 'text-success' : 'text-muted' }}">{{ hostelease_money($inv->paid_amount) }}</span>
                                    </div>
                                    <span class="badge rounded-pill flex-shrink-0 bg-{{ $inv->status === 'paid' ? 'success' : ($inv->status === 'partial' ? 'warning' : 'danger') }}-subtle text-{{ $inv->status === 'paid' ? 'success' : ($inv->status === 'partial' ? 'warning' : 'danger') }}">
                                        {{ ucfirst($inv->status) }}
                                    </span>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>

            </div>
        </div>
    @empty
        @if($isFiltered)
            <x-he-empty-state icon="magnifying-glass" title="{{ __('No matches') }}" subtitle="{{ __('No AC bills for this month and floor.') }}" />
        @else
            <x-he-empty-state icon="snowflake" title="{{ __('No AC bills for :month', ['month' => $filterMonth->format('F Y')]) }}" subtitle="{{ __('Generate a bill for a room to get started.') }}" />
        @endif
    @endforelse
</div>

@if($bills->hasPages())
    <div class="mt-4">{{ $bills->links() }}</div>
@endif
