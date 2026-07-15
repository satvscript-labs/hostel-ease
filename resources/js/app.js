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

    // Confirm-on-submit for delete forms
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (e) => {
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
    if (!forms.length) return;

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

    // Back/forward must show the state the URL describes, not a stale list.
    window.addEventListener('popstate', () => window.location.reload());
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

