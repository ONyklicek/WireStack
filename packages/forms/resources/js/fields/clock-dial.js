/**
 * The clock dial: the desktop clock, drawn the way SAP's TimePickerClock is.
 *
 * Two big buttons read the time being chosen (`14 : 30`) and say which part
 * the dial is setting. The rim carries a dot for every value that can be
 * picked — a larger one under each number, a small one between — with the
 * numbers just inside it, so a face stepping by one minute shows sixty places
 * and a face stepping by fifteen shows four. There is no hand: the chosen
 * value is a marker on the rim and a filled bubble that reads it (`07` too,
 * between two numbers), and the value under the pointer gets a lighter one.
 * The hours face carries two rings — 1–12 outside, 13–23 and 00 inside — and
 * lifting off an hour turns it to the minutes. Where the host keeps a draft
 * (TimePicker), nothing reaches the value until OK.
 *
 * One controller can carry more than one clock — a range has a start and an
 * end — so the bound and clock lookups take `end`, which the host interprets;
 * null means "the one the dial is setting", and a single picker ignores it.
 *
 * Joined into a controller with `mix()`. The host must provide:
 *   - draft          { hours, minutes, seconds } the dial is editing (a getter
 *                    and setter where the dial sets the value live)
 *   - hasSeconds, minutesStep, secondsStep
 *   - timeBoundsFor(end)   { lower, upper } as `H:i:s`, or nulls, for the day
 *                          that clock is on
 *   - confirmDial()   Enter on the last part: commit and close
 */
const SIZE = 256
const CENTER = SIZE / 2
// Radii, from the rim in: the dots, the numbers, the inner hours.
const TICK = 121
const OUTER = 98
const INNER = 62

const at = (angle, radius) => {
    const rad = angle * Math.PI / 180
    return { x: CENTER + radius * Math.sin(rad), y: CENTER - radius * Math.cos(rad) }
}

