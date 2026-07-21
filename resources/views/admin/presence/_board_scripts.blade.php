{{-- Board runtime: a ~20s poll that re-fetches the current URL (filters + page
     preserved) and swaps the live regions, plus client-side tickers so
     durations and "updated Xs ago" age without a round-trip (03 §1). --}}
<script>
    function presenceBoard() {
        return {
            polling: false,
            curfewOpen: false,
            _poll: null, _tick: null,

            init() {
                this.tick();
                this._tick = setInterval(() => this.tick(), 30000);
                this._poll = setInterval(() => { if (!document.hidden) this.poll(); }, 20000);
                // Refresh immediately when returning to the tab (it may be stale).
                document.addEventListener('visibilitychange', () => { if (!document.hidden) this.poll(); });
                document.addEventListener('he:fragment-swapped', () => this.tick());
            },

            // Age durations + the freshness stamp without hitting the server.
            tick() {
                document.querySelectorAll('#pb-list [data-since]').forEach(el => {
                    const t = this.fmtDur(el.dataset.since);
                    if (t) el.textContent = t;
                });
                document.querySelectorAll('#pb-fresh [data-synced]').forEach(el => {
                    el.textContent = this.fmtAgo(el.dataset.synced);
                });
            },

            async poll() {
                if (this.polling) return;
                this.polling = true;
                try {
                    const res = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                    for (const sel of ['#pb-stats', '#pb-fresh', '#pb-list']) {
                        const next = doc.querySelector(sel), cur = document.querySelector(sel);
                        if (next && cur && next.innerHTML !== cur.innerHTML) cur.innerHTML = next.innerHTML;
                    }
                    document.dispatchEvent(new CustomEvent('he:fragment-swapped'));
                    this.tick();
                } catch (e) {
                    /* network blip — the next tick retries; the freshness chip already tells the truth */
                } finally {
                    this.polling = false;
                }
            },

            // Mirror of the PHP $fmtDur so live text matches the server render.
            fmtDur(iso) {
                const s = Math.abs((Date.now() - new Date(iso).getTime()) / 1000);
                const d = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600), m = Math.floor((s % 3600) / 60);
                if (d > 0) return d + 'd ' + h + 'h';
                if (h > 0) return h + 'h ' + m + 'm';
                return Math.max(1, m) + 'm';
            },
            fmtAgo(iso) {
                const s = Math.floor(Math.abs((Date.now() - new Date(iso).getTime()) / 1000));
                if (s < 60) return s + 's ago';
                const m = Math.floor(s / 60);
                if (m < 60) return m + 'm ago';
                const h = Math.floor(m / 60);
                if (h < 24) return h + 'h ago';
                return Math.floor(h / 24) + 'd ago';
            },
        };
    }
</script>
