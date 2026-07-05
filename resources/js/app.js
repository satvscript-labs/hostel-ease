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
    const sidebar = document.querySelector('.hsms-sidebar');
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
});

function initGlobalSearch() {
    const input = document.getElementById('global-search');
    const panel = document.getElementById('search-results');
    if (!input || !panel) return;

    const url = input.closest('[data-search-url]')?.dataset.searchUrl;
    let timer;

    const render = (results) => {
        if (!results.length) {
            panel.innerHTML = '<span class="dropdown-item-text text-muted small">No matches.</span>';
            panel.classList.add('show');
            return;
        }
        let html = '';
        let lastGroup = null;
        results.forEach((r) => {
            if (r.group !== lastGroup) {
                html += `<h6 class="dropdown-header">${r.group}</h6>`;
                lastGroup = r.group;
            }
            html += `<a class="dropdown-item d-flex align-items-center gap-2" href="${r.url}">
                <i class="fa-solid ${r.icon} text-primary"></i>
                <span><span class="fw-semibold">${r.label}</span>
                <small class="text-muted d-block">${r.sub ?? ''}</small></span></a>`;
        });
        panel.innerHTML = html;
        panel.classList.add('show');
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) { panel.classList.remove('show'); return; }
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

