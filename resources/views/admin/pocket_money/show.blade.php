@extends('layouts.app')
@section('title', 'Pocket Money — '.$student->name)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Pocket Money — {{ $student->name }}</h1>
    <a href="{{ route('admin.pocket-money.index') }}" class="btn btn-light"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card text-white mb-3" style="background:#2563eb;border-radius:16px;"><div class="card-body text-center py-4">
            <div class="opacity-75">Balance</div>
            <div class="display-6 fw-bold">{{ hostelease_money($balance) }}</div>
        </div></div>
        <div class="row g-2">
            <div class="col-6"><form method="POST" action="{{ route('admin.pocket-money.store', $student) }}" class="card stat-card"><div class="card-body">@csrf
                <input type="hidden" name="type" value="deposit">
                <label class="form-label fw-semibold text-success">Deposit</label>
                <input type="number" step="0.01" name="amount" class="form-control mb-2" placeholder="Amount" required>
                <input type="text" name="note" class="form-control mb-2" placeholder="Note">
                <button class="btn btn-success w-100"><i class="fa-solid fa-plus"></i> Deposit</button>
            </div></form></div>
            <div class="col-6"><form method="POST" action="{{ route('admin.pocket-money.store', $student) }}" class="card stat-card"><div class="card-body">@csrf
                <input type="hidden" name="type" value="withdraw">
                <label class="form-label fw-semibold text-warning">Withdraw</label>
                <input type="number" step="0.01" name="amount" class="form-control mb-2" placeholder="Amount" required>
                <input type="text" name="note" class="form-control mb-2" placeholder="Note">
                <button class="btn btn-warning w-100"><i class="fa-solid fa-minus"></i> Withdraw</button>
            </div></form></div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card stat-card"><div class="card-body">
            <h6 class="fw-bold">History</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Type</th><th>Amount</th><th>Note</th><th>Date</th><th></th></tr></thead>
                    <tbody>
                    @forelse($transactions as $t)
                        <tr>
                            <td>@if($t->type==='deposit')<span class="badge bg-success">Deposit</span>@else<span class="badge bg-warning text-dark">Withdraw</span>@endif</td>
                            <td>{{ hostelease_money($t->amount) }}</td>
                            <td>{{ $t->note ?? '—' }}</td>
                            <td class="small text-nowrap">{{ $t->created_at->format('d M Y H:i') }}</td>
                            <td class="text-end"><form action="{{ route('admin.pocket-money.destroy', [$student, $t]) }}" method="POST" data-confirm="Delete this transaction?">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No transactions yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>
@endsection

