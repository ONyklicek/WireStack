/*
 * Client-side image processing for FileUpload: crop to an aspect ratio and/or
 * downscale, before Livewire uploads the file.
 *
 * Why here and not on the server: the point of imageResizeTargetWidth() is to
 * not ship a 12 MP phone photo over the wire in the first place. Once the upload
 * has happened, resizing it has already cost the user the upload.
 *
 * Deliberately dependency-free. A canvas can crop to a ratio and downscale in a
 * few lines; a *interactive* cropper — drag the frame, pick the region — is what
 * needs a library, and nothing in this API asks the user to pick a region:
 * imageCropAspectRatio('16:9') names a ratio, and this delivers exactly that,
 * from the centre of the image.
 */

/** "16:9" | "1.5" | 1.5 → 1.777… ; anything unparseable → null (leave the image alone). */
function parseAspectRatio(ratio) {
    if (ratio === null || ratio === undefined || ratio === '') return null;
    if (typeof ratio === 'number') return ratio > 0 ? ratio : null;

    const text = String(ratio).trim();

    if (text.includes(':') || text.includes('/')) {
        const [w, h] = text.split(/[:/]/).map(Number);
        return w > 0 && h > 0 ? w / h : null;
    }

    const n = Number(text);
    return Number.isFinite(n) && n > 0 ? n : null;
}

/** The largest centred rectangle of `ratio` that fits inside w×h. */
function centredCrop(width, height, ratio) {
    if (!ratio) return { x: 0, y: 0, width, height };

    if (width / height > ratio) {
        const cropWidth = Math.round(height * ratio);
        return { x: Math.round((width - cropWidth) / 2), y: 0, width: cropWidth, height };
    }

    const cropHeight = Math.round(width / ratio);
    return { x: 0, y: Math.round((height - cropHeight) / 2), width, height: cropHeight };
}

/**
 * The crop rectangle, positioned by `offset` — {x, y} in 0..1 of whatever slack
 * the ratio leaves. Null (or no slack) means centred, which is the default and
 * what a non-interactive crop always uses.
 */
function placedCrop(width, height, ratio, offset) {
    const box = centredCrop(width, height, ratio);
    if (!offset) return box;

    const clamp = (v) => Math.min(1, Math.max(0, Number(v) || 0));

    return {
        ...box,
        x: Math.round((width - box.width) * clamp(offset.x)),
        y: Math.round((height - box.height) * clamp(offset.y)),
    };
}

/** Fit w×h inside the target box, never scaling up — upscaling only adds bytes. */
function scaledSize(width, height, targetWidth, targetHeight) {
    if (!targetWidth && !targetHeight) return { width, height };

    const scale = Math.min(
        targetWidth ? targetWidth / width : Infinity,
        targetHeight ? targetHeight / height : Infinity,
        1,
    );

    return { width: Math.max(1, Math.round(width * scale)), height: Math.max(1, Math.round(height * scale)) };
}

const loadImage = (file) => new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
    img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('not an image')); };
    img.src = url;
});

/**
 * Crop and/or downscale one file. Returns the original when there is nothing to
 * do, when it is not a raster image (an SVG has no pixels to resample), or when
 * anything goes wrong — a failed resize must never lose the user's file.
 */
export async function processImage(file, { aspectRatio = null, targetWidth = null, targetHeight = null, offset = null } = {}) {
    const ratio = parseAspectRatio(aspectRatio);

    if (!ratio && !targetWidth && !targetHeight) return file;
    if (!file?.type?.startsWith('image/') || file.type === 'image/svg+xml') return file;

    try {
        const img = await loadImage(file);
        // The centre is the default; `offset` (0..1 of the slack) is what an
        // interactive frame supplies once the user has moved it.
        const crop = placedCrop(img.naturalWidth, img.naturalHeight, ratio, offset);
        const out = scaledSize(crop.width, crop.height, targetWidth, targetHeight);

        // Nothing would change: don't re-encode, which would only lose quality.
        if (crop.width === img.naturalWidth && crop.height === img.naturalHeight
            && out.width === crop.width && out.height === crop.height) {
            return file;
        }

        const canvas = document.createElement('canvas');
        canvas.width = out.width;
        canvas.height = out.height;
        canvas.getContext('2d').drawImage(
            img, crop.x, crop.y, crop.width, crop.height, 0, 0, out.width, out.height,
        );

        // Keep PNG lossless; everything else re-encodes as JPEG, where quality
        // is a knob and transparency was not on the table anyway.
        const type = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, type, 0.9));

        if (!blob) return file;

        const name = type === file.type
            ? file.name
            : file.name.replace(/\.[^.]+$/, '') + '.jpg';

        return new File([blob], name, { type, lastModified: Date.now() });
    } catch {
        // A corrupt or exotic image is the server's problem to report, not a
        // reason to drop the upload here.
        return file;
    }
}

/**
 * `wireImageUpload(config)` — Alpine data for the FileUpload dropzone.
 *
 * Livewire uploads whatever sits in its wire:model input the moment `change`
 * fires there, and its listener is on that same input — so racing it by
 * swallowing and re-dispatching the event would come down to listener order.
 * Instead the user picks into a plain input we own, and only the *processed*
 * file is ever placed into the Livewire one.
 *
 * That split only exists when processing is configured; without it the field
 * keeps its original single-input markup, so an ordinary upload is untouched.
 */
