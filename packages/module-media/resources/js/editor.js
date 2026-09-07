/*
 * The media library's image editor: crop, straighten, resize, save.
 *
 * The pixels are recomputed **in the browser**, and the deciding fact is in the
 * PHP rather than in the abstract: `MakeThumbnail` already gives up at
 * `is_file()` on a remote disk, so a server-side editor would be an editor that
 * silently does not exist on S3 — the configuration a large library is most
 * likely to be on. See ADR 0035.
 *
 * The maths is `wire-forms`' image processor, extended rather than copied.
 * `wire-forms` sits below this package in the graph, so importing it is allowed
 * and a second implementation of canvas resampling would diverge inside one
 * release.
 *
 * **Displaying and reading are two different addresses**, and the difference is
 * the taint rule. Drawing a cross-origin picture into an `<img>` is free; it is
 * reading its bytes back out that a browser refuses, and it refuses *after* the
 * person has finished composing their crop. So the frame is drawn over the
 * ordinary URL, and the bytes are fetched through this module's own route, which
 * is same-origin whatever disk the file is on.
 *
 * The route sits behind the application's middleware — `auth` by default — so
 * the fetch falls back to the plain URL when it will not answer. That is not a
 * weakening: where the route is unavailable, the plain URL is the only source
 * there ever was, and a cross-origin one fails loudly below rather than
 * producing a file that is silently the original.
 */
import { processImage } from '../../../forms/resources/js/image-processor.js'

/** A phone will not allocate a canvas for a 40 MP source; say so instead of dying. */
const MAX_PIXELS = 40_000_000

