import { ready } from './core.js';

ready(() => {
    const modal = document.querySelector('[data-worship-attendance-modal]');
    if (!modal) return;
    const title = modal.querySelector('[data-worship-attendance-title]');
    const body = modal.querySelector('[data-worship-attendance-body]');
    const templates = new Map();
    document.querySelectorAll('template[data-worship-attendance-template]').forEach((template) => {
        templates.set(template.getAttribute('data-worship-attendance-template') || '', template);
    });
    const close = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    };
    const open = (key) => {
        const template = templates.get(String(key || ''));
        if (!template) return;
        if (title) title.textContent = template.getAttribute('data-worship-attendance-template-title') || 'Isi Kehadiran';
        if (body) body.innerHTML = template.innerHTML;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        body?.querySelector('input, select, textarea')?.focus();
    };
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-worship-attendance-open]');
        if (trigger) { event.preventDefault(); open(trigger.getAttribute('data-worship-attendance-open')); }
    });
    modal.addEventListener('click', (event) => {
        if (event.target === modal || event.target.closest('[data-worship-attendance-close]')) {
            event.preventDefault(); close();
        }
    });
    document.addEventListener('keydown', (event) => event.key === 'Escape' && close());
    open(modal.getAttribute('data-worship-attendance-auto-open'));
});
