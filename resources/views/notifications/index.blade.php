@extends('layouts.app')
@section('title', 'Notifications')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Notifications</h1>
    @if($notifications->total() > 0)
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf @method('PATCH')
            <button class="btn btn-light"><i class="fa-solid fa-check-double me-1"></i> Mark all read</button>
        </form>
    @endif
</div>

<div class="card stat-card"><div class="card-body p-0">
    <ul class="list-group list-group-flush">
        @forelse($notifications as $n)
            <li class="list-group-item d-flex align-items-start gap-3 {{ $n->read_at ? 'opacity-75' : '' }}">
                <i class="fa-solid fa-circle text-{{ $n->level }} mt-2" style="font-size:.6rem;"></i>
                <div class="flex-grow-1">
                    <div class="fw-semibold">{{ $n->title }}
                        @unless($n->read_at)<span class="badge bg-primary ms-1">New</span>@endunless
                    </div>
                    <div class="text-muted small">{{ $n->message }}</div>
                    <div class="text-muted" style="font-size:.75rem;">{{ $n->created_at->diffForHumans() }}</div>
                </div>
                <div class="d-flex gap-1">
                    @unless($n->read_at)
                        <form method="POST" action="{{ route('notifications.read', $n) }}">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-light" title="Mark read"><i class="fa-solid fa-check"></i></button>
                        </form>
                    @endunless
                    <form method="POST" action="{{ route('notifications.destroy', $n) }}">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-light text-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </div>
            </li>
        @empty
            <li class="list-group-item text-center text-muted py-5">No notifications. You're all caught up 🎉</li>
        @endforelse
    </ul>
</div></div>

<div class="mt-3">{{ $notifications->links() }}</div>
@endsection
