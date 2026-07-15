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

async function assertRefreshButtonJoinsWorkspace(page, label) {
    const result = await page.evaluate(() => {
        const tabbar = document.querySelector('.discipleship-workspace__tabbar');
        const refresh = tabbar?.querySelector('[data-discipleship-tab-refresh]');
        const panels = document.querySelector('[data-discipleship-panels]');
        if (!tabbar || !refresh || !panels) return null;

        const tabbarRect = tabbar.getBoundingClientRect();
        const refreshRect = refresh.getBoundingClientRect();
        const panelsRect = panels.getBoundingClientRect();
        const refreshStyle = getComputedStyle(refresh);

        return {
            refreshBottomDelta: Math.abs(refreshRect.bottom - tabbarRect.bottom),
            panelsTopDelta: Math.abs(panelsRect.top - tabbarRect.bottom),
            borderBottomWidth: refreshStyle.borderBottomWidth,
            refreshHeight: refreshRect.height,
        };
    });

    expect(result, `${label} refresh button`).not.toBeNull();
    expect(result.refreshBottomDelta, `${label} refresh bottom alignment`).toBeLessThanOrEqual(1);
    expect(result.panelsTopDelta, `${label} tabbar and panel alignment`).toBeLessThanOrEqual(1);
    expect(result.borderBottomWidth, `${label} refresh bottom seam`).toBe('0px');
    expect(result.refreshHeight, `${label} refresh height`).toBe(38);
}

async function dgStatesByName(page, rowSelector, nameSelector, stepSelector) {
    return page.locator(rowSelector).evaluateAll((rows, selectors) => {
        const statesByName = {};
        rows.forEach((row) => {
            const name = String(row.querySelector(selectors.nameSelector)?.textContent || '').trim();
            const states = Array.from(row.querySelectorAll(selectors.stepSelector))
                .map((step) => String(step.textContent || '').trim());
            if (name !== '' && states.length === 3) statesByName[name] = states;
        });

        return statesByName;
    }, { nameSelector, stepSelector });
}

