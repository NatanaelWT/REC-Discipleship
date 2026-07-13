const WEB_MAX_SIDE = 1920;
const THUMBNAIL_MAX_SIDE = 480;
const WEB_MAX_BYTES = 2 * 1024 * 1024;
const THUMBNAIL_MAX_BYTES = 512 * 1024;

function outputType(file) {
    if (HTMLCanvasElement.prototype.toBlob) {
        return 'image/webp';
    }

    return file.type === 'image/png' ? 'image/png' : 'image/jpeg';
}

function extensionFor(type) {
    if (type === 'image/webp') return 'webp';
    if (type === 'image/png') return 'png';
    return 'jpg';
}

function resizedDimensions(width, height, maxSide) {
    const scale = Math.min(1, maxSide / Math.max(width, height));

    return {
        width: Math.max(1, Math.round(width * scale)),
        height: Math.max(1, Math.round(height * scale)),
    };
}

function canvasBlob(canvas, type, quality) {
    return new Promise((resolve) => canvas.toBlob(resolve, type, quality));
}

async function decodeImage(file) {
    if ('createImageBitmap' in window) {
        try {
            return await createImageBitmap(file, { imageOrientation: 'from-image' });
        } catch (_) {
            // Continue with the broadly supported HTMLImageElement decoder.
        }
    }

    const url = URL.createObjectURL(file);
    try {
        const image = new Image();
        image.decoding = 'async';
        image.src = url;
        if (typeof image.decode === 'function') {
            await image.decode();
        } else {
            await new Promise((resolve, reject) => {
                image.addEventListener('load', resolve, { once: true });
                image.addEventListener('error', reject, { once: true });
            });
        }

        return image;
    } finally {
        URL.revokeObjectURL(url);
    }
}

async function createVariant(file, maxSide, maxBytes, suffix) {
    const image = await decodeImage(file);
    const sourceWidth = image.width || image.naturalWidth;
    const sourceHeight = image.height || image.naturalHeight;
    const dimensions = resizedDimensions(sourceWidth, sourceHeight, maxSide);
    const canvas = document.createElement('canvas');
    canvas.width = dimensions.width;
    canvas.height = dimensions.height;

    const context = canvas.getContext('2d', { alpha: true });
    if (!context) throw new Error('Canvas 2D tidak tersedia.');
    context.drawImage(image, 0, 0, dimensions.width, dimensions.height);

    let type = outputType(file);
    let quality = 0.82;
    let blob = await canvasBlob(canvas, type, quality);
    if (!blob && type === 'image/webp') {
        type = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
        blob = await canvasBlob(canvas, type, quality);
    }
    while (blob && blob.size > maxBytes && quality > 0.58 && type !== 'image/png') {
        quality -= 0.08;
        blob = await canvasBlob(canvas, type, quality);
    }

    canvas.width = 0;
    canvas.height = 0;
    if (typeof image.close === 'function') image.close();
    if (!blob || blob.size > maxBytes) return null;

    const stem = file.name.replace(/\.[^.]+$/, '') || 'foto';
    return new File([blob], `${stem}.${suffix}.${extensionFor(type)}`, {
        type,
        lastModified: Date.now(),
    });
}

function variantInput(source, name, marker) {
    const form = source.form;
    if (!form || !name || !window.DataTransfer) return null;

    let input = form.querySelector(`input[data-generated-image-variant="${marker}"]`);
    if (!input) {
        input = document.createElement('input');
        input.type = 'file';
        input.name = name;
        input.multiple = true;
        input.hidden = true;
        input.dataset.generatedImageVariant = marker;
        form.appendChild(input);
    }

    return input;
}

async function prepareInput(input) {
    const files = Array.from(input.files || []);
    const webInput = variantInput(input, input.dataset.webVariantName || '', `${input.name}:web`);
    const thumbnailInput = variantInput(input, input.dataset.thumbnailName || '', `${input.name}:thumbnail`);
    if (!webInput || !thumbnailInput || files.length === 0) return;

    const status = input.form?.querySelector('[data-image-variant-status]');
    if (status) status.textContent = 'Menyiapkan versi hemat data…';

    const webTransfer = new DataTransfer();
    const thumbnailTransfer = new DataTransfer();
    for (const file of files) {
        try {
            const [web, thumbnail] = await Promise.all([
                createVariant(file, WEB_MAX_SIDE, WEB_MAX_BYTES, 'web'),
                createVariant(file, THUMBNAIL_MAX_SIDE, THUMBNAIL_MAX_BYTES, 'thumb'),
            ]);
            if (web) webTransfer.items.add(web);
            if (thumbnail) thumbnailTransfer.items.add(thumbnail);
        } catch (_) {
            // The original remains valid; the server records variant_pending.
        }
    }

    webInput.files = webTransfer.files;
    thumbnailInput.files = thumbnailTransfer.files;
    if (status) {
        status.textContent = webTransfer.files.length === files.length
            && thumbnailTransfer.files.length === files.length
            ? `${files.length} versi hemat data siap diunggah`
            : 'Original siap; sebagian versi hemat data akan diproses kemudian';
    }
}

export function initializeImageVariants(root = document) {
    root.querySelectorAll('[data-client-image-variants]').forEach((input) => {
        if (input.dataset.imageVariantsReady === '1') return;
        input.dataset.imageVariantsReady = '1';

        let pending = Promise.resolve();
        input.addEventListener('change', () => {
            pending = prepareInput(input);
        });
        input.form?.addEventListener('submit', async (event) => {
            if (!pending) return;
            event.preventDefault();
            await pending;
            pending = null;
            input.form.requestSubmit(event.submitter || undefined);
        }, { once: true });
    });
}

export const setupClientImageVariants = initializeImageVariants;

initializeImageVariants();
