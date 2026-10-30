/**
 * Optional crop before an image is uploaded (Content Studio).
 *
 * Architecture: cropping happens entirely in the browser. When the author applies a crop, the cropped (and, if large,
 * downscaled) image replaces the file that is uploaded; the full photo is never sent and the author's own file is never
 * touched. The uploaded file is therefore the Asset's immutable original, so there is exactly one upload, one Asset and
 * one placement, and every existing path (limits, rights, caption, blocks, reader) is unchanged. No derivative is stored.
 *
 * All geometry is expressed as fractions (0..1) of the picture as it is displayed, which is already rotated upright by
 * the browser (EXIF orientation), so a phone portrait stays portrait. The functions below are pure and unit tested.
 */

/** Longest edge of a cropped result. Large phone photos are reduced to this; smaller crops are never enlarged. */
export const MAX_EDGE = 2560;

/** Smallest crop box, as a fraction of the picture. */
export const MIN_SIZE = 0.08;

const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

export function fullRect() {
    return { x: 0, y: 0, w: 1, h: 1 };
}

/** A sensible starting box: most of the picture, centred. */
export function initialRect() {
    return { x: 0.1, y: 0.1, w: 0.8, h: 0.8 };
}

export function isFullRect(rect, tolerance = 0.005) {
    return rect.x <= tolerance && rect.y <= tolerance && rect.w >= 1 - tolerance && rect.h >= 1 - tolerance;
}

/** Keep a box inside the picture and no smaller than the minimum. */
export function normalizeRect(rect, min = MIN_SIZE) {
    const w = clamp(rect.w, min, 1);
    const h = clamp(rect.h, min, 1);

    return { x: clamp(rect.x, 0, 1 - w), y: clamp(rect.y, 0, 1 - h), w, h };
}

/** Drag the whole box by a fraction of the picture. */
export function moveRect(rect, dx, dy) {
    return normalizeRect({ ...rect, x: rect.x + dx, y: rect.y + dy });
}

/** Drag one corner ('nw' | 'ne' | 'sw' | 'se') or edge ('n' | 's' | 'e' | 'w'); the opposite side stays put. */
export function resizeRect(rect, handle, dx, dy, min = MIN_SIZE) {
    let left = rect.x;
    let top = rect.y;
    let right = rect.x + rect.w;
    let bottom = rect.y + rect.h;

    if (handle.includes('w')) left = clamp(left + dx, 0, right - min);
    if (handle.includes('e')) right = clamp(right + dx, left + min, 1);
    if (handle.includes('n')) top = clamp(top + dy, 0, bottom - min);
    if (handle.includes('s')) bottom = clamp(bottom + dy, top + min, 1);

    return { x: left, y: top, w: right - left, h: bottom - top };
}

/**
 * Pixels to copy and the size to draw them at, for a picture of the given (upright) size.
 * Never enlarges: the result is at most the cropped region itself, and at most `maxEdge` on its longest side.
 */
export function outputGeometry(width, height, rect, maxEdge = MAX_EDGE) {
    const r = normalizeRect(rect, 0);
    const sx = clamp(Math.round(r.x * width), 0, width - 1);
    const sy = clamp(Math.round(r.y * height), 0, height - 1);
    const sw = clamp(Math.round(r.w * width), 1, width - sx);
    const sh = clamp(Math.round(r.h * height), 1, height - sy);
    const scale = Math.min(1, maxEdge / Math.max(sw, sh));

    return { sx, sy, sw, sh, dw: Math.max(1, Math.round(sw * scale)), dh: Math.max(1, Math.round(sh * scale)) };
}

/** Output encoding: keep what the browser can write and what the Content library accepts; otherwise a good JPEG. */
export function outputFormat(mime) {
    switch ((mime || '').toLowerCase()) {
        case 'image/png': return { type: 'image/png', extension: 'png', quality: undefined };
        case 'image/webp': return { type: 'image/webp', extension: 'webp', quality: 0.9 };
        default: return { type: 'image/jpeg', extension: 'jpg', quality: 0.9 };
    }
}

/** The uploaded name keeps the author's name with the right extension, and marks it as cropped. */
export function outputName(originalName, extension) {
    const base = (originalName || 'image').replace(/\.[^.]+$/, '') || 'image';

    return `${base}-cropped.${extension}`;
}

/** Formats that can be cropped; animated GIFs are uploaded as they are. */
export function canCrop(mime) {
    return ['image/jpeg', 'image/png', 'image/webp', 'image/avif'].includes((mime || '').toLowerCase());
}
