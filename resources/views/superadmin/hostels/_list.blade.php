{{-- Hostels list fragment (W12) — swapped wholesale by the filter form and
     pagination (§4.3). Three designed tiers (§4.11): subgrid one-liner ≥880,
     two-line reflow 640–880, iOS row below. Each row's Edit carries its OWN
     payload, so paged-in rows stay editable (the page-load map only knows
     page 1). --}}
<div class="panel-card shadow-sm">
    <div class="sah-list">
        @forelse($hostels as $h)
            @php($days = $h->daysUntilExpiry())
            @php($statusColor = $h->status === 'active' ? 'success' : ($h->status === 'expired' ? 'danger' : 'secondary'))
            
            @php($payload = ['id' => $h->public_id, 'name' => $h->name, 'owner_name' => $h->owner_name, 'mobile' => $h->mobile,
                'email' => $h->email, 'address' => $h->address, 'city' => $h->city, 'state' => $h->state,
                'gst_number' => $h->gst_number, 'status' => $h->status,
                'subscription_start' => optional($h->subscription_start)->format('Y-m-d'),
                'subscription_end' => optional($h->subscription_end)->format('Y-m-d')])
            <div class="sah-row">
                <a href="{{ route('superadmin.hostels.show', $h) }}" class="sah-id text-decoration-none">
                    <div class="sah-ic"><i class="fa-solid fa-hotel"></i></div>
                    <div class="sah-text">
                        <div class="sah-name text-truncate">{{ $h->name }}</div>
                        <div class="sah-sub text-truncate">{{ $h->city ?: '—' }}{{ $h->state ? ', '.$h->state : '' }} · {{ $h->students_count }} {{ __('students') }}</div>
                    </div>
                </a>

                <div class="sah-owner">
                    <div class="rounded-circle bg-light border text-secondary d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width:30px; height:30px; font-size:.72rem;">{{ strtoupper(substr((string) $h->owner_name, 0, 1)) }}</div>
                    <div class="sah-text">
                        <div class="small fw-semibold text-dark text-truncate">{{ $h->owner_name }}</div>
                        <div class="text-muted text-truncate" style="font-size:.72rem;"><x-mobile-link :mobile="$h->mobile" /></div>
                    </div>
                </div>

                <div class="sah-cov">
                    <i class="fa-solid fa-calendar-check {{ $days !== null && $days <= 0 ? 'text-danger' : ($days !== null && $days <= 30 ? 'text-warning' : 'text-success') }}"></i>
                    <span class="fw-semibold text-dark">{{ optional($h->subscription_end)->format('d M Y') ?? '—' }}</span>
                    @if(! is_null($days))
                        <span class="{{ $days <= 0 ? 'text-danger' : ($days <= 30 ? 'text-warning' : 'text-muted') }}" style="font-size:.74rem;">{{ $days <= 0 ? __('expired') : $days.' '.__('d left') }}</span>
                    @endif
                </div>

                <div class="sah-status">
                    <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} rounded-pill px-3">{{ ucfirst($h->status) }}</span>
                </div>

                <div class="sah-acts he-act-row">
                    @if(!empty($accountByHostel[$h->id]))
                        <a href="{{ route('superadmin.accounts.show', $accountByHostel[$h->id]) }}" class="he-icon-btn" title="{{ __('Owner account') }}" aria-label="{{ __('Owner account') }}"><i class="fa-solid fa-user-gear"></i></a>
                    @endif
                    <button type="button" @click='openEditModal(@json($payload))' class="he-icon-btn" title="{{ __('Edit hostel') }}" aria-label="{{ __('Edit hostel') }}"><i class="fa-solid fa-pen"></i></button>
                    <form action="{{ route('superadmin.hostels.destroy', $h) }}" method="POST" class="m-0" data-confirm="{{ __('Delete :name? This removes its admins and data.', ['name' => $h->name]) }}">
                        @csrf @method('DELETE')
                        <button class="he-icon-btn is-danger" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </div>
            </div>
        @empty
            <div class="p-3" style="grid-column:1/-1;">
                <x-he-empty-state icon="hotel" title="{{ __('No hostels found') }}"
                    subtitle="{{ (request('q') || request('status')) ? __('Try clearing the search or status filter.') : __('Provision the first hostel to get started.') }}" />
            </div>
        @endforelse
    </div>

    @if($hostels->hasPages())
        <div class="p-3 border-top">{{ $hostels->links() }}</div>
    @endif
</div>
