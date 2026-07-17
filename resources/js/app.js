import './bootstrap-imports';

// Sets window.jQuery BEFORE the jQuery plugins below are imported, so they
// attach correctly (fixes "$(...).select2 is not a function").
import $ from './jquery-global';
import * as bootstrap from 'bootstrap';
import Swal from 'sweetalert2';
import Chart from 'chart.js/auto';
import 'datatables.net-bs5';
import 'select2';

window.bootstrap = bootstrap;
window.Swal = Swal;
window.Chart = Chart;

document.addEventListener('DOMContentLoaded', () => {
    // Off-canvas sidebar (mobile): toggle + backdrop + body lock + auto-close
    const sidebar = document.querySelector('.he-sidebar');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const openSidebar = () => {
        sidebar?.classList.add('show');
        backdrop?.classList.add('show');
        document.body.classList.add('sidebar-open');
    };
    const closeSidebar = () => {
        sidebar?.classList.remove('show');
        backdrop?.classList.remove('show');
        document.body.classList.remove('sidebar-open');
    };
    document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', (e) => {
        e.stopPropagation();
        sidebar?.classList.contains('show') ? closeSidebar() : openSidebar();
    });
    document.querySelector('[data-sidebar-close]')?.addEventListener('click', closeSidebar);
    backdrop?.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', (e) => e.key === 'Escape' && closeSidebar());
    // Tapping a nav link on mobile closes the drawer
    sidebar?.querySelectorAll('.nav-link').forEach((a) =>
        a.addEventListener('click', () => { if (window.innerWidth < 992) closeSidebar(); }));
    // Reset state if resized up to desktop
    window.addEventListener('resize', () => { if (window.innerWidth >= 992) closeSidebar(); });

    // Initialise DataTables (guarded so a plugin issue can't break the page)
    try {
        $('table[data-datatable]').each(function () {
            $(this).DataTable({ responsive: true, pageLength: 25, order: [] });
        });
    } catch (e) {
        console.error('DataTables init failed:', e);
    }

    // Initialise Select2 (guarded)
    try {
        $('select[data-select2]').select2({ theme: 'bootstrap-5', width: '100%' });
    } catch (e) {
        console.error('Select2 init failed:', e);
    }

    // Bootstrap tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]')
        .forEach((el) => new bootstrap.Tooltip(el));

    // Confirm-on-submit for delete forms. DELEGATED, not bound per-form:
    // the per-form version only covered forms that existed at page load, so
    // any delete button inside fragment-swapped content (a filtered list, a
    // pagination page) submitted with NO confirmation — money-destroying
    // clicks went straight through. One document-level listener covers every
    // form forever, including ones that don't exist yet. (form.submit() on
    // confirm is deliberate: it skips the submit event, so this listener
    // doesn't re-intercept its own confirmation.)
    document.addEventListener('submit', (e) => {
        const form = e.target.closest('form[data-confirm]');
        if (!form) return;
        e.preventDefault();
        Swal.fire({
            title: 'Are you sure?',
            text: form.dataset.confirm || 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, proceed',
        }).then((r) => r.isConfirmed && form.submit());
    });

    // Flash messages -> toast
    const flash = window.hostelease_FLASH;
    if (flash && flash.message) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: flash.type || 'info',
            title: flash.message,
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
        });
    }

    // Global instant search
    initGlobalSearch();

    // Idle session timeout (minutes from config, injected per-page)
    const timeoutMin = window.hostelease_SESSION_TIMEOUT;
    if (timeoutMin) {
        let timer;
        const reset = () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                document.getElementById('logout-form')?.submit();
            }, timeoutMin * 60 * 1000);
        };
        ['click', 'keydown', 'mousemove', 'scroll', 'touchstart']
            .forEach((evt) => document.addEventListener(evt, reset, { passive: true }));
        reset();
    }

    initFragmentForms();
    initRequiredRings();
    initFitLabels();
});

