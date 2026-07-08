@extends('layouts.app')
@section('title', 'Hostels')

@section('content')
<div x-data="hostelsManager()">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark tracking-tight">Hostels</h1>
            <p class="text-muted mb-0 small">Manage all tenant branches and their statuses.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" @click="createModalOpen = true" class="btn btn-primary shadow-sm rounded-pill px-4">
                <i class="fa-solid fa-plus me-2"></i> Add Hostel
            </button>
        </div>
    </div>

<div class="card stat-card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                    <tr>
                        <th class="py-3 px-4 text-muted fw-semibold border-0">Hostel / Branch</th>
                        <th class="py-3 px-4 text-muted fw-semibold border-0">Owner</th>
                        <th class="py-3 px-4 text-muted fw-semibold border-0 text-center">Students</th>
                        <th class="py-3 px-4 text-muted fw-semibold border-0">Subscription</th>
                        <th class="py-3 px-4 text-muted fw-semibold border-0">Status</th>
                        <th class="py-3 px-4 text-end border-0"></th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                @foreach($hostels as $h)
                    @php
                        $days = $h->daysUntilExpiry();
                        // Generate avatar initials
                        $initials = collect(explode(' ', $h->owner_name))->map(fn($w) => substr($w, 0, 1))->take(2)->join('');
                        $colors = ['primary', 'success', 'warning', 'info', 'danger'];
                        $avatarColor = $colors[strlen($h->owner_name) % 5];
                    @endphp
                    <tr>
                        <td class="px-4 py-3 text-nowrap">
                            <a href="{{ route('superadmin.hostels.show', $h) }}" class="text-decoration-none fw-bold text-dark d-block fs-6 mb-1">{{ $h->name }}</a>
                            <span class="text-muted small"><i class="fa-solid fa-location-dot me-1"></i> {{ $h->city }}{{ $h->state ? ', '.$h->state : '' }}</span>
                        </td>
                        <td class="px-4 py-3 text-nowrap">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-{{ $avatarColor }}-subtle text-{{ $avatarColor }} d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 40px; height: 40px; font-size: 0.9rem;">
                                    {{ strtoupper($initials) }}
                                </div>
                                <div>
                                    <div class="fw-semibold text-dark">{{ $h->owner_name }}</div>
                                    <div class="small text-muted"><x-mobile-link :mobile="$h->mobile" /></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge bg-light text-dark border px-2 py-1 rounded-pill">{{ $h->students_count }}</span>
                        </td>
                        <td class="px-4 py-3 text-nowrap">
                            <div class="fw-medium text-dark">{{ optional($h->subscription_end)->format('d M Y') ?? '—' }}</div>
                            @if(!is_null($days))
                                <div class="small mt-1">
                                    @if($days <= 0)
                                        <span class="text-danger fw-semibold"><i class="fa-solid fa-circle-xmark me-1"></i>Expired</span>
                                    @elseif($days <= 30)
                                        <span class="text-warning fw-semibold"><i class="fa-solid fa-clock me-1"></i>{{ $days }} days left</span>
                                    @else
                                        <span class="text-success"><i class="fa-solid fa-check-circle me-1"></i>Active</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($h->status === 'active')
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3"><i class="fa-solid fa-circle me-1" style="font-size: 0.5rem; vertical-align: middle;"></i> Active</span>
                            @elseif($h->status === 'expired')
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3">Expired</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3">{{ ucfirst($h->status) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-end text-nowrap">
                            <a href="{{ route('superadmin.hostels.show', $h) }}" class="btn btn-sm btn-light rounded-circle shadow-sm" style="width: 32px; height: 32px;" title="View Profile">
                                <i class="fa-regular fa-eye text-primary"></i>
                            </a>
                            <button type="button" @click="openEditModal({{ $h->id }})" class="btn btn-sm btn-light rounded-circle shadow-sm mx-1" style="width: 32px; height: 32px;" title="Edit Hostel">
                                <i class="fa-regular fa-pen-to-square text-secondary"></i>
                            </button>
                            <form action="{{ route('superadmin.hostels.destroy', $h) }}" method="POST" class="d-inline" data-confirm="Delete {{ $h->name }}? This removes its admins and data.">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-light rounded-circle shadow-sm" style="width: 32px; height: 32px;" title="Delete">
                                    <i class="fa-regular fa-trash-can text-danger"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($hostels->hasPages())
            <div class="p-3 border-top">
                {{ $hostels->links() }}
            </div>
        @endif
    </div>
</div>

@include('superadmin.hostels.modals')

</div>
@endsection
