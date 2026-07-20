{{-- Shared board CSS — included once by both the Students and Staff board pages
     so the classes live in exactly one file (§0.1 spirit) without needing an
     SCSS build. Page-local by concept: only the two board pages use .pb-*. --}}
<style>
    /* ── Freshness chip ── */
    .pb-fresh {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.4rem 0.85rem; border-radius: var(--he-radius-full);
        font-size: 0.76rem; font-weight: 600;
        background: var(--he-bg-surface); border: 1px solid rgba(0,0,0,0.06);
        color: var(--he-text-muted);
    }
    .pb-fresh__dot { width: 8px; height: 8px; border-radius: 50%; position: relative; flex-shrink: 0; }
    .pb-fresh--ok .pb-fresh__dot { background: var(--he-success); }
    .pb-fresh--ok .pb-fresh__dot::after {
        content: ''; position: absolute; inset: 0; border-radius: 50%; background: var(--he-success);
        animation: pb-breathe 2.2s var(--ease-out-expo) infinite;
    }
    .pb-fresh--stale {
        background: var(--he-warning-soft); border-color: rgba(245,158,11,0.3); color: #b45309;
    }
    .pb-fresh--stale .pb-fresh__dot { background: var(--he-warning); }
    @keyframes pb-breathe { 0%{transform:scale(1);opacity:.7} 70%{transform:scale(2.6);opacity:0} 100%{transform:scale(2.6);opacity:0} }

    /* ── State pill (Inside / Out / Unknown) ── */
    .pb-pill {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.3rem 0.75rem; border-radius: var(--he-radius-full);
        font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.03em;
        white-space: nowrap;
    }
    .pb-pill__dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .pb-pill--in  { background: var(--he-success-soft); color: #047857; }
    .pb-pill--in .pb-pill__dot { background: var(--he-success); }
    .pb-pill--out { background: var(--he-warning-soft); color: #b45309; }
    .pb-pill--out .pb-pill__dot { background: var(--he-warning); }
    .pb-pill--unknown { background: var(--he-bg-surface-raised); color: var(--he-text-muted); }
    .pb-pill--unknown .pb-pill__dot { background: var(--he-text-muted); }

    .pb-stale {
        display: inline-flex; align-items: center; gap: 0.3rem;
        padding: 0.15rem 0.5rem; border-radius: var(--he-radius-full);
        font-size: 0.62rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.03em;
        background: var(--he-warning-soft); color: #b45309; margin-left: 0.4rem;
    }

    .pb-dur { font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; color: var(--he-text-main); }
    .pb-last { font-size: 0.8rem; color: var(--he-text-muted); white-space: nowrap; }
    .pb-last b { color: var(--he-text-main); font-weight: 600; }

    .pb-avatar {
        width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; color: #fff; font-size: 0.98rem;
        background: linear-gradient(135deg, var(--he-primary), var(--he-accent));
    }

    /* ── Rows: three tiers (§4.9/4.11). Card holds the OUT accent so a glance
       finds who's out. ── */
    .pb-card {
        border: 0; border-left: 3px solid transparent;
        transition: border-color 0.3s var(--ease-out-expo), box-shadow 0.3s var(--ease-out-expo);
    }
    .pb-card.is-out { border-left-color: var(--he-warning); }
    .pb-card.is-flash { animation: pb-flash 1.4s var(--ease-out-expo); }
    @keyframes pb-flash { 0%{background:var(--he-primary-soft)} 100%{background:var(--he-bg-surface)} }

    /* Tablet band (640–880): a DESIGNED two-line reflow, never a squeezed grid. */
    .pb-row {
        align-items: center; gap: 0.5rem 1rem;
        grid-template-columns: minmax(0,1fr) auto;
        grid-template-areas:
            "id    state"
            "meta  meta";
    }
    .pb-row__id { grid-area: id; display: flex; align-items: center; gap: 0.85rem; min-width: 0; }
    .pb-row__state { grid-area: state; justify-self: end; }
    .pb-row__meta {
        grid-area: meta; display: flex; align-items: center; gap: 1.25rem;
        padding-top: 0.5rem; margin-top: 0.15rem; border-top: 1px solid rgba(0,0,0,0.05);
        min-width: 0;
    }
    @container (min-width: 880px) {
        .pb-row {
            grid-template-columns: minmax(220px,1fr) 150px 150px minmax(160px,1fr);
            grid-template-areas: "id state dur last";
            gap: 0.75rem 1.25rem;
        }
        .pb-row__state { justify-self: start; }
        .pb-row__meta { display: contents; }
        .pb-cell-dur  { grid-area: dur; }
        .pb-cell-last { grid-area: last; }
        .pb-row__meta { border: 0; padding: 0; }
    }
    .pb-cell-dur, .pb-cell-last { min-width: 0; }

    /* Phone card (<640): iOS inset row — one secondary line, status dot, whole
       row taps to the profile. Actions (history/correct) arrive in P4. */
    .pb-ios {
        /* flex-direction:row overrides Bootstrap .card's flex-direction:column
           (both classes are on this <a>), so it's a real iOS row, not a stack. */
        display: flex; flex-direction: row; align-items: center; gap: 0.85rem;
        padding: 0.75rem 0.9rem; text-decoration: none;
    }
    .pb-ios__body { flex: 1; min-width: 0; }
    .pb-ios__name { font-weight: 700; color: var(--he-text-main); }
    .pb-ios__sub { font-size: 0.8rem; color: var(--he-text-muted); }
    .pb-ios__dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
    .pb-ios__dot--in { background: var(--he-success); }
    .pb-ios__dot--out { background: var(--he-warning); box-shadow: 0 0 0 4px var(--he-warning-soft); }
    .pb-ios__dot--unknown { background: var(--he-text-muted); }
    /* .pb-ios sets display:flex, which (loaded after _premium.scss) would beat
       .he-cq-card's `display:none` at ≥640 and show the phone card on desktop
       too. Re-assert the hide at the same 640 container tier the he-cq system
       flips on. */
    @container (min-width: 640px) { .pb-ios { display: none; } }

    @media (prefers-reduced-motion: reduce) {
        .pb-fresh--ok .pb-fresh__dot::after, .pb-card.is-flash { animation: none; }
    }
</style>
