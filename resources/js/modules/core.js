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
    let returnFocus = null;
    const focusableSelector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(',');
    const focusable = () => Array.from(sidebar.querySelectorAll(focusableSelector)).filter((node) => {
        const style = getComputedStyle(node);
        const rect = node.getBoundingClientRect();
        return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
    });
    sidebar.setAttribute('tabindex', '-1');
    const setOpen = (open) => {
        const active = Boolean(open) && mobile.matches;
        if (active && !shell.classList.contains('sidebar-open')) {
            returnFocus = toggle;
        }
        shell.classList.toggle('sidebar-open', active);
        document.body.classList.toggle('sidebar-open', active);
        toggle.setAttribute('aria-expanded', active ? 'true' : 'false');
        sidebar.setAttribute('aria-hidden', active || !mobile.matches ? 'false' : 'true');
        if (active) {
            const focusSidebar = () => {
                if (shell.classList.contains('sidebar-open')) (focusable()[0] || sidebar).focus?.();
            };
            focusSidebar();
            window.requestAnimationFrame(focusSidebar);
            window.setTimeout(focusSidebar, 260);
            window.setTimeout(focusSidebar, 1000);
        } else {
            const target = returnFocus instanceof HTMLElement && returnFocus.isConnected ? returnFocus : toggle;
            target.focus();
            returnFocus = null;
        }
    };
    sidebar.setAttribute('aria-hidden', mobile.matches ? 'true' : 'false');
    toggle.addEventListener('mousedown', (event) => {
        if (mobile.matches && !shell.classList.contains('sidebar-open')) event.preventDefault();
    });
    toggle.addEventListener('click', () => setOpen(!shell.classList.contains('sidebar-open')));
    backdrop.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (event) => {
        if (!shell.classList.contains('sidebar-open')) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
            return;
        }
        if (event.key !== 'Tab') return;
        const items = focusable();
        if (items.length === 0) {
            event.preventDefault();
            sidebar.focus?.();
            return;
        }
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
    sidebar.addEventListener('click', (event) => {
        if (mobile.matches && event.target.closest('a, button[type="submit"]')) setOpen(false);
    });
    const viewportChanged = () => {
        if (!mobile.matches) {
            setOpen(false);
        } else if (!shell.classList.contains('sidebar-open')) {
            sidebar.setAttribute('aria-hidden', 'true');
        }
    };
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

function setupTableScrollAccessibility(scope = document) {
    const areas = scope.querySelectorAll(
        '.table-wrap, .worship-steward-table-wrap, .member-feedback-recap-table-wrap, .dg-recap-group-report-table-wrap, .tree-group-journal-table-wrap',
    );
    areas.forEach((area) => {
        if (area.getAttribute('data-table-scroll-a11y-ready') === '1') return;
        const table = area.querySelector('table');
        if (!table) return;
        area.setAttribute('data-table-scroll-a11y-ready', '1');
        area.setAttribute('data-table-horizontal-scroll', '');
        if (!area.hasAttribute('tabindex')) area.setAttribute('tabindex', '0');
        if (!area.hasAttribute('role')) area.setAttribute('role', 'region');
        if (!area.hasAttribute('aria-label')) {
            const caption = table.querySelector('caption')?.textContent?.trim();
            const label = table.getAttribute('aria-label') || caption || 'Tabel';
            area.setAttribute('aria-label', `${label}. Geser untuk melihat kolom lain.`);
        }
        if (!area.hasAttribute('aria-describedby')) {
            const hint = document.createElement('span');
            const hintId = `table-scroll-hint-${Math.random().toString(36).slice(2, 10)}`;
            hint.id = hintId;
            hint.className = 'table-scroll-hint';
            hint.setAttribute('data-table-scroll-hint', '');
            hint.textContent = 'Gunakan tombol panah kiri dan kanan untuk melihat kolom lain.';
            area.appendChild(hint);
            area.setAttribute('aria-describedby', hintId);
        }
        let dragging = false;
        let moved = false;
        let startX = 0;
        let startLeft = 0;
        const finish = () => {
            if (!dragging) return;
            dragging = false;
            area.classList.remove('is-dragging');
            if (moved) {
                area.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                }, { capture: true, once: true });
            }
        };
        area.addEventListener('mousedown', (event) => {
            if (event.button !== 0 || event.target.closest('button, a, input, select, textarea, label')) return;
            dragging = true;
            moved = false;
            startX = event.clientX;
            startLeft = area.scrollLeft;
            area.classList.add('is-dragging');
        });
        window.addEventListener('mousemove', (event) => {
            if (!dragging) return;
            const delta = event.clientX - startX;
            if (Math.abs(delta) > 3) moved = true;
            area.scrollLeft = startLeft - delta;
        });
        window.addEventListener('mouseup', finish);
        area.addEventListener('mouseleave', finish);
        area.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            const maxScroll = Math.max(0, area.scrollWidth - area.clientWidth);
            if (maxScroll <= 0) return;
            const step = Math.max(48, Math.round(area.clientWidth * 0.72));
            const target = event.key === 'Home'
                ? 0
                : event.key === 'End'
                    ? maxScroll
                    : area.scrollLeft + (event.key === 'ArrowRight' ? step : -step);
            event.preventDefault();
            area.scrollTo({ left: Math.max(0, Math.min(maxScroll, target)), behavior: 'auto' });
        });
    });
}

