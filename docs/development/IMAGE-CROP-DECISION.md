# Image crop before upload — architecture decision

## Decision
The cropped picture **becomes the uploaded original** of the Content asset. The full photo is not uploaded and no derivative is stored.

## Why
- The point of cropping is to avoid sending unneeded pixels. Keeping the full original on the server as well would defeat that and double storage.
- This application's media model has no image derivatives: `asset_media_variants` exists only for video poster frames, and a Content Asset is immutable once stored. A "crop as derivative" would need a new variant kind, new reader logic and a second file to authorize and serve for no user benefit.
- The author's own file (phone gallery, camera roll, desktop) is only ever read by the browser. It is never modified or deleted; the author can always crop again from it.

## How it works
1. Choosing an image shows a preview. Nothing is uploaded yet.
2. `Use this image` uploads the chosen file through the existing direct upload (`assetUpload`) and the existing attach step. That is the uncropped path and is unchanged server-side.
3. `Crop / edit image` is optional. The author moves and resizes a box (touch and mouse, pointer events). `Apply crop` draws that region on a canvas and replaces the file to upload. `Use the full photo` undoes it.
4. Geometry is measured on the picture as the browser shows it, already rotated upright by its EXIF orientation (`createImageBitmap(..., { imageOrientation: 'from-image' })`). A phone portrait therefore stays portrait.
5. Output is never enlarged. Its longest edge is capped at 2560 px. JPEG and WebP are re-encoded at quality 0.9, PNG stays PNG, AVIF becomes JPEG (browsers cannot encode AVIF). Animated GIFs are previewed but cannot be cropped.
6. If the "crop" is the whole picture and is not smaller than the original, the original file is used.
7. A photo above the image size limit shows the limit and offers the crop (including a whole-photo crop) to bring it under.

## Integration
- **Resumable uploads:** images use the direct path, so they are unaffected; video and audio keep the resumable path.
- **Retries and idempotency:** one `assetUpload` value is attached once. After `attachAsset` it is cleared, so a repeated click or retry finds nothing to attach (tested): one Asset, one revision placement, one block.
- **Captions, rights, blocks, placements, reader:** unchanged; the cropped file is an ordinary image Asset (tested, including the published reader and asset route).
- **No migration, no new tables, no new server endpoints.**

## Tests
- `npm run test:js` — crop geometry: no upscaling, long-edge cap, portrait, clamping, handles, formats, names.
- `tests/Feature/ImageCropUploadTest.php` — uncropped upload, cropped portrait dimensions and caption, no derivative, double attach, published reader, dialog markup.
- Not covered by automation: real pointer/touch dragging and EXIF decoding in a browser. These need a manual check on a phone and a desktop.

## Camera capture (mobile)
On touch devices the media dialog also shows "Take a photo" and "Record a video" (`<input capture="environment">`, shown only for `pointer: coarse` devices; desktop browsers ignore it). The camera result is handed to the same `choose()` handler as a gallery file: a photo gets the preview and optional crop, a video uses the resumable upload. No separate pipeline. Whether the phone opens its camera app or a chooser is up to the browser/OS; this needs a manual check on iOS Safari and Android Chrome.
