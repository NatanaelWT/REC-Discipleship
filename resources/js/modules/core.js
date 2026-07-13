const ready = (callback) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
        callback();
    }
};

const isDiscipleshipPage = () => Boolean(
    document.body?.dataset.frontendDomain === 'discipleship'
    ||
    document.body?.matches('[class*="page-discipleship"], [class*="page-msk"], [class*="page-spiritual"], [class*="page-tree"], .page-dg_reports_recap')
    || document.querySelector('[data-discipleship-workspace], [data-discipleship-tab-panel]')
);

function setupClock() {
    const nodes = document.querySelectorAll('[data-live-jakarta-time]');
    if (nodes.length === 0) return;
    const formatter = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
    });
    const update = () => nodes.forEach((node) => { node.textContent = `${formatter.format(new Date())} WIB`; });
    update();
    window.setInterval(update, 1000);
}

function setupSidebar() {
    const shell = document.querySelector('.app-shell');
    const sidebar = shell?.querySelector('.sidebar');
    const toggle = shell?.querySelector('[data-sidebar-toggle]');
    const backdrop = shell?.querySelector('[data-sidebar-backdrop]');
    if (!shell || !sidebar || !toggle || !backdrop) return;
    const mobile = window.matchMedia('(max-width: 1024px)');
    const setOpen = (open) => {
        const active = Boolean(open) && mobile.matches;
        shell.classList.toggle('sidebar-open', active);
        document.body.classList.toggle('sidebar-open', active);
        toggle.setAttribute('aria-expanded', active ? 'true' : 'false');
    };
    toggle.addEventListener('click', () => setOpen(!shell.classList.contains('sidebar-open')));
    backdrop.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (event) => event.key === 'Escape' && setOpen(false));
    sidebar.addEventListener('click', (event) => {
        if (mobile.matches && event.target.closest('a, button[type="submit"]')) setOpen(false);
    });
    const viewportChanged = () => { if (!mobile.matches) setOpen(false); };
    mobile.addEventListener?.('change', viewportChanged);
}

function setupSimpleFilters(scope = document) {
    scope.querySelectorAll('[data-filter]').forEach((control) => {
        if (control.dataset.coreFilterReady === '1') return;
        control.dataset.coreFilterReady = '1';
        const apply = () => {
            const id = control.getAttribute('data-filter');
            const table = id ? document.getElementById(id) : null;
            if (!table) return;
            const controls = document.querySelectorAll(`[data-filter="${CSS.escape(id)}"]`);
            const search = Array.from(controls).find((node) => !node.hasAttribute('data-filter-role'));
            const query = String(search?.value || '').trim().toLocaleLowerCase('id');
            table.querySelectorAll('tbody tr').forEach((row) => {
                let visible = !query || String(row.textContent || '').toLocaleLowerCase('id').includes(query);
                controls.forEach((node) => {
                    const role = node.getAttribute('data-filter-role');
                    const value = String(node.value || 'all').toLocaleLowerCase('id');
                    if (!role || value === '' || value === 'all') return;
                    const tokens = String(row.getAttribute(`data-${role}-filter`) || row.getAttribute(`data-${role}`) || '')
                        .toLocaleLowerCase('id').split(/[\s,|]+/).filter(Boolean);
                    visible = visible && tokens.includes(value);
                });
                row.hidden = !visible;
            });
        };
        control.addEventListener(control.matches('select') ? 'change' : 'input', apply);
    });
}

function setupGenericModals() {
    const close = (modal) => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.toggle('modal-open', Boolean(document.querySelector('.modal.is-open')));
    };
    document.addEventListener('click', (event) => {
        const closer = event.target.closest('[data-modal-close]');
        if (closer) {
            const modal = closer.closest('.modal, [data-modal]');
            if (modal) { event.preventDefault(); close(modal); }
            return;
        }
        if (event.target.matches('.modal.is-open')) close(event.target);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') document.querySelectorAll('.modal.is-open').forEach(close);
    });
}

ready(() => {
    // The compatibility discipleship chunk owns these listeners for one release.
    if (isDiscipleshipPage()) return;
    setupClock();
    setupSidebar();
    setupSimpleFilters();
    setupGenericModals();
});

export { isDiscipleshipPage, ready };
