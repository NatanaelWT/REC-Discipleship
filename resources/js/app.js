import { isDiscipleshipPage } from './modules/core.js';

let imageVariantModule;

const setupImageVariants = (root = document) => {
    if (!imageVariantModule) {
        imageVariantModule = import('./modules/image-variants.js');
    }

    return imageVariantModule.then((module) => {
            const setup = module.setupClientImageVariants || module.default;
            if (typeof setup === 'function') {
                setup(root);
            }
        }).catch(() => {
            // Upload tetap dapat memakai original bila optimasi browser tidak tersedia.
        });
};

const bootOptionalModules = () => {
    if (document.querySelector('[data-client-image-variants]')) {
        setupImageVariants(document);
    }

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof Element)) {
                    return;
                }
                if (node.matches('[data-client-image-variants]')) {
                    setupImageVariants(node.parentElement || document);
                } else if (node.querySelector('[data-client-image-variants]')) {
                    setupImageVariants(node);
                }
            });
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });
};

const bootSidebarFocusFallback = () => {
    const shell = document.querySelector('.app-shell');
    const sidebar = shell?.querySelector('.sidebar');
    const toggle = shell?.querySelector('[data-sidebar-toggle]');
    if (!shell || !sidebar || !toggle || toggle.dataset.sidebarFocusFallback === '1') return;
    toggle.dataset.sidebarFocusFallback = '1';
    sidebar.setAttribute('tabindex', '-1');
    const focusSidebar = () => {
        if (toggle.getAttribute('aria-expanded') !== 'true') return;
        const first = sidebar.querySelector('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled])');
        (first || sidebar).focus();
    };
    const retrySidebarFocus = () => {
        focusSidebar();
        const stopAt = Date.now() + 600;
        const timer = window.setInterval(() => {
            if (toggle.getAttribute('aria-expanded') !== 'true' || Date.now() >= stopAt) {
                window.clearInterval(timer);
                return;
            }
            if (!sidebar.contains(document.activeElement)) focusSidebar();
        }, 50);
    };
    new MutationObserver(() => {
        if (toggle.getAttribute('aria-expanded') === 'true') window.setTimeout(retrySidebarFocus, 0);
    }).observe(toggle, { attributes: true, attributeFilter: ['aria-expanded'] });
    toggle.addEventListener('click', () => {
        window.requestAnimationFrame(retrySidebarFocus);
        window.setTimeout(retrySidebarFocus, 260);
    });
};

const bootRouteModule = () => {
    const body = document.body;
    if (!body) return;

    if (body.dataset.frontendDomain === 'worship' || body.matches('[class*="page-worship"], .page-worship_penatalayan')) {
        import('./modules/worship.js');
    }

    // The established feature-rich script is retained for discipleship only
    // during the compatibility release. Other domains never request this chunk.
    if (isDiscipleshipPage()) {
        import('../../public/assets/app.js');
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        bootSidebarFocusFallback();
        bootOptionalModules();
        bootRouteModule();
    }, { once: true });
} else {
    bootSidebarFocusFallback();
    bootOptionalModules();
    bootRouteModule();
}
