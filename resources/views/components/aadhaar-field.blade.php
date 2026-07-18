@props(['masked', 'url'])

{{-- Aadhaar display (P5): masked to its last four digits by default; the eye
     toggle fetches the full number from a LOGGED reveal endpoint. No-JS still
     shows the mask. Renders a plain dash when there's no number to reveal. --}}
@if($masked === '—' || blank($masked))
    <span class="text-muted">—</span>
@else
    <span x-data="{
            shown: false, full: null, loading: false,
            async toggle() {
                if (this.shown) { this.shown = false; return; }
                if (this.full) { this.shown = true; return; }
                this.loading = true;
                try {
                    const r = await fetch(@js($url), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (r.ok) { this.full = (await r.json()).aadhaar; this.shown = true; }
                } catch (e) {}
                this.loading = false;
            }
        }" class="d-inline-flex align-items-center gap-2">
        <span style="font-variant-numeric: tabular-nums; letter-spacing: .5px;"
              x-text="shown ? full : @js($masked)">{{ $masked }}</span>
        <button type="button" @click="toggle()" :disabled="loading"
                class="btn btn-sm btn-link p-0 text-muted lh-1 tactile-btn"
                :title="shown ? '{{ __('Hide') }}' : '{{ __('Reveal — this is logged') }}'"
                :aria-label="shown ? '{{ __('Hide Aadhaar') }}' : '{{ __('Reveal Aadhaar (logged)') }}'">
            <i class="fa-solid" :class="loading ? 'fa-spinner fa-spin' : (shown ? 'fa-eye-slash' : 'fa-eye')"></i>
        </button>
    </span>
@endif
