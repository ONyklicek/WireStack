/**
 * ColorPicker's controller: one value, two notations.
 *
 * `format()` decides what the *state* holds, but `<input type="color">` only
 * ever speaks hex — so the swatch is driven by a hex mirror and every write is
 * converted back to the configured notation. That conversion is this whole
 * body, and it was inlined in the view: ~75 lines of parser and colour maths
 * shipped again for every colour field on the page, the largest per-instance
 * blob left in wire-forms after the other controllers moved out
 * (architecture/plans/forms-and-surfaces-performance.md § 3).
 *
 * `color` arrives from the x-data expression because `@entangle` compiles to an
 * Alpine magic, which is in scope only there.
 */
const wireColorPicker = (config = {}) => ({
    format: config.format ?? 'hex',
    color: config.state,

    /** Any supported notation → {r,g,b,a}; unparseable → black, so the swatch never breaks. */
    parse(value) {
        const v = String(value ?? '').trim()
        let m

        if ((m = v.match(/^#?([0-9a-f]{3})$/i))) {
            const [r, g, b] = m[1].split('').map((c) => parseInt(c + c, 16))

            return { r, g, b, a: 1 }
        }
        if ((m = v.match(/^#?([0-9a-f]{6})$/i))) {
            const n = parseInt(m[1], 16)

            return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255, a: 1 }
        }
        if ((m = v.match(/^rgba?\(([^)]+)\)$/i))) {
            const p = m[1].split(',').map((x) => parseFloat(x))

            return { r: p[0] | 0, g: p[1] | 0, b: p[2] | 0, a: p[3] ?? 1 }
        }
        if ((m = v.match(/^hsla?\(([^)]+)\)$/i))) {
            const p = m[1].split(',').map((x) => parseFloat(x))

            return { ...this.hslToRgb(p[0], p[1], p[2]), a: p[3] ?? 1 }
        }

        return { r: 0, g: 0, b: 0, a: 1 }
    },

    hslToRgb(h, s, l) {
        s /= 100
        l /= 100
        const k = (n) => (n + h / 30) % 12
        const a = s * Math.min(l, 1 - l)
        const f = (n) => Math.round(255 * (l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)))))

        return { r: f(0), g: f(8), b: f(4) }
    },

    rgbToHsl({ r, g, b }) {
        r /= 255
        g /= 255
        b /= 255
        const max = Math.max(r, g, b), min = Math.min(r, g, b)
        const l = (max + min) / 2
        let h = 0, s = 0

        if (max !== min) {
            const d = max - min
            s = l > 0.5 ? d / (2 - max - min) : d / (max + min)
            h = max === r ? ((g - b) / d + (g < b ? 6 : 0)) : max === g ? (b - r) / d + 2 : (r - g) / d + 4
            h *= 60
        }

        return { h: Math.round(h), s: Math.round(s * 100), l: Math.round(l * 100) }
    },

    /** {r,g,b,a} → the configured notation. */
    stringify(c) {
        const hex = '#' + [c.r, c.g, c.b].map((n) => n.toString(16).padStart(2, '0')).join('')

        if (this.format === 'rgb') return `rgb(${c.r}, ${c.g}, ${c.b})`
        if (this.format === 'rgba') return `rgba(${c.r}, ${c.g}, ${c.b}, ${c.a})`
        if (this.format === 'hsl') {
            const h = this.rgbToHsl(c)

            return `hsl(${h.h}, ${h.s}%, ${h.l}%)`
        }

        return hex
    },

    /** The native swatch is hex-only, whatever the state holds. */
    get hex() {
        const c = this.parse(this.color)

        return '#' + [c.r, c.g, c.b].map((n) => n.toString(16).padStart(2, '0')).join('')
    },

    set hex(value) {
        this.color = this.stringify(this.parse(value))
    },

    pick(value) {
        this.color = this.stringify(this.parse(value))
    },
})

export default wireColorPicker
