/**
 * Studio media upload.
 *
 * Images and documents use Livewire's normal upload. Video and audio use the
 * resumable endpoints: the file is sent in small chunks (each below the host's
 * PHP limit), retried on network errors, and resumed from the server's offset
 * after a refresh — the same file just needs to be chosen again.
 * When the bytes are in, the browser measures duration/size and captures a
 * poster frame, so the shared host never needs FFmpeg.
 *
 * Images are shown in a preview first. Cropping is optional (see image-crop.js): the cropped picture, not the full photo,
 * is what gets uploaded, through the same direct upload and the same attach step.
 */

import { canCrop, initialRect, isFullRect, moveRect, outputFormat, outputGeometry, outputName, resizeRect } from './image-crop';

const EXTENSION_TYPES = {
    mp4: 'video/mp4', m4v: 'video/mp4', webm: 'video/webm', mov: 'video/quicktime',
    mp3: 'audio/mpeg', m4a: 'audio/mp4', aac: 'audio/aac', wav: 'audio/wav',
    ogg: 'audio/ogg', oga: 'audio/ogg', flac: 'audio/flac',
};

function typeOf(file) {
    if (file.type) {
        return file.type;
    }
    const extension = (file.name.split('.').pop() || '').toLowerCase();
    return EXTENSION_TYPES[extension] || '';
}

function kindOf(file) {
    const type = typeOf(file);
    if (type.startsWith('image/')) return 'image';
    if (type.startsWith('video/')) return 'video';
    if (type.startsWith('audio/')) return 'audio';
    return 'document';
}