export function wireImageUpload(config = {}) {
    return {
        isDragging: false,
        processing: false,

        // Interactive crop state. Only ever entered when the field both crops and
        // asks for it; otherwise the centre crop applies and nothing is shown.
        cropping: false,
        cropUrl: null,
        cropOffset: { x: 0.5, y: 0.5 },
        frame: { left: 0, top: 0, width: 0, height: 0 },
        _pending: [],
        _drag: null,

        handleDrop(e) {
            this.isDragging = false;
            const dropped = e.dataTransfer?.files;
            if (!dropped?.length) return;
            this.accept(Array.from(dropped));
        },

        openPicker() {
            (this.$refs.picker ?? this.$refs.fileInput).click();
        },

        onPick(e) {
            const files = Array.from(e.target.files ?? []);
            if (files.length) this.accept(files);
        },

        init() {
            // An image cached from a previous open is already complete, so `load`
            // never fires again — fit when the modal opens, too.
            this.$watch('cropping', (open) => open && this.$nextTick(() => this.fitFrame()));
        },

        async accept(files) {
            // Only a single raster image can be framed by hand; a batch, or a
            // format with no pixels, goes straight through the centre crop.
            if (config.interactive && files.length === 1 && files[0].type?.startsWith('image/')
                && files[0].type !== 'image/svg+xml') {
                this._pending = files;
                this.cropUrl = URL.createObjectURL(files[0]);
                this.cropOffset = { x: 0.5, y: 0.5 };
                this.cropping = true;

                return;
            }

            await this.process(files);
        },

        async process(files, offset = null) {
            this.processing = true;
            try {
                const out = await Promise.all(files.map((f) => processImage(f, { ...config, offset })));
                this.fill(out);
            } finally {
                this.processing = false;
            }
        },

        /**
         * Size the frame to the ratio, inside the displayed image.
         *
         * Retries on the next frame while the image measures zero: `load` can
         * fire while the modal is still display:none, and a frame sized against
         * a hidden image is a frame the user cannot see or drag. (The crop maths
         * reads the image's natural size, so this only bites the UI — which is
         * exactly the kind of break a passing pixel assertion hides.)
         */
        fitFrame() {
            const img = this.$refs.cropImage;
            if (!img) return;

            if (!img.clientWidth) {
                if (this.cropping) requestAnimationFrame(() => this.fitFrame());

                return;
            }

            const ratio = parseAspectRatio(config.aspectRatio);
            const box = ratio
                ? centredCrop(img.clientWidth, img.clientHeight, ratio)
                : { width: img.clientWidth, height: img.clientHeight };

            this.frame = { ...this.frame, width: box.width, height: box.height };
            this.moveFrame(this.cropOffset);
        },

        /** offset (0..1 of the slack) → pixel position of the frame. */
        moveFrame(offset) {
            const img = this.$refs.cropImage;
            if (!img) return;

            const slackX = img.clientWidth - this.frame.width;
            const slackY = img.clientHeight - this.frame.height;
            const clamp = (v) => Math.min(1, Math.max(0, v));

            this.cropOffset = { x: clamp(offset.x), y: clamp(offset.y) };
            this.frame = {
                ...this.frame,
                left: Math.round(slackX * this.cropOffset.x),
                top: Math.round(slackY * this.cropOffset.y),
            };
        },

        startDrag(e) {
            const point = e.touches?.[0] ?? e;
            this._drag = { x: point.clientX, y: point.clientY, left: this.frame.left, top: this.frame.top };
        },

        onDrag(e) {
            if (!this._drag) return;
            e.preventDefault();

            const img = this.$refs.cropImage;
            const point = e.touches?.[0] ?? e;
            const slackX = img.clientWidth - this.frame.width;
            const slackY = img.clientHeight - this.frame.height;

            this.moveFrame({
                x: slackX ? (this._drag.left + point.clientX - this._drag.x) / slackX : 0,
                y: slackY ? (this._drag.top + point.clientY - this._drag.y) / slackY : 0,
            });
        },

        endDrag() {
            this._drag = null;
        },

        async confirmCrop() {
            const files = this._pending;
            const offset = this.cropOffset;
            this.closeCrop();
            await this.process(files, offset);
        },

        cancelCrop() {
            this.closeCrop();
            // Leave the field as it was: an abandoned crop uploads nothing.
            if (this.$refs.picker) this.$refs.picker.value = '';
        },

        closeCrop() {
            if (this.cropUrl) URL.revokeObjectURL(this.cropUrl);
            this.cropUrl = null;
            this.cropping = false;
            this._pending = [];
        },

        /** Hand the finished files to Livewire's input and let it upload them. */
        fill(files) {
            const input = this.$refs.fileInput;
            const dt = new DataTransfer();
            files.forEach((f) => dt.items.add(f));
            input.files = dt.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        },
    };
}

// ─── Self-registration ──────────────────────────────────────────
// `alpine:init` fires exactly once per document, so a bundle that only listens
// for it registers nothing when it arrives after a `wire:navigate`. Register
// straight away when Alpine is already running; keep the listener for the
// first, cold load. The `registered` guard is load-bearing: the same src can be
// emitted twice (a per-surface partial plus the layout tag).
let registered = false;

const registerWireImageUpload = () => {
    if (registered || ! window.Alpine) return;
    registered = true;

    window.Alpine.data('wireImageUpload', wireImageUpload);
};

if (window.Alpine) {
    // Alpine already started (e.g. the script loaded after a Livewire navigation).
    registerWireImageUpload();
} else {
    document.addEventListener('alpine:init', registerWireImageUpload);
}