export function wireMediaEditor(config = {}) {
    return {
        // What the frame is drawn over.
        src: config.src ?? null,
        // Where the bytes come from: same-origin, so nothing can taint.
        source: config.source ?? config.src ?? null,
        name: config.name ?? 'image',
        // Fractions of the source, which is what `processImage` takes: the frame
        // is drawn over an <img> that preserves aspect, so a fraction of the
        // element is a fraction of the picture.
        frame: { x: 0, y: 0, width: 1, height: 1 },
        ratio: null,
        rotate: 0,
        flip: false,
        targetWidth: null,
        format: '',
        busy: false,
        error: null,
        natural: { width: 0, height: 0 },
        drag: null,

        init() {
            this.$watch('ratio', () => this.applyRatio())

            // `x-on:load` is not enough. A picture the browser already has is
            // `complete` before Alpine binds anything, and then `load` never
            // fires — leaving the editor with no dimensions, every ratio a
            // no-op, and a "crop" that saves the original back unchanged.
            //
            // In `$nextTick`, because `$refs` are not populated during a
            // parent's own init.
            this.$nextTick(() => {
                const image = this.$refs.image

                if (image?.complete && image.naturalWidth > 0) this.measure()
            })
        },

        /**
         * Measured once the picture has loaded, because the ratio maths needs the
         * source's own proportions: a frame that is 50% wide and 50% tall is only
         * square on a square photograph.
         */
        measure() {
            const image = this.$refs.image
            if (! image) return

            this.natural = { width: image.naturalWidth, height: image.naturalHeight }

            if (this.natural.width * this.natural.height > MAX_PIXELS) {
                this.error = config.messages?.tooLarge ?? 'This image is too large to edit in the browser.'
            }

            this.applyRatio()
        },

        get tooLarge() {
            return this.natural.width * this.natural.height > MAX_PIXELS
        },

        /** The output size, so the person can see what they are about to save. */
        get outputSize() {
            const width = Math.round(this.natural.width * this.frame.width)
            const height = Math.round(this.natural.height * this.frame.height)

            const scale = this.targetWidth && this.targetWidth < width ? this.targetWidth / width : 1
            const scaled = [Math.round(width * scale), Math.round(height * scale)]

            // A quarter turn swaps the axes, and the number a person is reading
            // is the number they will get.
            return (this.rotate === 90 || this.rotate === 270) ? [scaled[1], scaled[0]] : scaled
        },

        /** Re-shape the frame to the chosen ratio, keeping its centre. */
        applyRatio() {
            if (! this.ratio || ! this.natural.width) return

            const [w, h] = String(this.ratio).split(':').map(Number)
            if (! (w > 0 && h > 0)) return

            const target = w / h
            const centreX = this.frame.x + this.frame.width / 2
            const centreY = this.frame.y + this.frame.height / 2

            // Width and height are fractions of *different* lengths, so the ratio
            // between them is not the ratio of the picture until the source's own
            // proportions are put back in.
            let width = this.frame.width
            let height = (width * this.natural.width) / (target * this.natural.height)

            if (height > 1) {
                height = 1
                width = (target * this.natural.height * height) / this.natural.width
            }

            this.frame = this.clampFrame({
                x: centreX - width / 2,
                y: centreY - height / 2,
                width,
                height,
            })
        },

        clampFrame(frame) {
            const width = Math.min(1, Math.max(0.05, frame.width))
            const height = Math.min(1, Math.max(0.05, frame.height))

            return {
                width,
                height,
                x: Math.min(1 - width, Math.max(0, frame.x)),
                y: Math.min(1 - height, Math.max(0, frame.y)),
            }
        },

        startDrag(event, handle = null) {
            const box = this.$refs.stage?.getBoundingClientRect()
            if (! box) return

            event.preventDefault()

            this.drag = {
                handle,
                box,
                startX: event.clientX,
                startY: event.clientY,
                frame: { ...this.frame },
            }
        },

        onDrag(event) {
            if (! this.drag) return

            const dx = (event.clientX - this.drag.startX) / this.drag.box.width
            const dy = (event.clientY - this.drag.startY) / this.drag.box.height
            const start = this.drag.frame

            if (! this.drag.handle) {
                this.frame = this.clampFrame({ ...start, x: start.x + dx, y: start.y + dy })

                return
            }

            const right = this.drag.handle.includes('r')
            const bottom = this.drag.handle.includes('b')

            let width = right ? start.width + dx : start.width - dx
            let height = bottom ? start.height + dy : start.height - dy

            // A locked ratio takes its height from its width, so dragging any
            // corner keeps the shape the person chose.
            if (this.ratio && this.natural.width) {
                const [w, h] = String(this.ratio).split(':').map(Number)
                if (w > 0 && h > 0) {
                    height = (width * this.natural.width) / ((w / h) * this.natural.height)
                }
            }

            this.frame = this.clampFrame({
                width,
                height,
                x: right ? start.x : start.x + (start.width - width),
                y: bottom ? start.y : start.y + (start.height - height),
            })
        },

        endDrag() {
            this.drag = null
        },

        turn(degrees) {
            this.rotate = ((this.rotate + degrees) % 360 + 360) % 360
        },

        reset() {
            this.frame = { x: 0, y: 0, width: 1, height: 1 }
            this.ratio = null
            this.rotate = 0
            this.flip = false
            this.targetWidth = null
            this.format = ''
            this.error = null
        },

        /**
         * `intent` is `new` or `replace`, and it is set on the server before the
         * upload rather than passed with it: Livewire's upload carries a file and
         * nothing else, and a second round trip afterwards would be a window in
         * which a refresh replaces the wrong thing.
         */
        /**
         * The original's bytes, through the route first and the plain URL after.
         *
         * Two attempts rather than one because the route is behind the
         * application's middleware and may refuse this request while the picture
         * on screen — served straight from a public disk — loads perfectly well.
         * Falling back is what keeps the editor working there; a cross-origin
         * fallback fails on its own and is reported.
         */
        async readOriginal() {
            const addresses = [...new Set([this.source, this.src].filter(Boolean))]

            for (const address of addresses) {
                try {
                    const response = await fetch(address, { credentials: 'same-origin' })

                    if (response.ok) return await response.blob()
                } catch {
                    // Try the next address; the throw below is what reports the
                    // case where there is no next address.
                }
            }

            throw new Error('unreadable')
        },

        async save(intent) {
            if (this.busy || this.tooLarge) return

            this.busy = true
            this.error = null

            try {
                const blob = await this.readOriginal()
                const original = new File([blob], this.name, { type: blob.type })

                const edited = await processImage(original, {
                    crop: this.frame,
                    rotate: this.rotate,
                    flip: this.flip,
                    targetWidth: this.targetWidth ? Number(this.targetWidth) : null,
                    format: this.format || null,
                })

                await this.$wire.set('editorIntent', intent)

                this.$wire.upload(
                    'editorUpload',
                    edited,
                    () => { this.busy = false },
                    () => {
                        this.busy = false
                        this.error = config.messages?.failed ?? 'Saving failed.'
                    },
                )
            } catch {
                this.busy = false
                // Said out loud rather than swallowed: a cross-origin source with
                // no CORS headers, a file the disk no longer has, a network that
                // dropped. All of them look the same to a person watching a
                // button do nothing.
                this.error = config.messages?.unreadable ?? 'This image could not be read for editing.'
            }
        },
    }
}

// ─── Self-registration ──────────────────────────────────────────
// `alpine:init` fires exactly once per document, so a bundle that only listens
// for it registers nothing when it arrives after a `wire:navigate`. Register
// straight away when Alpine is already running; keep the listener for the first,
// cold load. The `registered` guard is load-bearing: the same src can be emitted
// twice (a per-surface partial plus the layout tag).
let registered = false

const registerWireMediaEditor = () => {
    if (registered || ! window.Alpine) return
    registered = true

    window.Alpine.data('wireMediaEditor', wireMediaEditor)
}

if (window.Alpine) {
    registerWireMediaEditor()
} else {
    document.addEventListener('alpine:init', registerWireMediaEditor)
}