/**
 * Fragment filtering — re-render only the list a filter affects, never the page.
 *
 * A filter that does `this.form.submit()` throws away the whole document to
 * change a few rows. Everything not in the HTML dies with it: scroll position,
 * every Alpine scope (open dropdowns, typed search text, active tab), focus, and
 * the entrance animations all replay. It reads as a lurch, and on a slow link
 * the screen is blank in between.
 *
 * This keeps the document alive: fetch the same URL, take only the target
 * element(s) out of the response, swap them in. Everything outside the target is
 * untouched, so state and scroll survive by construction.
 *
 * Usage — the form is a normal GET form (works with JS off; this is progressive
 * enhancement, not a replacement):
 *
 *   <form method="GET" data-fragment="#visitor-list"> ... </form>
 *   <div id="visitor-list"> ...server-rendered rows... </div>
 *
 * `data-fragment` takes one or more CSS selectors (comma-separated) that must
 * exist in BOTH the current page and the response.
 *
 * Anchors participate too (added W6.1 for pagination + "clear filters"):
 *
 *   <a href="?page=2" ...>            inside an element with [data-fragment-container]
 *                                     and a Laravel .page-link class → swaps that
 *                                     container in place (pagination without reload)
 *   <a href="..." data-fragment="#a"> explicit targets, same semantics as the form
 *
 * Modified clicks (ctrl/cmd/shift/middle) are left alone so open-in-new-tab
 * keeps working — the href is a real URL precisely so it can.
 *
 * Notes:
 * - Requires controls to submit via requestSubmit(), NOT submit(): the DOM's
 *   form.submit() deliberately does not fire a 'submit' event, so it would sail
 *   straight past this listener and hard-navigate. <x-he-select> uses
 *   requestSubmit() for this reason.
 * - The URL is kept in sync via pushState, so the filtered view stays
 *   shareable/bookmarkable and Back works (popstate re-syncs).
 * - Alpine auto-initialises swapped-in DOM (it observes mutations), and since
 *   the target sits inside the page's existing x-data root, new rows resolve
 *   parent scope normally.
 */
function initFragmentForms() {
    const forms = document.querySelectorAll('form[data-fragment]');
    const hasContainers = document.querySelector('[data-fragment-container]');
    if (!forms.length && !hasContainers) return;

    // One in-flight request per target group. Filters fire fast (a click, then
    // another click); without this, a slow first response can land AFTER a fast
    // second one and show the wrong list — the classic out-of-order swap.
    const inFlight = new Map();

    const swap = async (selectors, url, key) => {
        inFlight.get(key)?.abort();
        const controller = new AbortController();
        inFlight.set(key, controller);

        const targets = selectors.map((s) => document.querySelector(s)).filter(Boolean);
        targets.forEach((t) => t.classList.add('is-fragment-loading'));

        try {
            const res = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
            selectors.forEach((sel) => {
                const fresh = doc.querySelector(sel);
                const current = document.querySelector(sel);
                if (fresh && current) current.innerHTML = fresh.innerHTML;
            });
            window.history.pushState({ fragment: true }, '', url);

            // Alpine re-initialises swapped nodes itself, but plain-JS
            // enhancements (fit-labels, and anything added later) have no way
            // to know a list just got replaced. Announce it.
            document.dispatchEvent(new CustomEvent('he:fragment-swapped', { detail: { selectors, url } }));
        } catch (err) {
            if (err.name === 'AbortError') return; // superseded by a newer filter
            // Never strand the user on a half-updated list — fall back to the
            // full navigation they'd have got without this enhancement.
            window.location.assign(url);
            return;
        } finally {
            inFlight.delete(key);
            targets.forEach((t) => t.classList.remove('is-fragment-loading'));
        }
    };

    forms.forEach((form, i) => {
        const selectors = form.dataset.fragment.split(',').map((s) => s.trim()).filter(Boolean);
        if (!selectors.length) return;
        const key = `fragment-${i}`;

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const query = new URLSearchParams(new FormData(form)).toString();
            const action = form.getAttribute('action') || window.location.pathname;
            swap(selectors, query ? `${action}?${query}` : action, key);
        });
    });

    // Anchors: pagination links inside a [data-fragment-container] (Laravel's
    // .page-link), and any anchor that declares data-fragment targets itself.
    document.addEventListener('click', (e) => {
        // Respect open-in-new-tab / window: only plain left clicks are ours.
        if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;

        const a = e.target.closest('a[href]');
        if (!a || a.target === '_blank' || a.origin !== window.location.origin) return;

        let selectors = null;
        let key = null;

        if (a.dataset.fragment) {
            selectors = a.dataset.fragment.split(',').map((s) => s.trim()).filter(Boolean);
            key = `link-${a.dataset.fragment}`;
        } else if (a.classList.contains('page-link')) {
            // Only pagination is intercepted inside a container — content links
            // (student profiles etc.) must stay real navigations.
            const container = a.closest('[data-fragment-container]');
            if (!container || !container.id) return;
            selectors = [`#${container.id}`];
            key = `container-${container.id}`;
        }

        if (!selectors || !selectors.length) return;
        e.preventDefault();
        swap(selectors, a.href, key);
    });

    // Back/forward must show the state the URL describes, not a stale list.
    window.addEventListener('popstate', () => window.location.reload());
}

