@extends('layouts.app')
@section('title', 'Notifications')

@section('content')
<div class="page-enter" style="max-width: 820px; margin-inline: auto;">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 fw-bold mb-0">{{ __('Notifications') }}</h1>
            <p class="text-muted small mb-0">{{ __('Alerts, reminders and account activity.') }}</p>
        </div>
        @if($notifications->total() > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf @method('PATCH')
                <button class="btn btn-light border fw-semibold rounded-pill px-3 tactile-btn">
                    <i class="fa-solid fa-check-double me-1"></i> {{ __('Mark all read') }}
                </button>
            </form>
        @endif
    </div>

    <div class="card-premium p-0 overflow-hidden">
        @if($notifications->total() > 0)
            <div class="stagger">
                @foreach($notifications as $n)
                    <div class="notif-row d-flex align-items-start gap-3 px-3 px-md-4 py-3 {{ $n->read_at ? 'is-read' : '' }}">
                        <div class="notif-badge rounded-circle bg-{{ $n->level }}-subtle text-{{ $n->level }} d-flex align-items-center justify-content-center flex-shrink-0">
                            <i class="fa-solid fa-{{ $n->level === 'danger' ? 'triangle-exclamation' : ($n->level === 'success' ? 'check' : ($n->level === 'warning' ? 'clock' : 'bell')) }}"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="fw-semibold text-dark">{{ $n->title }}</span>
                                @unless($n->read_at)
                                    <span class="badge-premium bg-primary-subtle text-primary">{{ __('New') }}</span>
                                @endunless
                            </div>
                            <div class="text-muted small mt-1" style="line-height: 1.4;">{{ $n->message }}</div>
                            <div class="text-muted mt-1" style="font-size: var(--he-text-xs);">
                                <i class="fa-regular fa-clock me-1"></i>{{ $n->created_at->diffForHumans() }}
                            </div>
                        </div>
                        <div class="d-flex gap-1 flex-shrink-0">
                            @unless($n->read_at)
                                <form method="POST" action="{{ route('notifications.read', $n) }}">
                                    @csrf @method('PATCH')
                                    <button class="notif-action tactile-btn" title="{{ __('Mark read') }}"><i class="fa-solid fa-check"></i></button>
                                </form>
                            @endunless
                            <form method="POST" action="{{ route('notifications.destroy', $n) }}">
                                @csrf @method('DELETE')
                                <button class="notif-action notif-action--danger tactile-btn" title="{{ __('Delete') }}"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <x-he-empty-state icon="bell-slash" title="You're all caught up"
                subtitle="New alerts, reminders and account activity will appear here." />
        @endif
    </div>

    @if($notifications->hasPages())
        <div class="mt-3 d-flex justify-content-center">{{ $notifications->links() }}</div>
    @endif
</div>

@push('styles')
<style>
    .notif-row {
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        transition: background 0.2s var(--ease-out-expo);
    }
    .notif-row:last-child { border-bottom: none; }
    .notif-row:hover { background: var(--he-bg-surface-raised); }
    .notif-row.is-read { opacity: 0.7; }
    .notif-badge { width: 38px; height: 38px; font-size: 0.9rem; }
    .notif-action {
        width: 32px; height: 32px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: var(--he-radius-sm);
        border: 1px solid rgba(0, 0, 0, 0.06);
        background: var(--he-bg-surface);
        color: var(--he-text-muted);
        cursor: pointer;
        transition: all 0.2s var(--ease-out-expo);
    }
    .notif-action:hover { background: var(--he-primary-soft); color: var(--he-primary); border-color: transparent; }
    .notif-action--danger:hover { background: var(--he-danger-soft); color: var(--he-danger); }
    .min-w-0 { min-width: 0; }
</style>
@endpush
@endsection
