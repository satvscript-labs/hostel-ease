{{-- Wallet list fragment — everything inside #pw-list (§4.3). Departed
     students with money in custody stay listed, flagged (owner decision) —
     the old page hid them while the total still counted their balance. --}}
@php $isFiltered = filled($search) || filled($filter); @endphp

<div class="d-flex flex-column gap-3 stagger">
    @forelse($students as $s)
        @php
            $bal = (float) $s->pocket_balance;
            $balClass = $bal > 0 ? 'is-positive' : ($bal < 0 ? 'is-negative' : 'is-zero');
            $left = $s->status !== 'active';
        @endphp
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-3 p-lg-4">

                {{-- ═══ Wide / reflow row ═══ --}}
                <div class="he-cq-wide pw-row">
                    <div class="pw-c-info">
                        <img src="{{ $s->photo_url ?: 'https://ui-avatars.com/api/?name='.urlencode($s->name).'&background=eef2ff&color=4f46e5' }}"
                             class="rounded-circle flex-shrink-0" style="width: 44px; height: 44px; object-fit: cover;" alt="">
                        <div style="min-width: 0;">
                            <div class="d-block text-truncate">
                                <a href="{{ route('admin.students.show', $s) }}" class="fw-bold text-dark text-decoration-none">{{ $s->name }}</a>
                                @if($left)<span class="pw-left-chip"><i class="fa-solid fa-person-walking-arrow-right"></i>{{ __('Left') }}</span>@endif
                            </div>
                            <div class="text-muted small lh-sm text-truncate">
                                @if($s->activeAssignment)
                                    <i class="fa-solid fa-bed me-1 opacity-75"></i>{{ __('Room') }} {{ $s->activeAssignment->bed->room->room_number }} · {{ $s->activeAssignment->bed->bed_number }}
                                @elseif($left)
                                    {{ __('No longer resident — settle this wallet') }}
                                @else
                                    <i class="fa-solid fa-triangle-exclamation me-1 text-warning"></i>{{ __('No bed assigned') }}
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="pw-c-bal">
                        <span class="pw-balance {{ $balClass }}">
                            <i class="fa-solid {{ $bal < 0 ? 'fa-hand-holding-hand' : 'fa-wallet' }}" style="font-size: 0.75rem;"></i>
                            {{ hostelease_money($bal) }}
                        </span>
                    </div>

                    <div class="pw-row-acts">
                        <a href="{{ route('admin.pocket-money.show', $s) }}" class="btn btn-sm btn-premium rounded-pill fw-bold px-3 text-nowrap" style="min-height: 36px;">
                            <i class="fa-solid fa-wallet me-1"></i>{{ __('Open Wallet') }}
                        </a>
                    </div>
                </div>

                {{-- ═══ Bespoke phone card ═══ --}}
                <div class="he-cq-card">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <img src="{{ $s->photo_url ?: 'https://ui-avatars.com/api/?name='.urlencode($s->name).'&background=eef2ff&color=4f46e5' }}"
                             class="rounded-circle flex-shrink-0" style="width: 40px; height: 40px; object-fit: cover;" alt="">
                        <div class="flex-grow-1" style="min-width: 0;">
                            <div class="text-truncate">
                                <span class="fw-bold text-dark">{{ $s->name }}</span>
                                @if($left)<span class="pw-left-chip"><i class="fa-solid fa-person-walking-arrow-right"></i>{{ __('Left') }}</span>@endif
                            </div>
                            <div class="text-muted small text-truncate">
                                @if($s->activeAssignment)
                                    {{ __('Room') }} {{ $s->activeAssignment->bed->room->room_number }} · {{ $s->activeAssignment->bed->bed_number }}
                                @else
                                    {{ $left ? __('No longer resident') : __('No bed assigned') }}
                                @endif
                            </div>
                        </div>
                        <span class="pw-balance {{ $balClass }} flex-shrink-0">{{ hostelease_money($bal) }}</span>
                    </div>

                    <a href="{{ route('admin.pocket-money.show', $s) }}" class="btn btn-premium rounded-pill fw-bold w-100" style="min-height: 44px;">
                        <i class="fa-solid fa-wallet me-1"></i>{{ __('Open Wallet') }}
                    </a>
                </div>

            </div>
        </div>
    @empty
        @if($isFiltered)
            <x-he-empty-state icon="magnifying-glass" title="{{ __('No matches') }}" subtitle="{{ __('No wallets match your search or filter.') }}" />
        @else
            <x-he-empty-state icon="wallet" title="{{ __('No wallets yet') }}" subtitle="{{ __('Active students appear here; open a wallet from a student profile.') }}" />
        @endif
    @endforelse
</div>

@if($students->hasPages())
    <div class="mt-4">{{ $students->links() }}</div>
@endif