function setupGenericModals() {
    const focusableSelector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(',');
    const returnFocus = new WeakMap();
    const focusable = (modal) => Array.from(modal.querySelectorAll(focusableSelector)).filter((node) => {
        const style = getComputedStyle(node);
        const rect = node.getBoundingClientRect();
        return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
    });
    const focusFirst = (modal) => {
        const first = focusable(modal)[0];
        if (first) {
            first.focus();
        } else {
            if (!modal.hasAttribute('tabindex')) modal.setAttribute('tabindex', '-1');
            modal.focus();
        }
    };
    const restore = (modal) => {
        const opener = returnFocus.get(modal);
        returnFocus.delete(modal);
        if (opener instanceof HTMLElement && opener.isConnected) {
            window.requestAnimationFrame(() => opener.focus());
        }
    };
    const close = (modal) => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.toggle('modal-open', Boolean(document.querySelector('.modal.is-open')));
        restore(modal);
    };
    const syncOpenModalFocus = (modal) => {
        if (modal.getAttribute('aria-hidden') !== 'false' || !modal.classList.contains('is-open')) return;
        if (!returnFocus.has(modal) && document.activeElement instanceof HTMLElement && !modal.contains(document.activeElement)) {
            returnFocus.set(modal, document.activeElement);
        }
        window.requestAnimationFrame(() => {
            if (modal.classList.contains('is-open') && !modal.contains(document.activeElement)) focusFirst(modal);
        });
    };
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type !== 'attributes') return;
            const modal = mutation.target;
            if (!(modal instanceof HTMLElement)) return;
            if (modal.classList.contains('is-open') && modal.getAttribute('aria-hidden') === 'false') {
                syncOpenModalFocus(modal);
            } else if (modal.getAttribute('aria-hidden') === 'true' && returnFocus.has(modal)) {
                restore(modal);
            }
        });
    });
    const observeModal = (modal) => {
        if (!(modal instanceof HTMLElement) || modal.getAttribute('data-core-focus-ready') === '1') return;
        modal.setAttribute('data-core-focus-ready', '1');
        observer.observe(modal, { attributes: true, attributeFilter: ['class', 'aria-hidden'] });
    };
    document.querySelectorAll('.modal, [data-modal]').forEach(observeModal);
    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
            if (!(node instanceof HTMLElement)) return;
            if (node.matches('.modal, [data-modal]')) observeModal(node);
            node.querySelectorAll?.('.modal, [data-modal]').forEach(observeModal);
        }));
    }).observe(document.body, { childList: true, subtree: true });
    document.addEventListener('focusin', (event) => {
        const modal = document.querySelector('.modal.is-open, [data-modal].is-open');
        if (modal && !modal.contains(event.target)) focusFirst(modal);
    });
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
        const modal = Array.from(document.querySelectorAll('.modal.is-open, [data-modal].is-open')).at(-1);
        if (!modal) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            close(modal);
            return;
        }
        if (event.key !== 'Tab') return;
        const items = focusable(modal);
        if (items.length === 0) {
            event.preventDefault();
            focusFirst(modal);
            return;
        }
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
}

ready(() => {
    // The compatibility discipleship chunk owns these listeners for one release.
    if (isDiscipleshipPage()) return;
    setupClock();
    setupSidebar();
    setupSimpleFilters();
    setupTableScrollAccessibility();
    setupGenericModals();
});

export { isDiscipleshipPage, ready };
