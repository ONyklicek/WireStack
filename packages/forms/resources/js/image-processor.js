/*
 * Client-side image processing: crop, rotate, flip and downscale, before
 * Livewire uploads the file.
 *
 * Two callers, one implementation. FileUpload uses it to avoid shipping a 12 MP
 * phone photo over the wire; the media library's editor uses it to cut and
 * straighten a picture that is already stored (ADR 0035). The editor is why
 * `crop`, `rotate` and `flip` exist here rather than in a second module of its
 * own — a parallel copy of canvas resampling would diverge inside one release.
 *
 * Why here and not on the server: the point of imageResizeTargetWidth() is to
 * not ship a 12 MP phone photo over the wire in the first place. Once the upload
 * has happened, resizing it has already cost the user the upload.
 *
 * Deliberately dependency-free. A canvas does all of this in a few lines; what
 * would need a library is the *interactive* frame, and that is markup and
 * pointer events rather than image maths — `wireImageUpload()` below and the
 * media editor each own their own, and both hand the result to `processImage()`
 * as numbers.
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

/**
 * Draw a region of `source` at `width`×`height`, halving until it gets there.
 *
 * One `drawImage()` from 6000px to 400px is where canvas resampling visibly
 * falls apart: the browser samples a handful of source pixels per output pixel
 * and the result is aliased and mushy. Halving repeatedly averages the whole
 * image on the way down, which is what every image library does internally, and
 * costs a handful of draws.
 */
function drawStepped(source, region, width, height) {
    let canvas = document.createElement('canvas');
    let currentWidth = region.width;
    let currentHeight = region.height;

    canvas.width = currentWidth;
    canvas.height = currentHeight;
    canvas.getContext('2d').drawImage(
        source, region.x, region.y, region.width, region.height, 0, 0, currentWidth, currentHeight,
    );

    // Only on the way down, and never past the target.
    while (currentWidth > width * 2 && currentHeight > height * 2) {
        const nextWidth = Math.max(width, Math.round(currentWidth / 2));
        const nextHeight = Math.max(height, Math.round(currentHeight / 2));

        const step = document.createElement('canvas');
        step.width = nextWidth;
        step.height = nextHeight;

        const context = step.getContext('2d');
        context.imageSmoothingQuality = 'high';
        context.drawImage(canvas, 0, 0, currentWidth, currentHeight, 0, 0, nextWidth, nextHeight);

        canvas = step;
        currentWidth = nextWidth;
        currentHeight = nextHeight;
    }

    if (currentWidth === width && currentHeight === height) return canvas;

    const out = document.createElement('canvas');
    out.width = width;
    out.height = height;

    const context = out.getContext('2d');
    context.imageSmoothingQuality = 'high';
    context.drawImage(canvas, 0, 0, currentWidth, currentHeight, 0, 0, width, height);

    return out;
}

/**
 * Turn `canvas` by a quarter-turn multiple and/or mirror it.
 *
 * A quarter turn swaps the axes, so the destination canvas is the source's
 * height by its width — getting that the wrong way round is how a rotated
 * picture comes back with its edges cut off.
 */
function orient(canvas, rotate, flip) {
    const turn = ((Math.round((Number(rotate) || 0) / 90) * 90) % 360 + 360) % 360;

    if (turn === 0 && !flip) return canvas;

    const swapped = turn === 90 || turn === 270;
    const out = document.createElement('canvas');

    out.width = swapped ? canvas.height : canvas.width;
    out.height = swapped ? canvas.width : canvas.height;

    const context = out.getContext('2d');

    context.translate(out.width / 2, out.height / 2);
    context.rotate((turn * Math.PI) / 180);
    if (flip) context.scale(-1, 1);
    context.drawImage(canvas, -canvas.width / 2, -canvas.height / 2);

    return out;
}

/**
 * An explicit crop rectangle, given as fractions of the source.
 *
 * What an interactive frame produces: the user placed a box on the picture and
 * the numbers describe where. Clamped rather than trusted, because a frame
 * dragged past the edge is an ordinary thing to do with a mouse.
 */
function fractionalCrop(width, height, crop) {
    const clamp = (v) => Math.min(1, Math.max(0, Number(v) || 0));

    const x = clamp(crop.x);
    const y = clamp(crop.y);

    return {
        x: Math.round(width * x),
        y: Math.round(height * y),
        width: Math.max(1, Math.round(width * Math.min(clamp(crop.width), 1 - x))),
        height: Math.max(1, Math.round(height * Math.min(clamp(crop.height), 1 - y))),
    };
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
export async function processImage(file, {
    aspectRatio = null,
    targetWidth = null,
    targetHeight = null,
    offset = null,
    crop = null,
    rotate = 0,
    flip = false,
    format = null,
    quality = 0.9,
} = {}) {
    const ratio = parseAspectRatio(aspectRatio);
    const turn = ((Math.round((Number(rotate) || 0) / 90) * 90) % 360 + 360) % 360;

    if (!ratio && !crop && !targetWidth && !targetHeight && !turn && !flip && !format) return file;
    if (!file?.type?.startsWith('image/') || file.type === 'image/svg+xml') return file;

    try {
        const img = await loadImage(file);

        // Three ways to say which part of the picture is wanted, in order of how
        // explicit they are: a frame the user placed, a ratio with an offset, or
        // a ratio taken from the centre.
        const region = crop
            ? fractionalCrop(img.naturalWidth, img.naturalHeight, crop)
            : placedCrop(img.naturalWidth, img.naturalHeight, ratio, offset);

        // Measured against the region, before any quarter turn: asking for 800px
        // wide means 800 across the picture the user is looking at.
        const out = scaledSize(region.width, region.height, targetWidth, targetHeight);

        const untouched = region.width === img.naturalWidth && region.height === img.naturalHeight
            && out.width === region.width && out.height === region.height
            && !turn && !flip;

        // Nothing would change: don't re-encode, which would only lose quality.
        if (untouched && (!format || format === file.type)) return file;

        const canvas = orient(drawStepped(img, region, out.width, out.height), turn, flip);

        // Keep PNG lossless; everything else re-encodes as JPEG, where quality
        // is a knob and transparency was not on the table anyway. `format` lets
        // a caller overrule that — the media editor offers WebP, which is
        // smaller than both and is the point of offering it.
        const type = format || (file.type === 'image/png' ? 'image/png' : 'image/jpeg');
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, type, quality));

        if (!blob) return file;

        const extension = { 'image/png': 'png', 'image/webp': 'webp' }[type] || 'jpg';
        const name = type === file.type
            ? file.name
            : file.name.replace(/\.[^.]+$/, '') + '.' + extension;

        return new File([blob], name, { type, lastModified: Date.now() });
    } catch {
        // A corrupt or exotic image is the server's problem to report, not a
        // reason to drop the upload here. A tainted canvas — a cross-origin
        // source with no CORS headers — raises here too, and the editor tells
        // the person rather than shipping a file that is silently the original.
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
