{{-- Shared history-drawer + timeline CSS. Included by the boards and the Gate
     Log so the drawer looks identical wherever it's opened. Page-local by
     concept (only Presence uses .he-drawer / .hist-*). --}}
<style>
    .he-drawer-backdrop {
        position: fixed; inset: 0; z-index: 1055;
        background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(5px);
        opacity: 0; transition: opacity 0.35s var(--ease-out-expo);
    }
    .he-drawer-backdrop.is-open { opacity: 1; }

    .he-drawer {
        position: fixed; top: 0; right: 0; height: 100vh; z-index: 1056;
        width: 470px; max-width: 92vw;
        background: var(--he-bg-surface);
        box-shadow: -24px 0 48px rgba(15, 23, 42, 0.18);
        display: flex; flex-direction: column;
        transform: translateX(100%);
        transition: transform 0.4s var(--ease-out-expo);
    }
    .he-drawer.is-open { transform: translateX(0); }

    .he-drawer__head {
        display: flex; align-items: flex-start; gap: 0.85rem;
        padding: 1.15rem 1.25rem; border-bottom: 1px solid rgba(0,0,0,0.06);
    }
    .he-drawer__body { flex: 1; overflow-y: auto; padding: 1.15rem 1.25rem; background: #fafbfc; }
    .he-drawer__x {
        width: 34px; height: 34px; flex-shrink: 0; border: 0; border-radius: 10px;
        background: var(--he-bg-surface-raised); color: var(--he-text-muted);
        display: flex; align-items: center; justify-content: center;
        transition: all 0.2s var(--ease-out-expo);
    }
    .he-drawer__x:hover { background: var(--he-danger-soft); color: var(--he-danger); }
    .he-drawer__avatar {
        width: 48px; height: 48px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; color: #fff; font-size: 1.05rem;
        background: linear-gradient(135deg, var(--he-primary), var(--he-accent));
    }
    .he-drawer__loading { display: flex; align-items: center; justify-content: center; height: 220px; color: var(--he-text-muted); gap: 0.6rem; }

    @media (max-width: 575.98px) {
        .he-drawer {
            width: 100%; max-width: 100%; top: auto; bottom: 0; height: 90vh;
            border-radius: 20px 20px 0 0; transform: translateY(100%);
        }
        .he-drawer.is-open { transform: translateY(0); }
        .he-drawer__grab { width: 40px; height: 4px; border-radius: 999px; background: var(--he-bg-surface-raised); margin: 0.5rem auto -0.3rem; }
    }
    @media (min-width: 576px) { .he-drawer__grab { display: none; } }

    /* ── Mini-stats inside the drawer ── */
    .hist-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.6rem; margin-bottom: 1.1rem; }
    .hist-stat { background: var(--he-bg-surface); border: 1px solid rgba(0,0,0,0.05); border-radius: var(--he-radius-md); padding: 0.7rem 0.6rem; text-align: center; }
    .hist-stat__v { font-size: 1.15rem; font-weight: 800; color: var(--he-text-main); font-variant-numeric: tabular-nums; }
    .hist-stat__l { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--he-text-muted); }

    /* ── Day-grouped timeline ── */
    .hist-day { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: var(--he-text-muted); margin: 1.1rem 0 0.5rem; }
    .hist-line { position: relative; padding-left: 1.5rem; }
    .hist-line::before { content: ''; position: absolute; left: 7px; top: 4px; bottom: 4px; width: 2px; background: rgba(0,0,0,0.08); }
    .hist-event { position: relative; display: flex; align-items: center; gap: 0.6rem; padding: 0.35rem 0; }
    .hist-event__dot {
        position: absolute; left: -1.5rem; top: 50%; transform: translateY(-50%);
        width: 16px; height: 16px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.55rem; color: #fff;
    }
    .hist-event__dot--in { background: var(--he-success); }
    .hist-event__dot--out { background: var(--he-warning); }
    .hist-event__dot--unknown { background: var(--he-text-muted); }
    .hist-event__time { font-weight: 700; font-variant-numeric: tabular-nums; }
    .hist-event__meta { font-size: 0.76rem; color: var(--he-text-muted); }
    .hist-manual { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; background: var(--he-primary-soft); color: var(--he-primary); padding: 0.1rem 0.4rem; border-radius: 999px; }
    .hist-gap { padding: 0.15rem 0 0.15rem 0; margin-left: 0.1rem; font-size: 0.72rem; color: var(--he-text-muted); font-style: italic; }

    /* Correction form reveal */
    .hist-correct { background: var(--he-bg-surface); border: 1px solid rgba(0,0,0,0.06); border-radius: var(--he-radius-md); padding: 0.9rem; margin-top: 0.5rem; }
</style>
