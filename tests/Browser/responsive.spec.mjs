import { expect, test } from '@playwright/test';
import path from 'node:path';

const viewports = [
    { name: 'phone-320', width: 320, height: 568 },
    { name: 'phone-360', width: 360, height: 800 },
    { name: 'phone-390', width: 390, height: 844 },
    { name: 'tablet-portrait', width: 768, height: 1024 },
    { name: 'tablet-landscape', width: 1024, height: 768 },
    { name: 'desktop-1366', width: 1366, height: 768 },
    { name: 'desktop-1440', width: 1440, height: 900 },
];

const publicPages = [
    ['home', '/'],
    ['login', '/login'],
    ['dg-branches', '/publik/jurnal-dg'],
    ['dg-report', '/publik/jurnal-dg/kutisari/laporan'],
    ['feedback-branches', '/publik/umpan-balik-anggota'],
    ['difficult-question', '/publik/pertanyaan-sulit/kirim'],
    ['difficult-answer', '/publik/pertanyaan-sulit/jawaban'],
    ['materials', '/materi/msk'],
];

const internalPages = [
    ['discipleship-dashboard', '/pemuridan/dashboard'],
    ['discipleship-people', '/pemuridan/anggota'],
    ['discipleship-groups', '/pemuridan/kelompok'],
    ['discipleship-tree', '/pemuridan/pohon'],
    ['spiritual-journey', '/pemuridan/spiritual-journey'],
    ['msk', '/pemuridan/msk'],
    ['meeting-reports', '/pemuridan/laporan-dg'],
    ['member-feedback', '/pemuridan/umpan-balik-anggota'],
    ['difficult-questions-admin', '/pemuridan/pertanyaan-sulit'],
    ['targets', '/pemuridan/target'],
    ['settings', '/pengaturan'],
];

const developerPages = [
    ['developer-dashboard', '/developer'],
    ['developer-branches', '/developer/branches'],
    ['developer-users', '/developer/users'],
    ['developer-config', '/developer/config'],
    ['worship', '/ibadah/penatalayan'],
];

async function login(page, username) {
    await goto(page, '/login');
    await page.getByLabel('Username').fill(username);
    await page.getByLabel('Password').fill('responsive-test');
    await Promise.all([
        page.waitForURL((url) => !url.pathname.endsWith('/login')),
        page.getByRole('button', { name: /masuk/i }).click(),
    ]);
}

async function goto(page, url) {
    return page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15_000 });
}

async function assertResponsive(page, label) {
    await page.waitForLoadState('domcontentloaded');
    const result = await page.evaluate(() => {
        const root = document.documentElement;
        const allowedSelector = [
            '.table-wrap',
            '.worship-steward-table-wrap',
            '.member-feedback-recap-group-table-wrap',
            '.member-feedback-recap-table-wrap',
            '.dg-recap-group-report-table-wrap',
            '.tree-group-journal-table-wrap',
            '.discipleship-workspace__tabs',
            '.tree-v2-scroll',
            '.tree-scroll',
            '.journey-inline-track',
            '.dg-recap-calendar-panels',
            '.file-view-image-wrap',
            '.file-view-embed',
        ].join(',');
        const visible = (element) => {
            const style = getComputedStyle(element);
            const rect = element.getBoundingClientRect();
            return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
        };
        const offenders = [];
        for (const element of document.querySelectorAll('main *')) {
            if (!visible(element) || element.closest(allowedSelector)) continue;
            const rect = element.getBoundingClientRect();
            if (rect.right > innerWidth + 2 || rect.left < -2) {
                offenders.push(`${element.tagName.toLowerCase()}.${String(element.className).replaceAll(' ', '.')}`);
            }
            if (offenders.length >= 8) break;
        }
        return {
            documentOverflow: root.scrollWidth - root.clientWidth,
            offenders,
        };
    });

    expect(result.documentOverflow, `${label} has document overflow`).toBeLessThanOrEqual(2);
    expect(result.offenders, `${label} has elements outside the viewport`).toEqual([]);
}