export const clockDial = () => ({
    dialUnit: 'hours',
    _dialDragging: false,
    _dialPicked: false,
    dialHover: null,

    // The clock a bound check reads; a host with two clocks overrides it.
    timeOf() {
        return this.draft
    },

    setTimePart(_end, unit, v) {
        this.draft = { ...this.draft, [unit]: v }
        this.clampDraft()
    },

    get dialUnits() {
        return this.hasSeconds ? ['hours', 'minutes', 'seconds'] : ['hours', 'minutes']
    },

    // Pull the draft inside the bounds, the way a picked hour lands on 08:30
    // under an 08:30 minimum rather than being refused.
    clampDraft() {
        const { lower, upper } = this.timeBoundsFor(null)
        const p = (n) => String(n).padStart(2, '0')
        const t = this.draft
        const current = p(t.hours) + ':' + p(t.minutes) + ':' + p(t.seconds)
        const target = (lower && current < lower) ? lower : (upper && current > upper) ? upper : null
        if (! target) return
        const [h, m, s] = target.split(':').map((v) => parseInt(v, 10))
        this.draft = { hours: h, minutes: m, seconds: this.hasSeconds ? s : 0 }
    },

    dialStep(unit) {
        return Math.max(1, (unit === 'minutes' ? this.minutesStep : unit === 'seconds' ? this.secondsStep : 1) || 1)
    },

    isDialPickable(unit, v) {
        return v % this.dialStep(unit) === 0 && ! this.isPartDisabled(unit, v)
    },

    /**
     * Whether any instant this value stands for lies inside the bounds. An
     * hour is the span from :00:00 to :59:59 under it, a minute the span of
     * its seconds — so 08 stays pickable under an 08:30 minimum, and picking
     * it lands on 08:30 once the host clamps.
     */
    isPartDisabled(unit, v) {
        const { lower, upper } = this.timeBoundsFor(null)
        if (! lower && ! upper) return false

        const t = this.draft
        const p = (n) => String(n).padStart(2, '0')
        let from, to
        if (unit === 'hours') {
            from = p(v) + ':00:00'
            to = p(v) + ':59:59'
        } else if (unit === 'minutes') {
            from = p(t.hours) + ':' + p(v) + ':00'
            to = p(t.hours) + ':' + p(v) + ':59'
        } else {
            from = to = p(t.hours) + ':' + p(t.minutes) + ':' + p(v)
        }

        return (!! lower && to < lower) || (!! upper && from > upper)
    },

    // Where a value sits on the face: its angle (0° up, clockwise) and ring.
    dialSpot(unit, v) {
        if (unit === 'hours') {
            const inner = v === 0 || v > 12
            return { angle: (v % 12) * 30, radius: inner ? INNER : OUTER, inner }
        }
        return { angle: v * 6, radius: OUTER, inner: false }
    },

    dialLabel(unit, v) {
        return unit === 'hours' && v !== 0 && v <= 12 ? String(v) : String(v).padStart(2, '0')
    },

    // The dots on the rim: one under every number, and one at each value
    // between two numbers that the step reaches and the bounds allow.
    get dialTicks() {
        const unit = this.dialUnit
        // A number that cannot be picked keeps its dot, faded, so the rim stays
        // a clock face without claiming the value.
        if (unit === 'hours') {
            return Array.from({ length: 12 }, (_, i) => ({
                key: 'h' + i,
                big: true,
                muted: ! this.isDialPickable(unit, i === 0 ? 12 : i) && ! this.isDialPickable(unit, i === 0 ? 0 : i + 12),
                ...at(i * 30, TICK),
            }))
        }

        const ticks = []
        for (let v = 0; v < 60; v++) {
            const big = v % 5 === 0
            const pickable = this.isDialPickable(unit, v)
            if (! big && ! pickable) continue
            ticks.push({ key: unit + v, big, muted: ! pickable, ...at(v * 6, TICK) })
        }

        return ticks
    },

    get dialLabels() {
        const unit = this.dialUnit
        const values = unit === 'hours'
            ? [12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 0, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23]
            : Array.from({ length: 12 }, (_, i) => i * 5)

        return values.map((v) => {
            const { angle, radius, inner } = this.dialSpot(unit, v)
            return { value: v, label: this.dialLabel(unit, v), inner, disabled: ! this.isDialPickable(unit, v), ...at(angle, radius) }
        })
    },

    // A value as the face shows it chosen: the bubble at its number's place,
    // and the marker on the rim above it.
    dialMark(v) {
        if (v === null || v === undefined) return null
        const unit = this.dialUnit
        const { angle, radius, inner } = this.dialSpot(unit, v)
        const rim = at(angle, TICK)
        return { label: this.dialLabel(unit, v), inner, angle, ...at(angle, radius), mx: rim.x, my: rim.y }
    },

    get dialSelected() {
        return this.dialMark(this.draft[this.dialUnit])
    },

    get dialHovered() {
        return this.dialHover === this.draft[this.dialUnit] ? null : this.dialMark(this.dialHover)
    },

    get dialValueText() {
        const p = (n) => String(n).padStart(2, '0')
        return p(this.draft.hours) + ':' + p(this.draft.minutes) + (this.hasSeconds ? ':' + p(this.draft.seconds) : '')
    },

    // The value under a pointer: the nearest spot on the ring it is nearest to.
    dialValueAt(event) {
        const rect = this.$refs.dial.getBoundingClientRect()
        const dx = event.clientX - rect.left - rect.width / 2
        const dy = event.clientY - rect.top - rect.height / 2
        const angle = (Math.atan2(dx, -dy) * 180 / Math.PI + 360) % 360
        const distance = Math.hypot(dx, dy) * (SIZE / rect.width)

        if (this.dialUnit === 'hours') {
            const at = Math.round(angle / 30) % 12
            return distance < (INNER + OUTER) / 2 ? (at === 0 ? 0 : at + 12) : (at === 0 ? 12 : at)
        }

        const step = this.dialStep(this.dialUnit)
        return (Math.round(Math.round(angle / 6) / step) * step) % 60
    },

    pickDial(v) {
        if (! this.isDialPickable(this.dialUnit, v)) return false
        this.setTimePart(null, this.dialUnit, v)
        return true
    },

    dialDown(event) {
        this._dialDragging = true
        this.$refs.dial.setPointerCapture?.(event.pointerId)
        this._dialPicked = this.pickDial(this.dialValueAt(event))
    },

    // While dragging the hand follows; otherwise the value under the pointer
    // is previewed, the way the SAP clock lights it before the click.
    dialMove(event) {
        const v = this.dialValueAt(event)
        if (this._dialDragging) {
            if (this.pickDial(v)) this._dialPicked = true
            return
        }
        this.dialHover = this.isDialPickable(this.dialUnit, v) ? v : null
    },

    dialLeave() {
        this.dialHover = null
    },

    // Lifting off an hour moves on to the minutes (and minutes to seconds) —
    // but only after a pick: a press on a greyed hour stays on the hours.
    dialUp() {
        if (! this._dialDragging) return
        this._dialDragging = false
        if (this._dialPicked) this.advanceDial()
        this._dialPicked = false
    },

    advanceDial() {
        const index = this.dialUnits.indexOf(this.dialUnit)
        if (index < this.dialUnits.length - 1) this.dialUnit = this.dialUnits[index + 1]
        this.dialHover = null
    },

    showDialUnit(unit) {
        this.dialUnit = unit
        this.dialHover = null
        this.$nextTick(() => this.$refs.dial?.focus())
    },

    /**
     * Arrows step the part being set, skipping what the bounds exclude and
     * wrapping round the face; Enter moves on, or confirms on the last part.
     */
    dialKey(event) {
        const unit = this.dialUnit
        if (event.key === 'Enter') {
            event.preventDefault()
            unit === this.dialUnits.at(-1) ? this.confirmDial() : this.advanceDial()
            return
        }

        const dir = { ArrowUp: 1, ArrowRight: 1, ArrowDown: -1, ArrowLeft: -1 }[event.key]
        if (! dir) return
        event.preventDefault()

        const size = unit === 'hours' ? 24 : 60
        const step = this.dialStep(unit)
        let v = this.draft[unit]
        for (let i = 0; i < size; i++) {
            v = ((v + dir * step) % size + size) % size
            if (this.isDialPickable(unit, v)) return this.setTimePart(null, unit, v)
        }
    },
})