/**
 * ── Dynamic menu placement ───────────────────────────────────────────────
 *
 * A dropdown must open into the space that EXISTS, never into space the page
 * has to grow to provide. An absolutely-positioned menu that runs past the
 * viewport edge extends the document, and the browser answers with a
 * scrollbar — vertical is merely ugly, horizontal drags the whole layout
 * sideways and can strand the menu off-screen entirely.
 *
 * So: measure, then place. No space on the right → open to the left. No space
 * below → open above. Doesn't fit either way → clamp it and let the menu
 * itself scroll. The menu never decides the page's size.
 *
 * Call on open (after the menu is visible, so it can be measured) and it
 * re-places itself on scroll/resize until it's hidden again.
 *
 * @param {HTMLElement} trigger  the control the menu belongs to
 * @param {HTMLElement} menu     the absolutely-positioned menu itself
 */
export function placeMenu(trigger, menu) {
    if (!trigger || !menu) return;

    const GAP = 8; // matches the CSS `top: calc(100% + 0.5rem)`
    const EDGE = 8; // keep this much clear of the edge

    /**
     * The box the menu must actually fit inside.
     *
     * The viewport is NOT it. A menu inside a scrolling container (a modal
     * body, any overflow:auto panel) is clipped by that container long before
     * the viewport — so measuring the viewport says "loads of room below",
     * the menu opens down, gets cut off, and the container grows a scrollbar:
     * exactly the thing §4.7 forbids, just one box in.
     *
     * So: walk up to the nearest clipping ancestor and intersect its rect
     * with the viewport. No clipping ancestor → the viewport IS the box.
     */
    const availableBox = () => {
        let box = {
            top: 0,
            left: 0,
            right: document.documentElement.clientWidth,
            bottom: document.documentElement.clientHeight,
        };

        for (let el = menu.parentElement; el && el !== document.body; el = el.parentElement) {
            const style = getComputedStyle(el);
            const clips = /(auto|scroll|hidden|clip)/.test(style.overflowY + style.overflowX);
            if (!clips) continue;

            const r = el.getBoundingClientRect();
            box = {
                top: Math.max(box.top, r.top),
                left: Math.max(box.left, r.left),
                right: Math.min(box.right, r.right),
                bottom: Math.min(box.bottom, r.bottom),
            };
            break; // nearest clipper wins; anything above it can only be looser
        }

        return box;
    };

    const place = () => {
        // Reset first: measure the DEFAULT placement, not wherever the last
        // pass left it, or the decisions compound and the menu walks away.
        menu.style.top = '';
        menu.style.bottom = '';
        menu.style.left = '';
        menu.style.right = '';
        menu.style.maxWidth = '';
        menu.style.maxHeight = '';
        menu.style.overflowY = '';

        const box = availableBox();
        const t = trigger.getBoundingClientRect();
        let m = menu.getBoundingClientRect();

        // ── Horizontal ──
        if (m.right > box.right - EDGE) {
            // Try right-aligning to the trigger (menu grows leftward).
            menu.style.left = 'auto';
            menu.style.right = '0';
            m = menu.getBoundingClientRect();

            // Still out? It's wider than the space on either side — pin it
            // inside the box and cap the width.
            if (m.left < box.left + EDGE) {
                menu.style.right = 'auto';
                menu.style.left = `${box.left + EDGE - t.left}px`;
                menu.style.maxWidth = `${box.right - box.left - EDGE * 2}px`;
            }
        }

        // ── Vertical ── (measured against the box's own edges, not the
        // viewport's — inside a modal body neither edge is at 0.)
        m = menu.getBoundingClientRect();
        const spaceBelow = box.bottom - t.bottom - GAP - EDGE;
        const spaceAbove = t.top - box.top - GAP - EDGE;

        if (m.height > spaceBelow && spaceAbove > spaceBelow) {
            // More room up top — flip above the trigger.
            menu.style.top = 'auto';
            menu.style.bottom = `calc(100% + ${GAP}px)`;
            if (m.height > spaceAbove) {
                menu.style.maxHeight = `${Math.max(spaceAbove, 120)}px`;
                menu.style.overflowY = 'auto';
            }
        } else if (m.height > spaceBelow) {
            // Below is the better of two bad options — clamp and scroll.
            menu.style.maxHeight = `${Math.max(spaceBelow, 120)}px`;
            menu.style.overflowY = 'auto';
        }
    };

    place();

    // Re-place while open; self-unsubscribe once the menu is hidden (Alpine's
    // x-show sets display:none, which zeroes offsetParent).
    if (menu._hePlacing) return;
    menu._hePlacing = true;

    const reposition = (event) => {
        if (!menu.isConnected || menu.offsetParent === null) {
            window.removeEventListener('resize', reposition);
            window.removeEventListener('scroll', reposition, true);
            menu._hePlacing = false;
            return;
        }

        // A scroll INSIDE the menu is the menu doing exactly what place() just
        // asked of it (clamp the height, scroll the content). Re-placing on it
        // is fatal, and silently so:
        //
        //   place() clears max-height to re-measure the default position → the
        //   menu briefly fits its own content → nothing overflows → the browser
        //   clamps scrollTop to 0 → max-height goes back on, scrollTop stays 0.
        //
        // Every wheel tick therefore snapped the list back to the top, so any
        // menu tall enough to need scrolling could not be scrolled AT ALL. The
        // scrollbar was right there, and dragging it did nothing. The capture
        // listener is what makes this reachable: scroll events don't bubble,
        // but capture sees them on the way DOWN to any target, menu included.
        //
        // Only re-place when something ELSE moved the trigger.
        if (event?.target instanceof Node && menu.contains(event.target)) return;

        place();
    };

    window.addEventListener('resize', reposition);
    window.addEventListener('scroll', reposition, true); // capture: catches scrolling ancestors too
}