async function assertWideTablesScrollable(page, label) {
    if ((page.viewportSize()?.width ?? 9999) > 720) return;

    const failures = await page.evaluate(async () => {
        const wrapperSelector = [
            '.table-wrap',
            '.worship-steward-table-wrap',
            '.member-feedback-recap-group-table-wrap',
            '.member-feedback-recap-table-wrap',
            '.dg-recap-group-report-table-wrap',
            '.tree-group-journal-table-wrap',
        ].join(',');
        const visible = (element) => {
            const style = getComputedStyle(element);
            const rect = element.getBoundingClientRect();
            return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
        };
        const results = [];
        const auditedTableSelector = [
            '#people-dashboard-table',
            '#groups-dashboard-table',
            '#spiritual-journey-table',
            '#dg-recap-summary-table',
            '.member-feedback-recap-group-table',
        ].join(',');

        for (const table of document.querySelectorAll('table')) {
            if (!visible(table)) continue;
            if (!table.matches(auditedTableSelector) && table.querySelectorAll('thead th').length < 4) continue;
            const wrapper = table.closest(wrapperSelector);
            if (!wrapper) {
                results.push(`${table.id || table.className}: missing scroll wrapper`);
                continue;
            }
            if (table.matches(auditedTableSelector) && wrapper.getAttribute('data-table-horizontal-scroll-ready') !== '1') {
                results.push(`${table.id || table.className}: drag scrolling was not initialized`);
                continue;
            }
            if (wrapper.scrollWidth <= wrapper.clientWidth + 1) {
                results.push(`${table.id || table.className}: no horizontal scroll range`);
                continue;
            }
            const targetScrollLeft = Math.min(48, wrapper.scrollWidth - wrapper.clientWidth);
            wrapper.scrollLeft = targetScrollLeft;
            const immediateScrollLeft = wrapper.scrollLeft;
            await new Promise((resolve) => requestAnimationFrame(resolve));
            if (wrapper.scrollLeft < 1) {
                const style = getComputedStyle(wrapper);
                results.push(`${table.id || table.className}: scroll position did not change (client=${wrapper.clientWidth}, scroll=${wrapper.scrollWidth}, immediate=${immediateScrollLeft}, overflow=${style.overflowX})`);
            }
            wrapper.scrollLeft = 0;
            if (wrapper.hasAttribute('data-table-horizontal-scroll')) {
                const rect = wrapper.getBoundingClientRect();
                wrapper.dispatchEvent(new MouseEvent('mousedown', {
                    bubbles: true,
                    button: 0,
                    clientX: rect.left + Math.min(180, rect.width - 10),
                    clientY: rect.top + 24,
                }));
                window.dispatchEvent(new MouseEvent('mousemove', {
                    bubbles: true,
                    clientX: rect.left + 20,
                    clientY: rect.top + 24,
                }));
                window.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
                if (wrapper.scrollLeft < 1) results.push(`${table.id || table.className}: mouse drag did not scroll`);
                wrapper.scrollLeft = 0;
            }
        }

        return results;
    });

    expect(failures, `${label} has a wide table without usable horizontal scrolling`).toEqual([]);
}

async function openMobileSidebar(page) {
    const toggle = page.locator('[data-sidebar-toggle]');
    if (!await toggle.isVisible()) return;
    await toggle.click();
    const sidebar = page.locator('#app-sidebar');
    await expect(sidebar).toBeVisible();
    await expect.poll(async () => (await sidebar.boundingBox())?.x ?? -999).toBeGreaterThanOrEqual(-1);
    const box = await sidebar.boundingBox();
    expect(box?.x ?? -1).toBeGreaterThanOrEqual(-1);
    expect((box?.x ?? 0) + (box?.width ?? 0)).toBeLessThanOrEqual((await page.viewportSize()).width + 1);
    await page.keyboard.press('Escape');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
}

