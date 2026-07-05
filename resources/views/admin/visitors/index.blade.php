@extends('layouts.app')
@section('title', 'Visitor Register')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Visitor Register <span class="badge bg-success">{{ $insideCount }} inside</span></h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#visModal"><i class="fa-solid fa-user-plus me-1"></i> New Check-in</button>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <select name="filter" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All visitors</option>
            <option value="inside" @selected(request('filter')==='inside')>Currently inside</option>
        </select>
    </div>
    <div class="col-6 col-md-3"><input type="date" name="date" value="{{ request('date') }}" class="form-control form-control-sm" onchange="this.form.submit()"></div>
</form>

<div class="card stat-card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-datatable>
            <thead><tr><th>Visitor</th><th>Mobile</th><th>Visiting</th><th>Purpose</th><th>Check-in</th><th>Check-out</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @foreach($visitors as $v)
                <tr>
                    <td class="fw-semibold">{{ $v->name }}</td>
                    <td>@if($v->mobile)<x-mobile-link :mobile="$v->mobile" />@else — @endif</td>
                    <td>{{ $v->student?->name ?? '—' }}</td>
                    <td>{{ $v->purpose ?? '—' }}</td>
                    <td class="text-nowrap small">{{ $v->check_in->format('d M Y H:i') }}</td>
                    <td class="text-nowrap small">
                        @if($v->check_out){{ $v->check_out->format('d M Y H:i') }}
                        @else <span class="badge bg-success">Inside</span>@endif
                    </td>
                    <td class="text-end">
                        @if($v->isInside())
                            <form action="{{ route('admin.visitors.checkout', $v) }}" method="POST" class="d-inline">
                                @csrf @method('PATCH')<button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-right-from-bracket me-1"></i>Check out</button>
                            </form>
                        @endif
                        <form action="{{ route('admin.visitors.destroy', $v) }}" method="POST" class="d-inline" data-confirm="Delete this visitor record?">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div></div>

<div class="modal fade" id="visModal" tabindex="-1"><div class="modal-dialog">
    <form class="modal-content" method="POST" action="{{ route('admin.visitors.store') }}">@csrf
        <div class="modal-header"><h5 class="modal-title">New Visitor Check-in</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-6"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div>
                <div class="col-6"><label class="form-label">Mobile</label><div class="input-group"><span class="input-group-text">+91</span><input type="tel" name="mobile" maxlength="10" inputmode="numeric" class="form-control"></div></div>
                <div class="col-6"><label class="form-label">Visiting (student)</label>
                    <select name="student_id" class="form-select" data-select2><option value="">—</option>@foreach($students as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label">Purpose</label><input type="text" name="purpose" class="form-control"></div>
                <div class="col-6"><label class="form-label">ID Proof</label><input type="text" name="id_proof" class="form-control" placeholder="Aadhaar / DL no."></div>
                <div class="col-6"><label class="form-label">Check-in</label><input type="datetime-local" name="check_in" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}"></div>
                <div class="col-12"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control"></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Check in</button></div>
    </form>
</div></div>
@endsection
