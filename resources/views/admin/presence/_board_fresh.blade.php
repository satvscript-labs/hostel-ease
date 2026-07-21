{{-- Freshness chip — honest about the PIPELINE's freshness, not the browser's.
     Green + breathing when a sync landed recently; amber banner when the device
     is unreachable (03 §1). "Xs ago" ticks client-side. Swapped by the poll. --}}
@if($freshness['devices'] === 0)
    <span class="pb-fresh pb-fresh--stale">
        <span class="pb-fresh__dot"></span>
        {{ __('No gate device set up yet — add one on the Devices page.') }}
    </span>
@elseif($freshness['ok'])
    <span class="pb-fresh pb-fresh--ok">
        <span class="pb-fresh__dot"></span>
        {{ __('Updated') }} <span data-synced="{{ $freshness['synced_at']->toIso8601String() }}">{{ $freshness['synced_at']->diffForHumans() }}</span>
        · {{ $freshness['online'] }}/{{ $freshness['devices'] }} {{ __('online') }}
    </span>
@else
    <span class="pb-fresh pb-fresh--stale">
        <span class="pb-fresh__dot"></span>
        {{ __('Gate device unreachable — showing last known state') }}
        @if($freshness['synced_at'])(<span data-synced="{{ $freshness['synced_at']->toIso8601String() }}">{{ $freshness['synced_at']->diffForHumans() }}</span>)@endif
    </span>
@endif
