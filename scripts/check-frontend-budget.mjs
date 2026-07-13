import { readFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { gzipSync } from 'node:zlib';

const root = process.cwd();
const buildDirectory = path.join(root, 'public/build');
const manifest = JSON.parse(await readFile(path.join(buildDirectory, 'manifest.json'), 'utf8'));
const requiredEntries = ['resources/css/generated/core.css', 'resources/js/app.js'];
const domainEntries = ['public', 'discipleship', 'developer', 'worship']
    .map((domain) => `resources/css/generated/${domain}.css`);
for (const entry of domainEntries) {
    if (!manifest[entry]?.isEntry) throw new Error(`Route CSS is not an independent entry: ${entry}`);
}
if (manifest['public/assets/app.js']?.isEntry || !manifest['public/assets/app.js']?.isDynamicEntry) {
    throw new Error('The legacy/domain-heavy JavaScript must remain a lazy-only chunk.');
}
if (!manifest['resources/js/app.js']?.dynamicImports?.includes('public/assets/app.js')) {
    throw new Error('The route-aware loader no longer references the discipleship compatibility chunk.');
}
const files = requiredEntries.map((entry) => {
    const file = manifest[entry]?.file;
    if (!file) throw new Error(`Frontend budget entry missing from manifest: ${entry}`);
    return { entry, file };
});

let rawBytes = 0;
let gzipBytes = 0;
for (const item of files) {
    const content = await readFile(path.join(buildDirectory, item.file));
    item.raw = content.byteLength;
    item.gzip = gzipSync(content, { level: 9 }).byteLength;
    rawBytes += item.raw;
    gzipBytes += item.gzip;
}

const rawLimit = 150 * 1024;
const gzipLimit = 45 * 1024;
process.stdout.write(`${JSON.stringify({ files, totals: { rawBytes, gzipBytes, rawLimit, gzipLimit } }, null, 2)}\n`);
if (rawBytes > rawLimit || gzipBytes > gzipLimit) {
    throw new Error(`Core frontend budget exceeded: ${rawBytes} raw / ${gzipBytes} gzip bytes`);
}
