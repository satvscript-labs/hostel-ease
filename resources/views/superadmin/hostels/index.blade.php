@extends('layouts.app')
@section('title', 'Hostels')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Hostels</h1>
    <a href="{{ route('superadmin.hostels.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i> Add Hostel</a>
</div>

<div class="card stat-card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-datatable>
            <thead><tr><th>Hostel</th><th>Owner</th><th>Mobile</th><th>Students</th><th>Expires</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @foreach($hostels as $h)
                @php($days = $h->daysUntilExpiry())
                <tr>
                    <td class="fw-semibold"><a href="{{ route('superadmin.hostels.show', $h) }}" class="text-decoration-none">{{ $h->name }}</a></td>
                    <td>{{ $h->owner_name }}</td>
                    <td><x-mobile-link :mobile="$h->mobile" /></td>
                    <td>{{ $h->students_count }}</td>
                    <td>
                        {{ optional($h->subscription_end)->format('d M Y') ?? '—' }}
                        @if(!is_null($days) && $days <= 30)
                            <span class="badge bg-{{ $days < 0 ? 'danger' : 'warning text-dark' }}">{{ $days < 0 ? 'Expired' : $days.'d' }}</span>
                        @endif
                    </td>
                    <td><span class="badge bg-{{ $h->status==='active'?'success':($h->status==='expired'?'danger':'secondary') }}">{{ ucfirst($h->status) }}</span></td>
                    <td class="text-end">
                        <a href="{{ route('superadmin.hostels.show', $h) }}" class="btn btn-sm btn-light"><i class="fa-solid fa-eye"></i></a>
                        <a href="{{ route('superadmin.hostels.edit', $h) }}" class="btn btn-sm btn-light"><i class="fa-solid fa-pen"></i></a>
                        <form action="{{ route('superadmin.hostels.destroy', $h) }}" method="POST" class="d-inline" data-confirm="Delete {{ $h->name }}? This removes its admins and data.">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div></div>
@endsection