window.hePlaceMenu = placeMenu;

/**
 * ── Fit-or-drop labels ───────────────────────────────────────────────────
 *
 * <button data-fit-label> with a [data-fit-optional] part inside: when the
 * full text can't fit on one line, the optional part is dropped rather than
 * wrapped. "₹ Collect ₹1,000.00" becomes "Collect" — never two lines, never a
 * half-clipped "₹ Collect ₹1,0…".
 *
 * Measured, not guessed: a media query can't know how long an amount is, and
 * ₹1,000 and ₹1,25,000 don't need the same room.
 */
function fitLabel(el) {
    el.classList.remove('is-fit-short');
    // scrollWidth > clientWidth only means anything while the content is
    // nowrap + overflow:hidden — that's what .is-fit-* CSS guarantees.
    if (el.scrollWidth > el.clientWidth + 1) el.classList.add('is-fit-short');
}

function initFitLabels() {
    if (!('ResizeObserver' in window)) return;

    const observer = new ResizeObserver((entries) => {
        entries.forEach((entry) => fitLabel(entry.target));
    });

    const scan = () => {
        document.querySelectorAll('[data-fit-label]').forEach((el) => {
            fitLabel(el);
            observer.observe(el); // observing twice is a no-op
        });
    };

    scan();
    // Fragment swaps replace whole lists — the new buttons need observing too.
    document.addEventListener('he:fragment-swapped', scan);
}

/**
 * ── Attention rings ──────────────────────────────────────────────────────
 *
 * The one gesture the app uses to point at a control the user must deal with.
 * Two meanings, identical motion (see .he-ring in _premium.scss):
 *
 *   'primary'  a DEPENDENCY — "answer this first and what you just reached
 *              for will work". Fired by hand from a component, e.g. clicking
 *              a picker that can't filter until a fee type is chosen.
 *   'danger'   a MANDATORY field left empty at submit. Fired automatically
 *              for any <form data-ring-required>.
 *
 * Exposed as window.heRing so Alpine components can fire the dependency case
 * without importing anything.
 *
 * Why re-add on a rAF: re-applying a class the element already has does NOT
 * restart a CSS animation. Ring, ring again 200ms later, and nothing would
 * happen — the second click would look broken. Removing, forcing a frame,
 * then re-adding is what makes it retriggerable.
 */
const RING_MS = 2400; // 1.1s × 2 iterations + the longest stagger, then clean up.

