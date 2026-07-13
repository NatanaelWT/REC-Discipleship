import { copyFile, mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import postcss from 'postcss';

const projectRoot = process.cwd();
const sourcePath = path.join(projectRoot, 'public/assets/style.css');
const outputDirectory = path.join(projectRoot, 'resources/css/generated');
const licenseDirectory = path.join(projectRoot, 'public/assets/fonts/licenses');
const domains = ['core', 'public', 'discipleship', 'developer', 'worship'];

const domainPatterns = {
    public: [
        /(?:^|[^a-z0-9_-])page-login(?:[^a-z0-9_-]|$)/i,
        /(?:^|[^a-z0-9_-])login-/i,
        /(?:^|[^a-z0-9_-])page-public/i,
        /(?:^|[^a-z0-9_-])public-/i,
        /(?:^|[^a-z0-9_-])page-dg-public/i,
        /(?:^|[^a-z0-9_-])dg-public-/i,
    ],
    developer: [
        /(?:^|[^a-z0-9_-])page-developer/i,
        /(?:^|[^a-z0-9_-])developer-/i,
        /(?:^|[^a-z0-9_-])page-activities/i,
        /(?:^|[^a-z0-9_-])activity-/i,
        /(?:^|[^a-z0-9_-])page-analytics/i,
        /(?:^|[^a-z0-9_-])analytics-/i,
    ],
    worship: [
        /(?:^|[^a-z0-9_-])page-worship/i,
        /(?:^|[^a-z0-9_-])worship-/i,
        /(?:^|[^a-z0-9_-])penatalayan/i,
    ],
    discipleship: [
        /(?:^|[^a-z0-9_-])page-discipleship/i,
        /(?:^|[^a-z0-9_-])discipleship-/i,
        /(?:^|[^a-z0-9_-])page-msk/i,
        /(?:^|[^a-z0-9_-])msk-/i,
        /(?:^|[^a-z0-9_-])page-spiritual/i,
        /(?:^|[^a-z0-9_-])spiritual-/i,
        /(?:^|[^a-z0-9_-])journey-/i,
        /(?:^|[^a-z0-9_-])page-tree/i,
        /(?:^|[^a-z0-9_-])tree(?:-|\b)/i,
        /(?:^|[^a-z0-9_-])dg-recap/i,
        /(?:^|[^a-z0-9_-])dg-section/i,
        /(?:^|[^a-z0-9_-])dg-checklist/i,
        /(?:^|[^a-z0-9_-])dg-member/i,
        /(?:^|[^a-z0-9_-])dg-rating/i,
        /(?:^|[^a-z0-9_-])member-feedback/i,
        /(?:^|[^a-z0-9_-])people-progress/i,
        /(?:^|[^a-z0-9_-])people-dashboard/i,
        /(?:^|[^a-z0-9_-])groups-dashboard/i,
        /(?:^|[^a-z0-9_-])members-table/i,
        /(?:^|[^a-z0-9_-])central-rekap/i,
        /(?:^|[^a-z0-9_-])page-dg_reports/i,
    ],
};

const fontFaces = `/* Local variable fonts. Licenses: /assets/fonts/licenses/ */
@font-face {
  font-family: "Manrope";
  font-style: normal;
  font-display: swap;
  font-weight: 200 800;
  src: url("@fontsource-variable/manrope/files/manrope-latin-wght-normal.woff2") format("woff2-variations");
}

@font-face {
  font-family: "Fraunces";
  font-style: normal;
  font-display: swap;
  font-weight: 100 900;
  src: url("@fontsource-variable/fraunces/files/fraunces-latin-wght-normal.woff2") format("woff2-variations");
}

`;

function selectorDomain(selector) {
    for (const domain of ['public', 'developer', 'worship', 'discipleship']) {
        if (domainPatterns[domain].some((pattern) => pattern.test(selector))) {
            return domain;
        }
    }

    return 'core';
}

function splitContainer(container) {
    const output = Object.fromEntries(domains.map((domain) => [domain, postcss.root()]));

    for (const node of container.nodes || []) {
        if (node.type === 'rule') {
            const selectorsByDomain = Object.fromEntries(domains.map((domain) => [domain, []]));
            for (const selector of node.selectors || [node.selector]) {
                selectorsByDomain[selectorDomain(selector)].push(selector);
            }
            for (const domain of domains) {
                if (selectorsByDomain[domain].length === 0) {
                    continue;
                }
                const clone = node.clone();
                clone.selectors = selectorsByDomain[domain];
                output[domain].append(clone);
            }
            continue;
        }

        if (node.type === 'atrule' && node.nodes && !/^(?:font-face|keyframes|-webkit-keyframes|property)$/i.test(node.name)) {
            const splitChildren = splitContainer(node);
            for (const domain of domains) {
                if (splitChildren[domain].nodes.length === 0) {
                    continue;
                }
                const clone = node.clone({ nodes: [] });
                splitChildren[domain].each((child) => clone.append(child.clone()));
                output[domain].append(clone);
            }
            continue;
        }

        // Variables, font declarations, keyframes, and license comments are shared infrastructure.
        output.core.append(node.clone());
    }

    return output;
}

async function copyFontLicenses() {
    await mkdir(licenseDirectory, { recursive: true });
    const licenses = [
        ['@fontsource-variable/manrope/LICENSE', 'Manrope-OFL-1.1.txt'],
        ['@fontsource-variable/fraunces/LICENSE', 'Fraunces-OFL-1.1.txt'],
    ];
    for (const [packagePath, fileName] of licenses) {
        const source = path.join(projectRoot, 'node_modules', ...packagePath.split('/'));
        await copyFile(source, path.join(licenseDirectory, fileName));
    }
}

const source = await readFile(sourcePath, 'utf8');
const split = splitContainer(postcss.parse(source, { from: sourcePath }));
await mkdir(outputDirectory, { recursive: true });

for (const domain of domains) {
    const header = `/* Generated from public/assets/style.css by scripts/split-frontend-assets.mjs. */\n`;
    const css = `${header}${domain === 'core' ? fontFaces : ''}${split[domain].toString()}\n`;
    await writeFile(path.join(outputDirectory, `${domain}.css`), css, 'utf8');
}

await copyFontLicenses();

const summary = {};
for (const domain of domains) {
    const css = await readFile(path.join(outputDirectory, `${domain}.css`));
    summary[domain] = css.byteLength;
}
process.stdout.write(`Split CSS bytes: ${JSON.stringify(summary)}\n`);