async function assertSpiritualJourneyDgPresentation(page) {
    const firstRow = page.locator('[data-spiritual-journey-search-row]').first();
    await expect(firstRow).toBeVisible();
    await expect(firstRow.locator('.journey-msk-step')).toHaveCount(1);
    await expect(firstRow.locator('.journey-msk-step strong')).toHaveText('MSK');
    await expect(firstRow.locator('.journey-msk-step small')).not.toHaveText('');
    await expect(firstRow.locator('.journey-bridge-step')).toHaveCount(1);
    await expect(firstRow.locator('.journey-bridge-step strong')).toHaveText('RG / KGAP');
    await expect(firstRow.locator('.journey-bridge-select')).toHaveCount(1);

    const dgSteps = firstRow.locator('.journey-dg-step');
    await expect(dgSteps).toHaveCount(3);
    expect(await dgSteps.locator('strong').allTextContents()).toEqual(['DG 1', 'DG 2', 'DG 3']);
    const dgStates = await dgSteps.locator('small').allTextContents();
    expect(dgStates).toHaveLength(3);
    dgStates.forEach((state) => expect(['Selesai', 'Sedang', 'Terhenti', 'Belum']).toContain(state));
    await expect(firstRow.locator([
        '.journey-inline-track > .journey-track-badge.is-msk',
        '.journey-inline-track > .journey-track-badge.is-dg1',
        '.journey-inline-track > .journey-track-badge.is-dg2',
        '.journey-inline-track > .journey-track-badge.is-dg3',
    ].join(','))).toHaveCount(0);

    const stageCards = firstRow.locator('.journey-msk-step, .journey-dg-step, .journey-bridge-step');
    await expect(stageCards).toHaveCount(5);
    const geometries = await stageCards.evaluateAll((cards) => cards.map((card) => {
        const style = getComputedStyle(card);
        return {
            minHeight: style.minHeight,
            borderRadius: style.borderRadius,
            width: card.getBoundingClientRect().width,
        };
    }));
    geometries.forEach((geometry) => {
        expect(geometry.minHeight).toBe('42px');
        expect(geometry.borderRadius).toBe('7px');
        expect(geometry.width).toBeGreaterThanOrEqual(108);
    });

    return dgStatesByName(
        page,
        '[data-spiritual-journey-search-row]',
        '.journey-name-main',
        '.journey-dg-step small',
    );
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

async function tableLayout(page, panelSelector, cardSelector, wrapperSelector) {
    return page.evaluate(({ panelSelector, cardSelector, wrapperSelector }) => {
        const panel = document.querySelector(panelSelector);
        const card = panel?.querySelector(cardSelector);
        const wrapper = panel?.querySelector(wrapperSelector);
        const table = wrapper?.querySelector('table');
        const header = wrapper?.querySelector('thead th');
        if (!panel || !card || !wrapper || !table || !header) return null;
        const panelStyle = getComputedStyle(panel);
        const cardStyle = getComputedStyle(card);
        const wrapperStyle = getComputedStyle(wrapper);
        const tableStyle = getComputedStyle(table);
        const headerStyle = getComputedStyle(header);
        const panelRect = panel.getBoundingClientRect();
        const cardRect = card.getBoundingClientRect();
        const wrapperRect = wrapper.getBoundingClientRect();
        return {
            panelDisplay: panelStyle.display,
            panelDirection: panelStyle.flexDirection,
            panelOverflowX: panelStyle.overflowX,
            panelOverflowY: panelStyle.overflowY,
            cardDisplay: cardStyle.display,
            cardDirection: cardStyle.flexDirection,
            cardPadding: cardStyle.padding,
            cardMarginBottom: cardStyle.marginBottom,
            cardOverflow: cardStyle.overflow,
            cardInsetLeft: Math.round(cardRect.left - panelRect.left),
            cardWidth: Math.round(cardRect.width),
            wrapperInsetLeft: Math.round(wrapperRect.left - cardRect.left),
            wrapperWidth: Math.round(wrapperRect.width),
            wrapperOverflowX: wrapperStyle.overflowX,
            wrapperOverflowY: wrapperStyle.overflowY,
            wrapperMaxHeight: wrapperStyle.maxHeight,
            tableMinWidth: tableStyle.minWidth,
            headerPosition: headerStyle.position,
            headerTop: headerStyle.top,
        };
    }, { panelSelector, cardSelector, wrapperSelector });
}

async function assertWorkspaceUsesDocumentScroll(page, label, panelSelector) {
    const result = await page.evaluate((selector) => {
        const panel = document.querySelector(selector);
        const panelsHost = panel?.closest('[data-discipleship-panels]');
        const scrollRoot = document.scrollingElement;
        if (!panel || !panelsHost || !scrollRoot) return null;

        const nestedSelectors = [
            '[data-discipleship-people-scroll]',
            '[data-discipleship-groups-scroll]',
            '[data-msk-scroll]',
            '[data-spiritual-journey-scroll]',
            '[data-dg-recap-summary-scroll]',
            '[data-member-feedback-summary-scroll]',
            '.tree-v2-scroll',
            '.discipleship-overdue-list',
        ].join(',');
        const nestedVerticalScrollers = Array.from(panel.querySelectorAll(nestedSelectors))
            .filter((element) => {
                const style = getComputedStyle(element);
                const rect = element.getBoundingClientRect();
                return style.display !== 'none'
                    && rect.height > 0
                    && element.scrollHeight > element.clientHeight + 1;
            })
            .map((element) => element.getAttribute('data-discipleship-people-scroll') !== null
                ? 'people-table'
                : element.getAttribute('data-discipleship-groups-scroll') !== null
                    ? 'groups-table'
                    : element.className || element.tagName.toLowerCase());

        const panelStyle = getComputedStyle(panel);
        const hostStyle = getComputedStyle(panelsHost);
        return {
            panelOverflowY: panelStyle.overflowY,
            panelMaxHeight: panelStyle.maxHeight,
            panelScrollRange: panel.scrollHeight - panel.clientHeight,
            hostOverflowY: hostStyle.overflowY,
            hostScrollRange: panelsHost.scrollHeight - panelsHost.clientHeight,
            documentScrollRange: scrollRoot.scrollHeight - scrollRoot.clientHeight,
            nestedVerticalScrollers,
        };
    }, panelSelector);

    expect(result, `${label} workspace state`).not.toBeNull();
    expect(result.panelOverflowY, `${label} panel overflow`).toBe('visible');
    expect(result.panelMaxHeight, `${label} panel max height`).toBe('none');
    expect(result.panelScrollRange, `${label} panel vertical scroll range`).toBeLessThanOrEqual(1);
    expect(result.hostOverflowY, `${label} panels host overflow`).toBe('visible');
    expect(result.hostScrollRange, `${label} panels host vertical scroll range`).toBeLessThanOrEqual(1);
    expect(result.nestedVerticalScrollers, `${label} nested vertical scrollers`).toEqual([]);

    return result.documentScrollRange;
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
            let peopleDgStates = {};
            for (const [name, url] of pages) {
                const response = await goto(page, url);
                expect(response?.status(), `${name} response`).toBeLessThan(400);
                await assertResponsive(page, name);
                await assertWideTablesScrollable(page, name);
                if (await page.locator('[data-discipleship-tab-refresh]').count()) {
                    await assertRefreshButtonJoinsWorkspace(page, name);
                }
                if (name === 'discipleship-people') {
                    peopleDgStates = await dgStatesByName(
                        page,
                        '[data-discipleship-people-search-row]',
                        '.people-name-main',
                        '.people-progress-step small',
                    );
                    expect(Object.keys(peopleDgStates).length).toBeGreaterThan(0);
                }
                if (name === 'spiritual-journey') {
                    const journeyDgStates = await assertSpiritualJourneyDgPresentation(page);
                    Object.entries(peopleDgStates).forEach(([personName, states]) => {
                        expect(journeyDgStates[personName], `${personName} DG states`).toEqual(states);
                    });
                }
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
                if (name === 'spiritual-journey' && viewport.width === 320) {
                    const journeyTable = page.locator('[data-spiritual-journey-scroll]');
                    await journeyTable.evaluate((element) => {
                        element.scrollLeft = element.scrollWidth;
                    });
                    await page.screenshot({
                        path: path.join('test-results', 'responsive-screenshots', `${viewport.name}-${name}-track.png`),
                        fullPage: true,
                    });
                }
                if (name === 'spiritual-journey' && viewport.width === 1440) {
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

        test('discipleship tabs use document scrolling', async ({ page }) => {
            test.skip(!exhaustive);
            await login(page, 'responsive_branch');

            for (const workspace of [
                {
                    url: '/pemuridan/dashboard',
                    tabs: [
                        ['dashboard', '#discipleship-tabpanel-dashboard'],
                        ['people', '#discipleship-tabpanel-people'],
                        ['groups', '#discipleship-tabpanel-groups'],
                        ['tree', '#discipleship-tabpanel-tree'],
                    ],
                },
                {
                    url: '/pemuridan/spiritual-journey',
                    tabs: [
                        ['spiritual', '#discipleship-tabpanel-spiritual'],
                        ['msk', '#discipleship-tabpanel-msk'],
                    ],
                },
                {
                    url: '/pemuridan/laporan-dg',
                    tabs: [
                        ['meeting', '#discipleship-tabpanel-meeting'],
                        ['feedback', '#discipleship-tabpanel-feedback'],
                    ],
                },
            ]) {
                await goto(page, workspace.url);

                for (const [index, [key, panelSelector]] of workspace.tabs.entries()) {
                    if (index > 0) {
                        await page.locator(`[data-discipleship-tab][data-tab-key="${key}"]`).click();
                    }
                    await expect(page.locator(panelSelector)).toBeVisible();
                    await expect(page.locator('[data-discipleship-panels] > [data-discipleship-tab-panel]:visible')).toHaveCount(1);
                    await page.evaluate(() => window.scrollTo(0, 0));
                    const documentScrollRange = await assertWorkspaceUsesDocumentScroll(page, key, panelSelector);
                    if (key === 'dashboard') {
                        expect(documentScrollRange, 'dashboard document scroll range').toBeGreaterThan(0);
                        await page.evaluate(() => window.scrollTo(0, document.scrollingElement.scrollHeight));
                        expect(await page.evaluate(() => window.scrollY), 'dashboard window scroll position').toBeGreaterThan(0);
                        await page.evaluate(() => window.scrollTo(0, 0));
                    }
                }
            }

            for (const [label, url, listSelector, rowSelector] of [
                [
                    'people',
                    '/pemuridan/anggota?limit=2',
                    '[data-discipleship-people-list]',
                    '[data-discipleship-people-search-row]',
                ],
                [
                    'spiritual',
                    '/pemuridan/spiritual-journey?limit=2',
                    '[data-spiritual-journey-list]',
                    '[data-spiritual-journey-search-row]',
                ],
                [
                    'msk',
                    '/pemuridan/msk?limit=2',
                    '[data-msk-list]',
                    '[data-msk-search-row]',
                ],
            ]) {
                await goto(page, url);
                const list = page.locator(listSelector);
                await expect(list, `${label} lazy list`).toHaveAttribute('data-limit', '2');
                await expect.poll(async () => {
                    await page.evaluate(() => window.scrollTo(0, document.scrollingElement.scrollHeight));
                    return list.getAttribute('data-has-more');
                }, { message: `${label} cursor completed by document scroll` }).toBe('0');
                expect(
                    await page.locator(rowSelector).count(),
                    `${label} loaded more than the initial row limit`,
                ).toBeGreaterThan(2);
            }
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

        test('journal tables match across AJAX, refresh, and direct navigation', async ({ page }) => {
            test.skip(!exhaustive);
            await login(page, 'responsive_branch');
            await goto(page, '/pemuridan/laporan-dg');

            const meetingLayout = await tableLayout(
                page,
                '#discipleship-tabpanel-meeting',
                '.dg-recap-section-card',
                '[data-dg-recap-summary-scroll]',
            );
            expect(meetingLayout).not.toBeNull();

            await goto(page, '/pemuridan/spiritual-journey');
            const spiritualJourneyLayout = await tableLayout(
                page,
                '#discipleship-tabpanel-spiritual',
                '.table-card-plain',
                '[data-spiritual-journey-scroll]',
            );
            expect(spiritualJourneyLayout).toEqual(meetingLayout);

            await goto(page, '/pemuridan/laporan-dg');

            const feedbackFragmentResponse = page.waitForResponse((response) => (
                new URL(response.url()).pathname.endsWith('/pemuridan/umpan-balik-anggota')
                && response.request().headers()['x-discipleship-fragment'] === 'tab'
            ));
            await page.locator('[data-discipleship-tab][data-tab-key="feedback"]').click();
            expect((await feedbackFragmentResponse).status()).toBe(200);
            const feedbackPanel = page.locator('#discipleship-tabpanel-feedback');
            await expect(feedbackPanel).toBeVisible();
            await expect(page.locator('#discipleship-tabpanel-meeting')).toBeHidden();
            await expect(page.locator('[data-discipleship-panels] > [data-discipleship-tab-panel]:visible')).toHaveCount(1);
            await expect(feedbackPanel.locator('#member-feedback-recap-group-table')).toBeVisible();
            const feedbackFromTab = await tableLayout(
                page,
                '#discipleship-tabpanel-feedback',
                '.member-feedback-recap-group-card',
                '[data-member-feedback-summary-scroll]',
            );
            expect(feedbackFromTab).toEqual(meetingLayout);
            const feedbackTable = feedbackPanel.locator('#member-feedback-recap-group-table');
            const feedbackMarkupFromTab = await feedbackTable.evaluate((table) => table.outerHTML);
            const staleFeedbackTable = await feedbackTable.elementHandle();

            const feedbackRefreshResponse = page.waitForResponse((response) => (
                new URL(response.url()).pathname.endsWith('/pemuridan/umpan-balik-anggota')
                && response.request().headers()['x-discipleship-fragment'] === 'tab'
            ));
            await page.locator('[data-discipleship-tab-refresh]').click();
            expect((await feedbackRefreshResponse).status()).toBe(200);
            await expect(feedbackPanel).toBeVisible();
            expect(await staleFeedbackTable.evaluate((table) => table.isConnected)).toBe(false);
            const feedbackAfterRefresh = await tableLayout(
                page,
                '#discipleship-tabpanel-feedback',
                '.member-feedback-recap-group-card',
                '[data-member-feedback-summary-scroll]',
            );
            const feedbackMarkupAfterRefresh = await feedbackPanel
                .locator('#member-feedback-recap-group-table')
                .evaluate((table) => table.outerHTML);
            expect(feedbackAfterRefresh).toEqual(meetingLayout);
            expect(feedbackMarkupAfterRefresh).toBe(feedbackMarkupFromTab);

            await goto(page, '/pemuridan/umpan-balik-anggota');
            const feedbackDirect = await tableLayout(
                page,
                '#discipleship-tabpanel-feedback',
                '.member-feedback-recap-group-card',
                '[data-member-feedback-summary-scroll]',
            );
            const feedbackMarkupDirect = await page
                .locator('#discipleship-tabpanel-feedback #member-feedback-recap-group-table')
                .evaluate((table) => table.outerHTML);

            expect(feedbackDirect).toEqual(meetingLayout);
            expect(feedbackMarkupDirect).toBe(feedbackMarkupFromTab);
            await page.screenshot({
                path: path.join('test-results', 'responsive-screenshots', `${viewport.name}-feedback-direct.png`),
                fullPage: true,
            });

            await page.locator('[data-discipleship-tab][data-tab-key="meeting"]').click();
            await expect(page.locator('#discipleship-tabpanel-meeting')).toBeVisible();
            await expect(page.locator('#discipleship-tabpanel-feedback')).toBeHidden();
            await expect(page.locator('[data-discipleship-panels] > [data-discipleship-tab-panel]:visible')).toHaveCount(1);
        });
    });
}