async function assertMskModal(page) {
    await goto(page, '/pemuridan/msk');
    await page.locator('[data-msk-create-open]').click();
    const modal = page.locator('[data-msk-create-modal]');
    const card = modal.locator('.modal-card');
    await expect(modal).toHaveAttribute('aria-hidden', 'false');
    await expect(card).toBeVisible();
    const box = await card.boundingBox();
    const viewport = page.viewportSize();
    expect(box?.x ?? -1).toBeGreaterThanOrEqual(-1);
    expect(box?.y ?? -1).toBeGreaterThanOrEqual(-1);
    expect((box?.x ?? 0) + (box?.width ?? 0)).toBeLessThanOrEqual(viewport.width + 1);
    expect((box?.y ?? 0) + (box?.height ?? 0)).toBeLessThanOrEqual(viewport.height + 1);
    await modal.locator('[data-msk-create-close]').first().click();
    await expect(modal).toHaveAttribute('aria-hidden', 'true');
}

for (const viewport of viewports) {
    test.describe(viewport.name, () => {
        test.use({ viewport: { width: viewport.width, height: viewport.height } });
        const exhaustive = viewport.width === 320 || viewport.width === 1440;

        test('public pages stay within the viewport', async ({ page }) => {
            const pages = exhaustive ? publicPages : publicPages.slice(0, 3);
            for (const [name, url] of pages) {
                const response = await goto(page, url);
                expect(response?.status(), `${name} response`).toBeLessThan(400);
                await assertResponsive(page, name);
                await assertWideTablesScrollable(page, name);
            }
            await goto(page, '/');
            await page.screenshot({
                path: path.join('test-results', 'responsive-screenshots', `${viewport.name}-public-home.png`),
                fullPage: true,
            });
        });

        test('discipleship pages stay within the viewport', async ({ page }) => {
            await login(page, 'responsive_branch');
            const pages = exhaustive
                ? internalPages
                : internalPages.filter(([name]) => ['discipleship-dashboard', 'discipleship-tree', 'msk'].includes(name));
            for (const [name, url] of pages) {
                const response = await goto(page, url);
                expect(response?.status(), `${name} response`).toBeLessThan(400);
                await assertResponsive(page, name);
                await assertWideTablesScrollable(page, name);
                if (viewport.width === 320 && [
                    'discipleship-people',
                    'discipleship-groups',
                    'spiritual-journey',
                    'meeting-reports',
                    'member-feedback',
                ].includes(name)) {
                    await page.screenshot({
                        path: path.join('test-results', 'responsive-screenshots', `${viewport.name}-${name}.png`),
                        fullPage: true,
                    });
                }
            }
            await assertMskModal(page);
            if (viewport.width <= 1024) await openMobileSidebar(page);
            await goto(page, '/pemuridan/dashboard');
            await page.screenshot({
                path: path.join('test-results', 'responsive-screenshots', `${viewport.name}-dashboard.png`),
                fullPage: true,
            });
        });

        test('developer and worship pages stay within the viewport', async ({ page }) => {
            await login(page, 'responsive_developer');
            const pages = exhaustive
                ? developerPages
                : developerPages.filter(([name]) => ['developer-dashboard', 'worship'].includes(name));
            for (const [name, url] of pages) {
                const response = await goto(page, url);
                expect(response?.status(), `${name} response`).toBeLessThan(400);
                await assertResponsive(page, name);
                await assertWideTablesScrollable(page, name);
            }
        });

        test('central REC pages stay within the viewport', async ({ page }) => {
            test.skip(!exhaustive);
            await login(page, 'responsive_central');
            for (const [name, url] of [
                ['central-dashboard', '/pemuridan/dashboard'],
                ['central-journey', '/pemuridan/spiritual-journey'],
                ['central-settings', '/pengaturan'],
            ]) {
                const response = await goto(page, url);
                expect(response?.status(), `${name} response`).toBeLessThan(400);
                await assertResponsive(page, name);
                await assertWideTablesScrollable(page, name);
            }
            if (viewport.width <= 1024) await openMobileSidebar(page);
        });

        test('steward pages stay within the viewport', async ({ page }) => {
            test.skip(!exhaustive);
            await login(page, 'responsive_steward');
            for (const [name, url] of [
                ['steward-worship', '/ibadah/penatalayan'],
                ['steward-settings', '/pengaturan'],
            ]) {
                const response = await goto(page, url);
                expect(response?.status(), `${name} response`).toBeLessThan(400);
                await assertResponsive(page, name);
                await assertWideTablesScrollable(page, name);
            }
            if (viewport.width <= 1024) await openMobileSidebar(page);
        });
    });
}
