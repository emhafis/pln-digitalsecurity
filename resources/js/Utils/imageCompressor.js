/**
 * Browser-side image processor for camera/photo uploads.
 * Converts all supported images (including HEIC/HEIF when possible) to compressed JPEG.
 */
export const DEFAULT_COMPRESS_OPTIONS = {
    maxWidth: 720,
    maxHeight: 720,
    quality: 0.7,
    mimeType: 'image/jpeg',
    maxOutputBytes: 350 * 1024, // target small KB
    minQuality: 0.5,
    minWidth: 480,
    minHeight: 480,
};

const HEIC_TYPES = ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'];
const HEIC_EXTENSIONS = ['.heic', '.heif'];

function isHeicFile(file) {
    const type = (file?.type || '').toLowerCase();
    if (HEIC_TYPES.includes(type)) return true;
    const name = (file?.name || '').toLowerCase();
    return HEIC_EXTENSIONS.some((ext) => name.endsWith(ext));
}

function isImageFile(file) {
    if (!file) return false;
    const type = (file.type || '').toLowerCase();
    if (type.startsWith('image/')) return true;
    const name = (file.name || '').toLowerCase();
    return /\.(jpe?g|png|webp|gif|bmp|avif|tiff?|heic|heif)$/i.test(name);
}

function loadImage(src) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error('Gagal memuat gambar untuk diproses.'));
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
    const base = (name || 'photo').replace(/\.[^.]+$/, '') || 'photo';
    return `${base}.${ext}`;
}

async function decodeToImageSource(file) {
    if (typeof createImageBitmap === 'function') {
        try {
            return await createImageBitmap(file, { imageOrientation: 'from-image' });
        } catch (e) {
            // fallback below
        }
    }
    const url = URL.createObjectURL(file);
    try {
        return await loadImage(url);
    } finally {
        URL.revokeObjectURL(url);
    }
}

async function convertHeicToJpegFile(file) {
    let heic2any;
    try {
        const mod = await import('heic2any');
        heic2any = mod?.default ?? mod;
    } catch (e) {
        throw new Error('Format HEIC/HEIF belum bisa diproses di perangkat ini. Coba gunakan format JPG.');
    }

    const converted = await heic2any({
        blob: file,
        toType: 'image/jpeg',
        quality: 0.92,
    });
    const blob = Array.isArray(converted) ? converted[0] : converted;
    if (!(blob instanceof Blob)) {
        throw new Error('Gagal mengonversi gambar HEIC/HEIF.');
    }
    return new File([blob], replaceExtension(file.name, 'jpg'), {
        type: 'image/jpeg',
        lastModified: Date.now(),
    });
}

async function prepareSourceFile(file) {
    if (!isHeicFile(file)) return file;
    try {
        await decodeToImageSource(file);
        return file;
    } catch (e) {
        return await convertHeicToJpegFile(file);
    }
}

async function drawToCanvas(sourceFile, maxWidth, maxHeight) {
    const src = await decodeToImageSource(sourceFile);
    const srcWidth = src.width;
    const srcHeight = src.height;
    const target = getTargetSize(srcWidth, srcHeight, maxWidth, maxHeight);
    const canvas = document.createElement('canvas');
    canvas.width = target.width;
    canvas.height = target.height;
    const ctx = canvas.getContext('2d');
    if (!ctx) {
        if (typeof src.close === 'function') src.close();
        throw new Error('Kanvas tidak tersedia untuk kompresi gambar.');
    }
    ctx.drawImage(src, 0, 0, target.width, target.height);
    if (typeof src.close === 'function') src.close();
    return { canvas, width: target.width, height: target.height, srcWidth, srcHeight, scaled: target.scaled };
}

export async function compressImage(file, options = {}) {
    if (!(file instanceof File) && !(file instanceof Blob)) {
        throw new Error('File gambar tidak valid.');
    }
    if (!isImageFile(file)) {
        throw new Error('File harus berupa gambar.');
    }

    const opts = { ...DEFAULT_COMPRESS_OPTIONS, ...options };
    const maxOutputBytes = Number(opts.maxOutputBytes) > 0 ? Number(opts.maxOutputBytes) : DEFAULT_COMPRESS_OPTIONS.maxOutputBytes;
    const sourceFile = await prepareSourceFile(file);

    const src = await decodeToImageSource(sourceFile);
    const srcWidth = src.width;
    const srcHeight = src.height;
    if (typeof src.close === 'function') src.close();

    const initial = getTargetSize(srcWidth, srcHeight, opts.maxWidth, opts.maxHeight);
    let width = initial.width;
    let height = initial.height;
    let quality = opts.quality;
    let blob = null;
    let bestBlob = null;

    for (let attempt = 0; attempt < 10; attempt += 1) {
        const drawn = await drawToCanvas(sourceFile, width, height);
        blob = await canvasToBlob(drawn.canvas, 'image/jpeg', quality);
        drawn.canvas.width = 0;
        drawn.canvas.height = 0;

        if (!bestBlob || blob.size < bestBlob.size) {
            bestBlob = blob;
        }
        if (blob.size <= maxOutputBytes) break;

        if (quality > opts.minQuality + 0.05) {
            quality = Math.max(opts.minQuality, +(quality - 0.1).toFixed(2));
        } else if (width > opts.minWidth || height > opts.minHeight) {
            width = Math.max(opts.minWidth, Math.round(width * 0.85));
            height = Math.max(opts.minHeight, Math.round(height * 0.85));
            quality = opts.quality;
        } else {
            break;
        }
    }

    const finalBlob = blob && blob.size <= maxOutputBytes ? blob : (bestBlob || blob);
    if (!finalBlob) {
        throw new Error('Gagal memproses gambar.');
    }

    const outFile = new File([finalBlob], replaceExtension(file.name, 'jpg'), {
        type: 'image/jpeg',
        lastModified: Date.now(),
    });

    return {
        file: outFile,
        blob: finalBlob,
        width,
        height,
        originalSize: file.size || 0,
        compressedSize: finalBlob.size,
        wasCompressed: true,
    };
}

export function formatBytes(bytes) {
    if (!Number.isFinite(bytes) || bytes < 0) return '-';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

export function validateImageFile(file, { maxInputBytes = 10 * 1024 * 1024 } = {}) {
    if (!file) return 'Pilih file gambar.';
    if (!isImageFile(file)) {
        return 'File harus berupa gambar.';
    }
    if (file.size > maxInputBytes) {
        return 'Ukuran file terlalu besar. Maksimal 10 MB.';
    }
    return null;
}
