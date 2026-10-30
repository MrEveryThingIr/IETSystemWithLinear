import assert from 'node:assert/strict';
import test from 'node:test';
import { MAX_EDGE, MIN_SIZE, canCrop, fullRect, initialRect, isFullRect, moveRect, normalizeRect, outputFormat, outputGeometry, outputName, resizeRect } from '../image-crop.js';

test('a small crop is never enlarged', () => {
    const g = outputGeometry(1000, 800, { x: 0, y: 0, w: 0.5, h: 0.5 });
    assert.deepEqual([g.sw, g.sh, g.dw, g.dh], [500, 400, 500, 400]);
});

test('a large phone photo is reduced to the maximum edge, keeping its shape', () => {
    const g = outputGeometry(4000, 3000, fullRect());
    assert.equal(g.dw, MAX_EDGE);
    assert.equal(g.dh, Math.round(3000 * (MAX_EDGE / 4000)));
});

test('a portrait photo stays portrait: the crop is measured on the upright picture', () => {
    // A phone portrait is 3024 x 4032 once rotated upright by its EXIF orientation.
    const g = outputGeometry(3024, 4032, { x: 0.25, y: 0.25, w: 0.5, h: 0.5 });
    assert.equal(g.sw, 1512);
    assert.equal(g.sh, 2016);
    assert.ok(g.dh > g.dw);
    assert.ok(Math.max(g.dw, g.dh) <= MAX_EDGE);
});

test('the cropped region always lies inside the picture', () => {
    const g = outputGeometry(1000, 1000, { x: 0.9, y: 0.9, w: 0.5, h: 0.5 });
    assert.ok(g.sx + g.sw <= 1000 && g.sy + g.sh <= 1000);
    assert.ok(g.sw >= 1 && g.sh >= 1);
});

test('moving keeps the box inside the picture', () => {
    const moved = moveRect(initialRect(), 5, -5);
    assert.equal(moved.x + moved.w, 1);
    assert.equal(moved.y, 0);
});

test('resizing a corner keeps the opposite side fixed and respects the minimum size', () => {
    const start = { x: 0.2, y: 0.2, w: 0.5, h: 0.5 };
    const grown = resizeRect(start, 'se', 0.1, 0.1);
    assert.equal(grown.x, 0.2);
    assert.equal(grown.y, 0.2);
    assert.ok(Math.abs(grown.w - 0.6) < 1e-9);
    const crushed = resizeRect(start, 'nw', 5, 5);
    assert.ok(Math.abs(crushed.w - MIN_SIZE) < 1e-9);
    assert.ok(Math.abs(crushed.x + crushed.w - 0.7) < 1e-9);
    const outside = resizeRect(start, 'se', 5, 5);
    assert.equal(outside.x + outside.w, 1);
});

test('a box that covers the whole picture counts as the full picture', () => {
    assert.equal(isFullRect(fullRect()), true);
    assert.equal(isFullRect(initialRect()), false);
    assert.deepEqual(normalizeRect({ x: -1, y: 2, w: 3, h: 0 }), { x: 0, y: 0.92, w: 1, h: MIN_SIZE });
});

test('formats: PNG and WebP are kept, everything else becomes a good JPEG; only still formats can be cropped', () => {
    assert.equal(outputFormat('image/png').extension, 'png');
    assert.equal(outputFormat('image/webp').type, 'image/webp');
    assert.equal(outputFormat('image/avif').type, 'image/jpeg');
    assert.equal(outputFormat('image/jpeg').quality, 0.9);
    assert.equal(canCrop('image/gif'), false);
    assert.equal(canCrop('image/jpeg'), true);
});

test('the cropped name keeps the original name and marks the crop', () => {
    assert.equal(outputName('IMG_2041.HEIC.jpeg', 'jpg'), 'IMG_2041.HEIC-cropped.jpg');
    assert.equal(outputName('', 'png'), 'image-cropped.png');
});
