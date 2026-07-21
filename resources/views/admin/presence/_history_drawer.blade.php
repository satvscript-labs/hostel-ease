{{-- History drawer shell — a teleported right-side slide-over (phone: bottom
     sheet). Any row can open it: dispatch `presence-history` on window with
     { profile: <public_id> }. It fetches the body from the history route.
     Included on the boards and the Gate Log. --}}
<template x-teleport="body">
    <div x-data="historyDrawer()" @presence-history.window="open($event.detail.profile)" @close-history.window="close()">
        <div class="he-drawer-backdrop" :class="{ 'is-open': shown }" x-show="shown" x-cloak
             @click="close()" style="display:none;"></div>

        <aside class="he-drawer" :class="{ 'is-open': shown }" x-show="shown" x-cloak
               role="dialog" aria-label="{{ __('Presence history') }}" @keydown.escape.window="close()" style="display:none;">
            <div class="he-drawer__grab d-sm-none"></div>
            <div id="he-drawer-content">
                <div class="he-drawer__loading">
                    <i class="fa-solid fa-spinner fa-spin"></i> {{ __('Loading history…') }}
                </div>
            </div>
        </aside>
    </div>
</template>

<script>
    function historyDrawer() {
        return {
            shown: false,
            base: @js(url('admin/presence/history')),
            open(profileId) {
                this.shown = true;
                document.body.style.overflow = 'hidden';
                const box = document.getElementById('he-drawer-content');
                box.innerHTML = '<div class="he-drawer__loading"><i class="fa-solid fa-spinner fa-spin"></i> {{ __('Loading history…') }}</div>';
                fetch(`${this.base}/${profileId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.ok ? r.text() : Promise.reject())
                    .then(html => { box.innerHTML = html; if (window.Alpine) Alpine.initTree(box); })
                    .catch(() => { box.innerHTML = '<div class="he-drawer__body text-center text-muted py-5">{{ __('Could not load history.') }}</div>'; });
            },
            close() {
                this.shown = false;
                document.body.style.overflow = '';
            },
        };
    }
</script>
