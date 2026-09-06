/**
 * SignaturePad's canvas controller.
 *
 * Drawing is pointer-based rather than mouse+touch: one set of events covers a
 * stylus, a finger and a mouse, and `setPointerCapture` keeps a stroke that
 * leaves the canvas attached to it instead of ending mid-letter.
 *
 * Two details are what make a signature legible rather than blocky:
 *
 *  - the canvas is sized in device pixels and scaled back down, so a stroke on
 *    a 2× or 3× screen is drawn at that screen's resolution;
 *  - the backing store is only re-created on a real width change. Assigning
 *    `canvas.width` clears the canvas, so a naive resize handler would wipe a
 *    signature every time a mobile browser's toolbar slid away.
 *
 * State is written on pointer-up, not per point: `toDataURL()` re-encodes the
 * whole image, and doing that per pointermove would push a base64 PNG through
 * Livewire dozens of times per stroke.
 */
const wireSignaturePad = (config = {}) => ({
    state: config.state,
    penColor: config.penColor ?? '#111827',
    penWidth: config.penWidth ?? 2,
    backgroundColor: config.backgroundColor ?? null,
    disabled: config.disabled ?? false,
    imageUrl: config.imageUrl ?? null,

    drawing: false,
    empty: true,
    width: 0,

    init() {
        this.resize()

        // The existing signature is drawn onto the canvas, so an edit starts
        // from what is already stored rather than from a blank pad.
        if (this.imageUrl) this.load(this.imageUrl)

        window.addEventListener('resize', () => this.resize())
    },

    canvas() {
        return this.$refs.canvas
    },

    context() {
        const context = this.canvas().getContext('2d')

        context.lineCap = 'round'
        context.lineJoin = 'round'
        context.strokeStyle = this.penColor
        context.lineWidth = this.penWidth

        return context
    },

    /** Size the backing store to the device's pixels — and only when it changed. */
    resize() {
        const canvas = this.canvas()
        const width = canvas.offsetWidth

        if (! width || width === this.width) return

        const ratio = window.devicePixelRatio || 1
        const drawing = this.empty ? null : canvas.toDataURL('image/png')

        this.width = width
        canvas.width = width * ratio
        canvas.height = canvas.offsetHeight * ratio
        canvas.getContext('2d').scale(ratio, ratio)

        this.paintBackground()

        if (drawing) this.load(drawing)
    },

    paintBackground() {
        if (! this.backgroundColor) return

        const context = this.canvas().getContext('2d')

        context.fillStyle = this.backgroundColor
        context.fillRect(0, 0, this.canvas().offsetWidth, this.canvas().offsetHeight)
    },

    load(source) {
        const image = new Image

        image.crossOrigin = 'anonymous'
        image.onload = () => {
            this.canvas().getContext('2d').drawImage(image, 0, 0, this.canvas().offsetWidth, this.canvas().offsetHeight)
            this.empty = false
        }
        image.src = source
    },

    point(event) {
        const rect = this.canvas().getBoundingClientRect()

        return { x: event.clientX - rect.left, y: event.clientY - rect.top }
    },

    start(event) {
        if (this.disabled) return

        this.drawing = true
        this.empty = false
        event.target.setPointerCapture(event.pointerId)

        const { x, y } = this.point(event)
        const context = this.context()

        context.beginPath()
        context.moveTo(x, y)
        // A tap with no movement is a dot, and a signature's dots matter.
        context.lineTo(x, y)
        context.stroke()
    },

    draw(event) {
        if (! this.drawing) return

        const { x, y } = this.point(event)
        const context = this.canvas().getContext('2d')

        context.lineTo(x, y)
        context.stroke()
    },

    stop() {
        if (! this.drawing) return

        this.drawing = false
        this.state = this.canvas().toDataURL('image/png')
    },

    clear() {
        if (this.disabled) return

        const canvas = this.canvas()

        canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height)
        this.paintBackground()
        this.empty = true
        this.imageUrl = null
        this.state = ''
    },
})

export default wireSignaturePad
