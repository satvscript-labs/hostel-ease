@extends('layouts.app')
@section('title', $title)

@push('styles')
<style>@media print { .hsms-sidebar,.hsms-topbar,footer,.no-print{display:none!important} .hsms-content{margin-left:0!important} }</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('admin.reports.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
        <h1 class="h4 fw-bold mb-0">{{ $title }}</h1>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-light"><i class="fa-solid fa-print me-1"></i> Print</button>
        <a href="{{ route('admin.reports.show', [$type, 'export' => 'pdf'] + request()->only(['period','from','to'])) }}" class="btn btn-light"><i class="fa-solid fa-file-pdf me-1"></i> PDF</a>
        <a href="{{ route('admin.reports.show', [$type, 'export' => 'excel'] + request()->only(['period','from','to'])) }}" class="btn btn-success"><i class="fa-solid fa-file-excel me-1"></i> Excel</a>
    </div>
</div>

@if($needsRange)
<form method="GET" class="card stat-card mb-3 no-print"><div class="card-body">
    <div class="row g-2 align-items-end">
        @if($type === 'collection')
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Group By</label>
            <select name="period" class="form-select form-select-sm">
                @foreach(['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly','yearly'=>'Yearly'] as $k=>$v)
                    <option value="{{ $k }}" @selected($period===$k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-6 col-md-3"><label class="form-label small mb-1">From</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">To</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-3"><button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter me-1"></i> Apply</button></div>
    </div>
</div></form>
@endif

<div class="card stat-card"><div class="card-body">
    @if($needsRange)<p class="text-muted small mb-2">Period: {{ $from->format('d M Y') }} → {{ $to->format('d M Y') }}</p>@endif
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-datatable>
            <thead><tr>@foreach($data['headings'] as $h)<th class="{{ in_array($loop->index, $data['money']) ? 'text-end' : '' }}">{{ $h }}</th>@endforeach</tr></thead>
            <tbody>
            @forelse($data['rows'] as $row)
                <tr>
                    @foreach($row as $i => $cell)
                        <td class="{{ in_array($i, $data['money']) ? 'text-end' : '' }}">{{ in_array($i, $data['money']) ? hostelease_money($cell) : $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($data['headings']) }}" class="text-center text-muted py-4">No data for this report.</td></tr>
            @endforelse
            </tbody>
            @if(! is_null($data['total']))
            <tfoot><tr class="fw-bold border-top">
                <td colspan="{{ count($data['headings']) - 1 }}" class="text-end">Total</td>
                <td class="text-end">{{ hostelease_money($data['total']) }}</td>
            </tr></tfoot>
            @endif
        </table>
    </div>
</div></div>
@endsection