function formatBytes(bytes) {
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    return Math.max(1, Math.round(bytes / 1024)) + ' KB';
}

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/** Duration, size and a poster frame, measured locally. Never throws. */
async function describeMedia(file, kind) {
    const result = { duration_ms: null, width: null, height: null, poster: null };
    if (kind !== 'video' && kind !== 'audio') return result;

    const url = URL.createObjectURL(file);
    const element = document.createElement(kind === 'video' ? 'video' : 'audio');
    element.preload = 'metadata';
    element.muted = true;
    element.playsInline = true;
    element.src = url;

    const once = (event, ms) => new Promise((resolve) => {
        const timer = setTimeout(() => resolve(false), ms);
        element.addEventListener(event, () => { clearTimeout(timer); resolve(true); }, { once: true });
    });

    try {
        if (!(await once('loadedmetadata', 15000))) return result;
        if (Number.isFinite(element.duration)) result.duration_ms = Math.round(element.duration * 1000);
        if (kind !== 'video') return result;

        result.width = element.videoWidth || null;
        result.height = element.videoHeight || null;
        element.currentTime = Math.min(1, (element.duration || 2) / 10);
        if (!(await once('seeked', 15000)) || !element.videoWidth) return result;

        const scale = Math.min(1, 1280 / element.videoWidth);
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(element.videoWidth * scale);
        canvas.height = Math.round(element.videoHeight * scale);
        canvas.getContext('2d').drawImage(element, 0, 0, canvas.width, canvas.height);
        result.poster = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.82));
    } catch (error) {
        // Metadata is optional; the upload still completes without it.
    } finally {
        URL.revokeObjectURL(url);
        element.removeAttribute('src');
    }

    return result;
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('mediaUpload', (config) => ({
        state: 'idle',
        mode: 'direct',
        file: null,
        progress: 0,
        message: '',
        note: '',
        session: null,
        cancelled: false,
        originalFile: null,
        previewUrl: null,
        canCropImage: false,
        cropped: false,
        cropping: false,
        crop: initialRect(),
        drag: null,
        dimensions: '',

        size(bytes) {
            return formatBytes(bytes);
        },

        choose(event) {
            const file = event.target.files[0];
            event.target.value = '';
            if (!file) return;

            this.file = file;
            this.message = '';
            this.note = '';
            const kind = kindOf(file);
            if (kind === 'image') {
                this.originalFile = file;
                this.showPreview();
                return;
            }
            const resumable = (kind === 'video' || kind === 'audio') && config.resumableLimits[kind] > 0;
            const limit = resumable ? config.resumableLimits[kind] : config.limits[kind];

            if (file.size > limit) {
                this.state = 'too_large';
                this.message = config.labels.tooLarge.replace(':size', formatBytes(file.size)).replace(':limit', formatBytes(limit));
                return;
            }

            this.mode = resumable ? 'resumable' : 'direct';
            resumable ? this.uploadResumable() : this.uploadDirect();
        },

        // ---- Images: preview, optional crop, then the normal direct upload -----------------------------------------

        showPreview() {
            this.mode = 'direct';
            this.cropped = false;
            this.cropping = false;
            this.dimensions = '';
            this.canCropImage = canCrop(typeOf(this.file));
            this.setPreview(this.file);
            this.state = 'preview';
            this.warnIfTooLarge();
        },

        setPreview(file) {
            this.releasePreview();
            this.previewUrl = URL.createObjectURL(file);
        },

        releasePreview() {
            if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
            this.previewUrl = null;
        },

        warnIfTooLarge() {
            const limit = config.limits.image;
            this.message = this.file.size > limit
                ? config.labels.tooLargeImage.replace(':size', formatBytes(this.file.size)).replace(':limit', formatBytes(limit))
                : '';
        },

        startCrop() {
            this.crop = initialRect();
            this.cropping = true;
        },

        cancelCrop() {
            this.cropping = false;
        },

        cropStyle() {
            const c = this.crop;
            return `left:${c.x * 100}%;top:${c.y * 100}%;width:${c.w * 100}%;height:${c.h * 100}%`;
        },

        dragStart(event, handle) {
            event.preventDefault();
            event.currentTarget.setPointerCapture(event.pointerId);
            this.drag = { handle, x: event.clientX, y: event.clientY, start: { ...this.crop } };
        },

        dragMove(event) {
            if (!this.drag) return;
            const box = this.$refs.stage.getBoundingClientRect();
            if (!box.width || !box.height) return;
            const dx = (event.clientX - this.drag.x) / box.width;
            const dy = (event.clientY - this.drag.y) / box.height;
            this.crop = this.drag.handle === 'move'
                ? moveRect(this.drag.start, dx, dy)
                : resizeRect(this.drag.start, this.drag.handle, dx, dy);
        },

        dragEnd() {
            this.drag = null;
        },

        /** The picture as the browser shows it, rotated upright by its EXIF orientation. */
        async decodeUpright() {
            try {
                const bitmap = await createImageBitmap(this.originalFile, { imageOrientation: 'from-image' });
                return { source: bitmap, width: bitmap.width, height: bitmap.height, close: () => bitmap.close() };
            } catch (error) {
                const image = new Image();
                image.src = URL.createObjectURL(this.originalFile);
                await image.decode();
                return { source: image, width: image.naturalWidth, height: image.naturalHeight, close: () => URL.revokeObjectURL(image.src) };
            }
        },

        async applyCrop() {
            try {
                const decoded = await this.decodeUpright();
                const geometry = outputGeometry(decoded.width, decoded.height, this.crop);
                const format = outputFormat(typeOf(this.originalFile));
                const canvas = document.createElement('canvas');
                canvas.width = geometry.dw;
                canvas.height = geometry.dh;
                const context = canvas.getContext('2d');
                context.imageSmoothingQuality = 'high';
                if (format.type === 'image/jpeg') {
                    context.fillStyle = '#ffffff';
                    context.fillRect(0, 0, canvas.width, canvas.height);
                }
                context.drawImage(decoded.source, geometry.sx, geometry.sy, geometry.sw, geometry.sh, 0, 0, geometry.dw, geometry.dh);
                decoded.close();
                const blob = await new Promise((resolve) => canvas.toBlob(resolve, format.type, format.quality));
                if (!blob) throw new Error('encode');

                this.cropping = false;
                // The whole picture "cropped" only to shrink it: keep the original when that would not make it smaller.
                if (isFullRect(this.crop) && blob.size >= this.originalFile.size) {
                    this.undoCrop();
                    return;
                }
                this.file = new File([blob], outputName(this.originalFile.name, format.extension), { type: format.type, lastModified: Date.now() });
                this.cropped = true;
                this.dimensions = `${geometry.dw} × ${geometry.dh}`;
                this.setPreview(this.file);
                this.warnIfTooLarge();
            } catch (error) {
                this.cropping = false;
                this.message = config.labels.failed;
            }
        },

        undoCrop() {
            this.file = this.originalFile;
            this.cropped = false;
            this.cropping = false;
            this.dimensions = '';
            this.setPreview(this.file);
            this.warnIfTooLarge();
        },

        useImage() {
            if (this.file.size > config.limits.image) {
                this.warnIfTooLarge();
                return;
            }
            this.message = '';
            this.releasePreview();
            this.uploadDirect();
        },

        retry() {
            this.mode === 'resumable' ? this.uploadResumable() : this.uploadDirect();
        },

        uploadDirect() {
            this.state = 'uploading';
            this.progress = 0;
            this.$wire.upload('assetUpload', this.file,
                () => { this.state = 'ready'; },
                () => { this.state = 'failed'; this.message = config.labels.failed; },
                (event) => { this.progress = event.detail.progress; },
                () => { this.state = 'idle'; this.file = null; },
            );
        },

        storageKey() {
            const file = this.file;
            return `iet-upload:${config.contentUuid}:${file.name}:${file.size}:${file.lastModified}`;
        },

        headers(json = true) {
            const headers = { 'X-CSRF-TOKEN': config.csrf, Accept: 'application/json' };
            if (json) headers['Content-Type'] = 'application/json';
            return headers;
        },

        async request(url, options, attempts = 5) {
            let delay = 1000;
            for (let attempt = 1; ; attempt++) {
                try {
                    const response = await fetch(url, { credentials: 'same-origin', ...options });
                    if (response.status >= 500 && attempt < attempts) throw new Error('server');
                    return response;
                } catch (error) {
                    if (this.cancelled || attempt >= attempts) throw error;
                    await wait(delay);
                    delay = Math.min(delay * 2, 15000);
                }
            }
        },

        async openSession() {
            let stored = null;
            try { stored = window.localStorage.getItem(this.storageKey()); } catch (error) { stored = null; }

            if (stored) {
                const response = await this.request(config.statusUrl.replace('__SESSION__', stored), { headers: this.headers(false) }, 2).catch(() => null);
                if (response && response.ok) {
                    const session = await response.json();
                    if (session.status === 'open') {
                        if (session.received_bytes > 0) this.note = config.labels.resuming;
                        return session;
                    }
                }
            }

            const response = await this.request(config.createUrl, {
                method: 'POST',
                headers: this.headers(),
                body: JSON.stringify({
                    filename: this.file.name,
                    byte_size: this.file.size,
                    mime_type: typeOf(this.file),
                    rights_status: this.$wire.assetRightsStatus,
                    caption: this.$wire.assetCaption || null,
                }),
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(body.message || (body.errors ? Object.values(body.errors)[0][0] : config.labels.failed));
            }
            try { window.localStorage.setItem(this.storageKey(), body.uuid); } catch (error) { /* private mode */ }
            return body;
        },

        async uploadResumable() {
            this.state = 'uploading';
            this.cancelled = false;
            this.message = '';

            try {
                this.session = await this.openSession();
                let received = this.session.received_bytes;
                const size = this.file.size;
                const chunkSize = this.session.chunk_size;

                while (received < size) {
                    if (this.cancelled) return;
                    this.progress = Math.floor((received / size) * 100);
                    const chunk = this.file.slice(received, Math.min(size, received + chunkSize));
                    const response = await this.request(`${this.session.urls.chunk}?offset=${received}`, {
                        method: 'POST',
                        headers: { ...this.headers(false), 'Content-Type': 'application/octet-stream' },
                        body: chunk,
                    });
                    const body = await response.json().catch(() => ({}));
                    if (response.status === 409 && typeof body.received_bytes === 'number' && body.status === 'open') {
                        received = body.received_bytes;
                        continue;
                    }
                    if (!response.ok) throw new Error(body.message || config.labels.failed);
                    received = body.received_bytes;
                }

                this.progress = 100;
                this.state = 'processing';
                const media = await describeMedia(this.file, kindOf(this.file));
                const form = new FormData();
                ['duration_ms', 'width', 'height'].forEach((key) => { if (media[key]) form.append(key, media[key]); });
                if (media.poster) form.append('poster', media.poster, 'poster.jpg');

                const response = await this.request(this.session.urls.complete, { method: 'POST', headers: this.headers(false), body: form }, 3);
                const body = await response.json().catch(() => ({}));
                if (!response.ok || !body.asset_uuid) throw new Error(body.message || config.labels.failed);

                try { window.localStorage.removeItem(this.storageKey()); } catch (error) { /* ignore */ }
                await this.$wire.placeUploadedAsset(body.asset_uuid);
                this.state = 'done';
            } catch (error) {
                if (this.cancelled) return;
                this.state = 'failed';
                this.message = error && error.message && error.message !== 'server' && error.message !== 'Failed to fetch'
                    ? error.message
                    : config.labels.failedResumable;
            }
        },

        async cancel() {
            if (this.mode === 'direct') {
                this.$wire.cancelUpload('assetUpload');
            } else {
                this.cancelled = true;
                if (this.session) {
                    await fetch(this.session.urls.cancel, { method: 'DELETE', credentials: 'same-origin', headers: this.headers(false) }).catch(() => null);
                }
                try { window.localStorage.removeItem(this.storageKey()); } catch (error) { /* ignore */ }
            }
            this.releasePreview();
            this.state = 'idle';
            this.file = null;
            this.originalFile = null;
            this.session = null;
            this.progress = 0;
        },

        clear() {
            if (this.state === 'ready') this.$wire.set('assetUpload', null);
            this.releasePreview();
            this.state = 'idle';
            this.file = null;
            this.originalFile = null;
            this.message = '';
        },
    }));
});
