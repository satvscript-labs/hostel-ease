{{-- Account 360 · Reminders (S4 · 24_S4_DESIGN.md §6): can this customer be reached,
     what have they been told, and a free WhatsApp nudge that records itself. --}}
@php($rp = $reminderPanel)
<div class="panel-card shadow-sm mb-4 a360-rem">
    <div class="d-flex flex-wrap align-items-center gap-3 p-3 px-4">
        <div class="d-flex align-items-center gap-2 flex-wrap flex-grow-1 min-w-0">
            <h6 class="fw-bold mb-0 text-dark me-1"><i class="fa-solid fa-bell text-primary me-2"></i>Reminders</h6>

            @if(! $rp['email'])
                <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2" title="Renewal reminders reach this owner in the app only"><i class="fa-solid fa-envelope-open me-1"></i>No email</span>
            @elseif(! $rp['verified'])
                <span class="badge rounded-pill bg-warning-subtle text-warning px-3 py-2" title="Reminders are sent, but the owner has not confirmed this address"><i class="fa-solid fa-circle-exclamation me-1"></i>Email not verified</span>
            @else
                <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2"><i class="fa-solid fa-circle-check me-1"></i>Email verified</span>
            @endif

            @if($rp['stage'])
                <span class="text-muted small">
                    @if($rp['stage']['days'] > 0) Renews in {{ $rp['stage']['days'] }} day{{ $rp['stage']['days'] === 1 ? '' : 's' }}
                    @elseif($rp['stage']['days'] === 0) Renews today
                    @elseif($rp['stage']['tone'] === 'grace') In grace
                    @else Expired @endif
                </span>
            @endif

            @if($rp['lastNudged'])
                <span class="text-muted small"><i class="fa-brands fa-whatsapp text-success me-1"></i>Nudged {{ $rp['lastNudged']->diffForHumans() }}</span>
            @endif
        </div>

        @if($rp['canNudge'])
            <form method="POST" action="{{ route('superadmin.accounts.nudge', $account) }}" target="_blank" class="m-0">
                @csrf
                <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 fw-semibold" @if($rp['lastNudged'] && $rp['lastNudged']->gt(now()->subDay())) title="Already nudged in the last 24 hours" @endif>
                    <i class="fa-brands fa-whatsapp me-1"></i>Nudge on WhatsApp
                </button>
            </form>
        @else
            <button type="button" class="btn btn-sm btn-light rounded-pill px-3 fw-semibold" disabled
                    title="{{ $account->owner?->mobile ? 'No renewal coming up' : 'No mobile number on file' }}">
                <i class="fa-brands fa-whatsapp me-1"></i>Nudge on WhatsApp
            </button>
        @endif
    </div>

    @if($rp['logs']->isNotEmpty())
        <div class="border-top px-4 py-2">
            @foreach($rp['logs'] as $log)
                <div class="d-flex flex-wrap align-items-center gap-2 py-1 small">
                    <span class="fw-semibold text-dark" style="min-width:120px;">{{ \App\Models\SubscriptionReminderLog::windowLabel($log->window) }}</span>
                    <span class="text-muted">{{ $log->created_at?->format('d M Y') }}</span>
                    @if($log->status === 'sent')
                        <span class="badge rounded-pill bg-success-subtle text-success"><i class="fa-solid fa-envelope me-1"></i>Emailed</span>
                    @elseif($log->status === 'failed')
                        <span class="badge rounded-pill bg-danger-subtle text-danger" title="{{ $log->detail['error'] ?? '' }}"><i class="fa-solid fa-triangle-exclamation me-1"></i>Email failed · {{ $log->attempts }} {{ $log->attempts === 1 ? 'try' : 'tries' }}</span>
                    @elseif($log->status === 'no_email')
                        <span class="badge rounded-pill bg-secondary-subtle text-secondary">No email</span>
                    @endif
                    <span class="badge rounded-pill bg-primary-subtle text-primary"><i class="fa-solid fa-bell me-1"></i>In app</span>
                    <span class="text-muted ms-auto">for {{ $log->cycle_end?->format('d M Y') }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
