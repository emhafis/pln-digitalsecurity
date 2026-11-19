/**
 * Reusable browser-side image compressor using HTML Canvas.
 * Returns a compressed JPEG File/Blob while preserving aspect ratio.
 */
export const DEFAULT_COMPRESS_OPTIONS = {
    maxWidth: 1600,
    maxHeight: 1600,
    quality: 0.8,
    mimeType: 'image/jpeg',
    maxOutputBytes: 1024 * 1024, // ~1MB
    minCompressBytes: 128 * 1024,
};

const SUPPORTED_INPUT_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

function readFileAsDataURL(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = () => reject(new Error('Gagal membaca file gambar.'));
        reader.readAsDataURL(file);
    });
}

function loadImage(src) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error('Gagal memuat gambar untuk kompresi.'));
        img.src = src;
    });
}

function getTargetSize(width, height, maxWidth, maxHeight) {
    if (!width || !height) return { width, height, scaled: false };
    const ratio = Math.min(maxWidth / width, maxHeight / height, 1);
    return {
        width: Math.max(1, Math.round(width * ratio)),
        height: Math.max(1, Math.round(height * ratio)),
        scaled: ratio < 1,
    };
}

function canvasToBlob(canvas, mimeType, quality) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (!blob) {
                reject(new Error('Gagal menghasilkan gambar hasil kompresi.'));
                return;
            }
            resolve(blob);
        }, mimeType, quality);
    });
}

function replaceExtension(name, ext) {
    const base = (name || 'incident-photo').replace(/\.[^.]+$/, '');
    return `${base}.${ext}`;
}

export async function compressImage(file, options = {}) {
    if (!(file instanceof File) && !(file instanceof Blob)) {
        throw new Error('File gambar tidak valid.');
    }
    if (file.type && !SUPPORTED_INPUT_TYPES.includes(file.type)) {
        throw new Error('Format gambar tidak didukung. Gunakan JPG, PNG, atau WebP.');
    }

    const opts = { ...DEFAULT_COMPRESS_OPTIONS, ...options };
    const mimeType = opts.mimeType || 'image/jpeg';
    const outputExt = mimeType === 'image/png' ? 'png' : mimeType === 'image/webp' ? 'webp' : 'jpg';

    let objectUrl = null;
    try {
        objectUrl = URL.createObjectURL(file);
        let img;
        if (typeof createImageBitmap === 'function') {
            try {
                img = await createImageBitmap(file, { imageOrientation: 'from-image' });
            } catch (e) {
                img = await loadImage(objectUrl);
            }
        } else {
            img = await loadImage(objectUrl);
        }

        const srcWidth = img.width;
        const srcHeight = img.height;
        const target = getTargetSize(srcWidth, srcHeight, opts.maxWidth, opts.maxHeight);

        const canvas = document.createElement('canvas');
        canvas.width = target.width;
        canvas.height = target.height;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            throw new Error('Kanvas tidak tersedia untuk kompresi gambar.');
        }
        ctx.drawImage(img, 0, 0, target.width, target.height);
        if (typeof img.close === 'function') {
            img.close();
        }

        let blob = await canvasToBlob(canvas, mimeType, opts.quality);
        canvas.width = 0;
        canvas.height = 0;

        // Don't make it larger or "worse" when source is already efficient enough.
        const alreadyEfficient = !target.scaled && file.size <= opts.maxOutputBytes && file.size <= blob.size;
        if (alreadyEfficient) {
            return {
                file,
                blob: file,
                width: srcWidth,
                height: srcHeight,
                originalSize: file.size,
                compressedSize: file.size,
                wasCompressed: false,
            };
        }

        // Try a couple lower qualities if still above cap (without changing dimensions again).
        let quality = opts.quality;
        let attempts = 0;
        while (blob.size > opts.maxOutputBytes && quality > 0.45 && attempts < 3) {
            quality = Math.max(0.45, quality - 0.15);
            const c2 = document.createElement('canvas');
            c2.width = target.width;
            c2.height = target.height;
            const ctx2 = c2.getContext('2d');
            if (!ctx2) break;
            const img2 = await loadImage(objectUrl);
            ctx2.drawImage(img2, 0, 0, target.width, target.height);
            blob = await canvasToBlob(c2, mimeType, quality);
            c2.width = 0;
            c2.height = 0;
            attempts += 1;
        }

        if (blob.size > file.size && file.size <= opts.maxOutputBytes) {
            return {
                file,
                blob: file,
                width: srcWidth,
                height: srcHeight,
                originalSize: file.size,
                compressedSize: file.size,
                wasCompressed: false,
            };
        }

        const outName = replaceExtension(file.name, outputExt);
        const outFile = new File([blob], outName, { type: mimeType, lastModified: Date.now() });

        return {
            file: outFile,
            blob,
            width: target.width,
            height: target.height,
            originalSize: file.size,
            compressedSize: blob.size,
            wasCompressed: true,
        };
    } catch (error) {
        if (error instanceof Error) throw error;
        throw new Error('Gagal memproses kompresi gambar.');
    } finally {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
        }
    }
}

export function formatBytes(bytes) {
    if (!Number.isFinite(bytes) || bytes < 0) return '-';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

export function validateImageFile(file, { maxInputBytes = 10 * 1024 * 1024 } = {}) {
    if (!file) return 'Pilih file gambar.';
    if (!SUPPORTED_INPUT_TYPES.includes(file.type)) {
        return 'Format gambar tidak didukung. Gunakan JPG, PNG, atau WebP.';
    }
    if (file.size > maxInputBytes) {
        return 'Ukuran file terlalu besar untuk diproses.';
    }
    return null;
}
