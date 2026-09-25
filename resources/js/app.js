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

    const setupFromTarget = (target) => {
        const input = target instanceof Element
            ? target.closest('[data-client-image-variants]')
            : null;
        if (input) {
            setupImageVariants(input.parentElement || document);
        }
    };

    document.addEventListener('pointerdown', (event) => setupFromTarget(event.target), true);
    document.addEventListener('focusin', (event) => setupFromTarget(event.target));
    document.addEventListener('discipleship:panel-activate', (event) => {
        if (event.target instanceof Element && event.target.querySelector('[data-client-image-variants]')) {
            setupImageVariants(event.target);
        }
    }, true);
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
        bootOptionalModules();
        bootRouteModule();
    }, { once: true });
} else {
    bootOptionalModules();
    bootRouteModule();
}
