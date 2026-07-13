import { createHash } from 'node:crypto';
import { promises as fs } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import sharp from 'sharp';

const apply = process.argv.includes('--apply');
const rootArgument = process.argv.find((argument) => argument.startsWith('--root='));
const root = path.resolve(rootArgument?.slice('--root='.length) || 'storage/app/private/uploads');
const manifestPath = path.resolve('storage/app/private/media-variants-manifest.json');
const supported = new Set(['.jpg', '.jpeg', '.png', '.webp']);

async function filesIn(directory) {
    const rows = [];
    for (const entry of await fs.readdir(directory, { withFileTypes: true })) {
        const absolute = path.join(directory, entry.name);
        if (entry.isDirectory()) {
            if (!['web', 'thumbnails', 'quarantine'].includes(entry.name.toLowerCase())) {
                rows.push(...await filesIn(absolute));
            }
        } else if (supported.has(path.extname(entry.name).toLowerCase())) {
            rows.push(absolute);
        }
    }

    return rows;
}

async function sha256(file) {
    return createHash('sha256').update(await fs.readFile(file)).digest('hex');
}

async function writeVariant(source, directory, filename, maxSide, quality) {
    const output = path.join(directory, filename);
    if (!apply) return output;

    await fs.mkdir(directory, { recursive: true });
    await sharp(source, { failOn: 'warning', limitInputPixels: 40_000_000 })
        .rotate()
        .resize({ width: maxSide, height: maxSide, fit: 'inside', withoutEnlargement: true })
        .webp({ quality, effort: 5 })
        .toFile(output);

    return output;
}

async function variantInfo(file) {
    const [hash, stat, metadata] = await Promise.all([
        sha256(file),
        fs.stat(file),
        sharp(file, { limitInputPixels: 40_000_000 }).metadata(),
    ]);

    return {
        sha256: hash,
        size: stat.size,
        width: metadata.width || null,
        height: metadata.height || null,
        format: metadata.format || null,
    };
}

async function main() {
    const originals = await filesIn(root);
    const manifest = [];
    for (const original of originals) {
        const hash = await sha256(original);
        const parent = path.dirname(original);
        const web = await writeVariant(original, path.join(parent, 'web'), `web_${hash}.webp`, 1920, 82);
        const thumbnail = await writeVariant(original, path.join(parent, 'thumbnails'), `thumb_${hash}.webp`, 480, 78);
        const metadata = await sharp(original, { limitInputPixels: 40_000_000 }).metadata();
        const [webInfo, thumbnailInfo] = apply
            ? await Promise.all([variantInfo(web), variantInfo(thumbnail)])
            : [null, null];

        manifest.push({
            original: path.relative(path.dirname(root), original).replaceAll('\\', '/'),
            sha256: hash,
            size: (await fs.stat(original)).size,
            width: metadata.width || null,
            height: metadata.height || null,
            web_path: path.relative(path.dirname(root), web).replaceAll('\\', '/'),
            web_sha256: webInfo?.sha256 || null,
            web_size: webInfo?.size || null,
            web_width: webInfo?.width || null,
            web_height: webInfo?.height || null,
            web_format: webInfo?.format || null,
            thumbnail_path: path.relative(path.dirname(root), thumbnail).replaceAll('\\', '/'),
            thumbnail_sha256: thumbnailInfo?.sha256 || null,
            thumbnail_size: thumbnailInfo?.size || null,
            thumbnail_width: thumbnailInfo?.width || null,
            thumbnail_height: thumbnailInfo?.height || null,
            thumbnail_format: thumbnailInfo?.format || null,
            variant_status: apply ? 'ready' : 'dry-run',
        });
    }

    if (apply) {
        await fs.writeFile(manifestPath, `${JSON.stringify({ generated_at: new Date().toISOString(), files: manifest }, null, 2)}\n`);
    }

    process.stdout.write(`${JSON.stringify({ apply, root, files: manifest.length, manifest: apply ? manifestPath : null })}\n`);
}

main().catch((error) => {
    process.stderr.write(`${error.stack || error.message}\n`);
    process.exitCode = 1;
});
