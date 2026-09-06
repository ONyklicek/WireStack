/**
 * Slider's controller: the value, and the fill behind the thumb.
 *
 * The track is painted with a gradient whose stop follows the value, because a
 * native `<input type="range">` gives no way to colour the part left of the
 * thumb. The two CSS variables are set by the view, so a themed slider needs no
 * JavaScript of its own.
 */
const wireSlider = (config = {}) => ({
    value: config.state,
    min: config.min ?? 0,
    max: config.max ?? 100,

    init() {
        // An empty slider still has to sit somewhere, and the low end is the
        // only defensible place — a thumb at the middle would read as a value
        // nobody chose.
        if (this.value === null || this.value === undefined || this.value === '') {
            this.value = this.min
        }
    },

    get percent() {
        const span = this.max - this.min

        if (span <= 0) return 0

        return Math.min(100, Math.max(0, ((this.value - this.min) / span) * 100))
    },

    get trackBackground() {
        return `linear-gradient(to right, var(--wf-fill) ${this.percent}%, var(--wf-track) ${this.percent}%)`
    },
})

export default wireSlider
