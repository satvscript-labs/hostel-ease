@extends('layouts.app')
@section('title', $title)

@push('styles')
<style>
    @media print { .he-sidebar,.he-topbar,footer,.no-print{display:none!important} .he-content{margin-left:0!important} }
    .report-table thead th {
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        color: var(--bs-secondary);
        background: rgba(0,0,0,0.02);
        border-bottom: 2px solid rgba(0,0,0,0.05);
        padding: 1rem;
    }
    .report-table tbody td {
        padding: 1rem;
        vertical-align: middle;
    }
    .report-table tfoot td {
        background: rgba(var(--bs-primary-rgb), 0.03);
        padding: 1rem;
        color: var(--bs-primary);
        font-size: 1.1rem;
    }
</style>
@endpush

@section('content')
<div class="page-enter">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4 no-print">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.reports.index') }}" class="btn btn-light rounded-circle shadow-sm" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="h4 fw-bold mb-0 text-dark">{{ $title }}</h1>
                @if($needsRange)
                <p class="text-muted small mb-0">{{ $from->format('d M Y') }} &mdash; {{ $to->format('d M Y') }}</p>
                @else
                <p class="text-muted small mb-0">{{ __('Live Snapshot') }}</p>
                @endif
            </div>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm"><i class="fa-solid fa-print me-1"></i> {{ __('Print') }}</button>
            <a href="{{ route('admin.reports.show', [$type, 'export' => 'pdf'] + request()->only(['period','from','to'])) }}" class="btn btn-outline-danger rounded-pill px-3 shadow-sm"><i class="fa-solid fa-file-pdf me-1"></i> {{ __('PDF') }}</a>
            <a href="{{ route('admin.reports.show', [$type, 'export' => 'excel'] + request()->only(['period','from','to'])) }}" class="btn btn-success rounded-pill px-3 shadow-sm"><i class="fa-solid fa-file-excel me-1"></i> {{ __('Excel') }}</a>
        </div>
    </div>

    @if($needsRange)
    <div class="card stat-card mb-4 border-0 shadow-sm no-print">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                @if($type === 'collection')
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase">{{ __('Group By') }}</label>
                    <select name="period" class="form-select form-select-lg bg-light border-0">
                        @foreach(['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly','yearly'=>'Yearly'] as $k=>$v)
                            <option value="{{ $k }}" @selected($period===$k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold text-muted text-uppercase">{{ __('From Date') }}</label>
                    <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-lg bg-light border-0">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold text-muted text-uppercase">{{ __('To Date') }}</label>
                    <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-lg bg-light border-0">
                </div>
                <div class="col-12 col-md-auto ms-auto">
                    <button class="btn btn-primary btn-lg rounded-pill px-4 shadow-sm w-100"><i class="fa-solid fa-filter me-1"></i> {{ __('Apply') }}</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <div class="card stat-card border-0 shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 report-table" data-datatable>
                <thead>
                    <tr>
                        @foreach($data['headings'] as $h)
                            <th class="{{ in_array($loop->index, $data['money']) ? 'text-end' : '' }}">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @forelse($data['rows'] as $row)
                    <tr>
                        @foreach($row as $i => $cell)
                            <td class="{{ in_array($i, $data['money']) ? 'text-end fw-semibold text-dark' : 'text-secondary' }}">{{ in_array($i, $data['money']) ? hostelease_money($cell) : $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($data['headings']) }}" class="text-center text-muted py-5"><i class="fa-solid fa-inbox fs-2 mb-3 d-block opacity-50"></i> {{ __('No data available for this report.') }}</td></tr>
                @endforelse
                </tbody>
                @if(! is_null($data['total']))
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="{{ count($data['headings']) - 1 }}" class="text-end text-uppercase small">{{ __('Total') }}</td>
                        <td class="text-end">{{ hostelease_money($data['total']) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
