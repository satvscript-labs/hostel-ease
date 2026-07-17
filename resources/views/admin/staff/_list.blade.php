{{-- Staff directory fragment — everything inside #staff-list so the filter form
     and pagination swap it wholesale (§4.3). Server-rendered: search/status/
     paging happen in StaffController.

     The old page rendered every staff row and filtered CLIENT-side by
     interpolating each name into an Alpine expression — a name with an
     apostrophe ("O'Brien") broke the expression and the card disappeared. It
     also ran two queries per row for the attendance/paid figures.

     Rows are container-tiered (§4.9/4.10): one-line grid ≥880px, two-line
     reflow below, bespoke phone card under 640px. --}}
@php $isFiltered = filled($search) || filled($status); @endphp

<div class="d-flex flex-column gap-3 stagger">
    @forelse($staff as $s)
        @php
            $removed = $s->trashed();
            $payload = \Illuminate\Support\Js::from([
                'action' => route('admin.staff.salary', $s),
                'name' => $s->name,
                'salary' => (float) $s->monthly_salary,
            ]);
        @endphp
        <div class="card border-0 shadow-sm rounded-4 {{ $removed ? 'opacity-75' : '' }}">
            <div class="card-body p-3 p-lg-4">

                {{-- ═══ Wide / reflow row ═══ --}}
                <div class="he-cq-wide st-row">
                    <div class="st-c-info">
                        <x-staff-avatar :staff="$s" size="44" />
                        <div class="st-c-text">
                            <div class="st-c-block">
                                <div class="d-block text-truncate">
                                    <a href="{{ route('admin.staff.show', $s) }}" class="fw-bold text-dark text-decoration-none">{{ $s->name }}</a>
                                    @if($removed)
                                        <span class="st-chip st-chip--removed">{{ __('Removed') }}</span>
                                    @elseif(! $s->is_active)
                                        <span class="st-chip st-chip--inactive">{{ __('Inactive') }}</span>
                                    @endif
                                </div>
                                <div class="text-muted small lh-sm text-truncate">{{ $s->designation ?: __('Staff Member') }}</div>
                            </div>
                            <div class="st-c-block">
                                <div class="fw-semibold text-dark small lh-sm text-truncate">
                                    @if($s->mobile)<x-mobile-link :mobile="$s->mobile" />@else<span class="text-muted">—</span>@endif
                                </div>
                                <div class="text-muted small lh-sm text-truncate">
                                    {{ $s->join_date ? __('Joined').' '.$s->join_date->format('M Y') : __('Join date not set') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="st-row-money">
                        <div class="st-row-num">
                            <div class="st-row-lbl">{{ __('Salary') }}</div>
                            <div class="fw-bold text-dark">{{ hostelease_money($s->monthly_salary) }}</div>
                        </div>
                        <div class="st-row-num">
                            <div class="st-row-lbl">{{ __('Present') }}</div>
                            <div class="fw-bold text-dark">{{ $s->present_this_month }} <span class="text-muted fw-normal small">{{ __('d') }}</span></div>
                        </div>
                        <div class="st-row-num">
                            <div class="st-row-lbl">{{ __('Paid') }}</div>
                            <div class="fw-bold {{ (float) $s->paid_this_month > 0 ? 'text-success' : 'text-muted' }}">{{ hostelease_money($s->paid_this_month ?? 0) }}</div>
                        </div>
                    </div>

                    <div class="st-row-acts">
                        @if($removed)
                            <form method="POST" action="{{ route('admin.staff.restore', $s->id) }}" class="m-0">
                                @csrf
                                <button class="btn btn-sm btn-white border rounded-pill fw-bold px-3 text-nowrap" style="min-height: 36px;">
                                    <i class="fa-solid fa-rotate-left me-1"></i>{{ __('Restore') }}
                                </button>
                            </form>
                        @else
                            <button type="button" class="btn btn-sm btn-success rounded-pill fw-bold px-3 text-nowrap" style="min-height: 36px;"
                                    @click="openPay({{ $payload }})">
                                <i class="fa-solid fa-money-bill-wave me-1"></i>{{ __('Pay') }}
                            </button>
                            {{-- No "open profile" button: the name IS the link. --}}
                            <form method="POST" action="{{ route('admin.staff.destroy', $s) }}" class="m-0"
                                  data-confirm="{{ __('Remove :name from the directory? Their salary history and its expense entries stay on the books.', ['name' => $s->name]) }}">
                                @csrf @method('DELETE')
                                <button class="he-icon-btn is-danger" title="{{ __('Remove staff') }}" aria-label="{{ __('Remove staff') }}">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- ═══ Bespoke phone card ═══ --}}
                <div class="he-cq-card">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <x-staff-avatar :staff="$s" size="40" />
                        <div class="flex-grow-1" style="min-width: 0;">
                            <div class="text-truncate">
                                <a href="{{ route('admin.staff.show', $s) }}" class="fw-bold text-dark text-decoration-none">{{ $s->name }}</a>
                            </div>
                            <div class="text-muted small text-truncate">{{ $s->designation ?: __('Staff Member') }}</div>
                        </div>
                        @if($removed)
                            <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-1 flex-shrink-0">{{ __('Removed') }}</span>
                        @elseif($s->is_active)
                            <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1 flex-shrink-0">{{ __('Active') }}</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-1 flex-shrink-0">{{ __('Inactive') }}</span>
                        @endif
                    </div>

                    <div class="he-money-list mb-2">
                        <div class="he-money-row">
                            <span class="he-money-lbl">{{ __('Salary') }}</span>
                            <span class="fw-bold">{{ hostelease_money($s->monthly_salary) }}</span>
                        </div>
                        <div class="he-money-row">
                            <span class="he-money-lbl">{{ __('Present This Month') }}</span>
                            <span class="fw-bold">{{ $s->present_this_month }} {{ __('days') }}</span>
                        </div>
                        <div class="he-money-row">
                            <span class="he-money-lbl">{{ __('Paid This Month') }}</span>
                            <span class="fw-bold {{ (float) $s->paid_this_month > 0 ? 'text-success' : 'text-muted' }}">{{ hostelease_money($s->paid_this_month ?? 0) }}</span>
                        </div>
                    </div>

                    <div class="he-act-row">
                        @if($removed)
                            <form method="POST" action="{{ route('admin.staff.restore', $s->id) }}" class="m-0">
                                @csrf
                                <button class="btn btn-white border rounded-pill fw-bold px-4" style="min-height: 44px;">
                                    <i class="fa-solid fa-rotate-left me-1"></i>{{ __('Restore') }}
                                </button>
                            </form>
                        @else
                            <button type="button" class="btn btn-success rounded-pill fw-bold px-4" style="min-height: 44px;"
                                    @click="openPay({{ $payload }})">
                                <i class="fa-solid fa-money-bill-wave me-1"></i>{{ __('Pay Salary') }}
                            </button>
                            <div class="he-act-right">
                                <form method="POST" action="{{ route('admin.staff.destroy', $s) }}" class="m-0"
                                      data-confirm="{{ __('Remove :name from the directory? Their salary history and its expense entries stay on the books.', ['name' => $s->name]) }}">
                                    @csrf @method('DELETE')
                                    <button class="he-icon-btn he-icon-btn--lg is-danger" title="{{ __('Remove staff') }}" aria-label="{{ __('Remove staff') }}">
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
            <x-he-empty-state icon="magnifying-glass" title="{{ __('No matches') }}"
                subtitle="{{ __('No staff match your search or filter.') }}" />
        @else
            <x-he-empty-state icon="id-badge" title="{{ __('No staff yet') }}"
                subtitle="{{ __('Add your first employee to start tracking payroll and attendance.') }}" />
        @endif
    @endforelse
</div>

@if($staff->hasPages())
    <div class="mt-4">{{ $staff->links() }}</div>
@endif