export function ringElements(els, tone = 'danger') {
    const list = Array.from(els).filter(Boolean);
    if (!list.length) return;

    const toneClass = `he-ring--${tone}`;

    list.forEach((el, i) => {
        el.classList.remove('he-ring', 'he-ring--primary', 'he-ring--danger');
        clearTimeout(el._heRingTimer);

        requestAnimationFrame(() => {
            // Stagger reads as one sweep across a group rather than a flash.
            el.style.setProperty('--he-ring-delay', `${i * 90}ms`);
            el.classList.add('he-ring', toneClass);

            el._heRingTimer = setTimeout(() => {
                el.classList.remove('he-ring', toneClass);
                el.style.removeProperty('--he-ring-delay');
            }, RING_MS + i * 90);
        });
    });
}

window.heRing = ringElements;

/**
 * <form data-ring-required> — replaces the browser's native validation bubble
 * with the app's own red ring on every empty mandatory field at once.
 *
 * The bubble points at ONE field, is unstyleable, and vanishes on the next
 * click; a modal with three empty required fields makes you discover them one
 * submit at a time. This marks them all, staggered, and focuses the first.
 *
 * Hooked to 'invalid', NOT 'submit': when constraint validation fails the
 * browser never fires a submit event at all, so a submit listener would sit
 * there doing nothing. 'invalid' fires once per failing control just before
 * the bubble, and preventDefault() on it suppresses that bubble. This also
 * means no novalidate attribute is needed (forget it and native validation
 * silently wins), and it needs no init-time sweep — so forms that Alpine
 * teleports into <body> after boot are covered for free.
 *
 * 'invalid' doesn't bubble, hence capture: the capture phase still walks down
 * through document to the target, so one listener catches every form.
 *
 * The events arrive one per control, so they're batched into a microtask and
 * rung together — otherwise each field would start its own un-staggered ring.
 */
function initRequiredRings() {
    const pending = new Set();
    let scheduled = false;

    document.addEventListener(
        'invalid',
        (e) => {
            const form = e.target.form;
            if (!form || !form.hasAttribute('data-ring-required')) return;

            e.preventDefault(); // drop the native bubble; the ring replaces it
            pending.add(e.target);

            if (scheduled) return;
            scheduled = true;
            queueMicrotask(() => {
                const els = Array.from(pending);
                pending.clear();
                scheduled = false;

                ringElements(els, 'danger');
                els[0]?.focus({ preventScroll: false });
            });
        },
        true,
    );
}

function initGlobalSearch() {
    const input = document.getElementById('global-search');
    const panel = document.getElementById('search-results');
    if (!input || !panel) return;

    const url = input.closest('[data-search-url]')?.dataset.searchUrl;
    let timer;

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));

    const render = (results) => {
        if (!results.length) {
            panel.innerHTML = `<div class="he-search-empty">
                <i class="fa-solid fa-magnifying-glass"></i>
                <span>No matches found.</span>
            </div>`;
            panel.classList.add('show');
            return;
        }
        let html = '';
        let lastGroup = null;
        results.forEach((r) => {
            if (r.group !== lastGroup) {
                html += `<div class="he-search-group">${esc(r.group)}</div>`;
                lastGroup = r.group;
            }
            html += `<a class="he-search-item" href="${esc(r.url)}">
                <span class="he-search-ic"><i class="fa-solid ${esc(r.icon)}"></i></span>
                <span class="he-search-text">
                    <span class="he-search-label">${esc(r.label)}</span>
                    <small class="he-search-sub">${esc(r.sub)}</small>
                </span>
                <i class="fa-solid fa-arrow-right he-search-go"></i>
            </a>`;
        });
        panel.innerHTML = html;
        panel.classList.add('show');
    };

    const renderLoading = () => {
        let html = '';
        for (let i = 0; i < 3; i++) {
            html += `<div class="he-search-item is-loading">
                <span class="he-search-ic skeleton"></span>
                <span class="he-search-text">
                    <span class="skeleton d-block" style="width:55%;height:12px;border-radius:4px;"></span>
                    <span class="skeleton d-block" style="width:35%;height:9px;border-radius:4px;margin-top:6px;"></span>
                </span>
            </div>`;
        }
        panel.innerHTML = html;
        panel.classList.add('show');
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) { panel.classList.remove('show'); return; }
        renderLoading();
        timer = setTimeout(() => {
            window.axios.get(url, { params: { q } })
                .then((res) => render(res.data.results || []))
                .catch(() => panel.classList.remove('show'));
        }, 250);
    });

    document.addEventListener('click', (e) => {
        if (!panel.contains(e.target) && e.target !== input) panel.classList.remove('show');
    });
}

// Register the PWA service worker.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

