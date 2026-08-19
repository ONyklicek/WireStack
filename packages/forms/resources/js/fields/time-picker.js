import { typing } from './typing'

/**
 * TimePicker's slot-list controller.
 *
 * Its formatter is the time half of the DateTimePicker's and stays in step with
 * it deliberately; `typing.js`'s `formatState()` is not reused because this one
 * formats a bare `H:i:s` slot rather than a full state string, and slots are
 * formatted in a loop over the whole day.
 *
 * `config.typeable` is a runtime branch where the view had a Blade `@if`: an
 * Alpine.data factory is compiled once for every instance, so what used to vary
 * the object's shape has to vary its behaviour instead.
 */
const wireTimePicker = (config = {}) => ({
    ...typing(),

    open: false,
    value: config.state,

    hasSeconds: config.hasSeconds ?? false,
    interval: config.interval ?? 15,
    minTime: config.minTime ?? null,
    maxTime: config.maxTime ?? null,
    displayFormat: config.displayFormat ?? null,
    typedFormat: config.typedFormat ?? null,
    typeable: config.typeable ?? false,
    sheetOnMobile: config.sheetOnMobile ?? false,
    sheetBreakpoint: config.sheetBreakpoint ?? null,

    slots: [],
    _float: null,

    init() {
        this.buildSlots()

        this.$watch('open', (open) => {
            if (open) {
                this.$nextTick(() => {
                    const options = { placement: 'bottom-start', offset: 4 }

                    if (this.sheetOnMobile) {
                        options.sheetOnMobile = true
                        options.sheetBreakpoint = this.sheetBreakpoint
                    }

                    this._float = this.$float(this.$refs.trigger, this.$refs.panel, options)
                    // A list that always opened at 00:00 would make every afternoon
                    // a scroll; land on the current choice, or on the first slot
                    // the bounds allow.
                    this.scrollToActive()
                })
            } else if (this._float) {
                this._float()
                this._float = null
            }
        })
    },

    pad(n) { return String(n).padStart(2, '0') },

    // Every slot of the day, at the field's interval. The interval is clamped to
    // >= 1 server-side, so this always terminates.
    buildSlots() {
        const out = []
        for (let m = 0; m < 24 * 60; m += this.interval) {
            const value = this.pad(Math.floor(m / 60)) + ':' + this.pad(m % 60)
                + (this.hasSeconds ? ':00' : '')
            out.push({ value, label: this.format(value), disabled: this.isDisabled(value) })
        }
        this.slots = out
    },

    // Compare on a seconds-bearing string, the shape the bounds are in.
    normalize(time) {
        if (! time) return null
        const parts = String(time).split(':')
        return this.pad(parts[0] ?? 0) + ':' + this.pad(parts[1] ?? 0) + ':' + this.pad(parts[2] ?? 0)
    },

    isDisabled(time) {
        const t = this.normalize(time)
        if (this.minTime && t < this.normalize(this.minTime)) return true
        if (this.maxTime && t > this.normalize(this.maxTime)) return true
        return false
    },

    /**
     * A stored value need not sit on a slot boundary (the interval can change
     * under existing data), so this matches the instant, not the string, and
     * simply highlights nothing when it falls between slots.
     */
    isSelected(time) {
        if (! this.value) return false
        return this.normalize(this.value) === this.normalize(time)
    },

    select(slot) {
        if (slot.disabled) return
        this.value = slot.value
        this.open = false
    },

    scrollToActive() {
        const list = this.$refs.list
        if (! list) return
        const target = list.querySelector('[data-active=\'true\']')
            ?? list.querySelector('button:not([disabled])')
        if (target) list.scrollTop = target.offsetTop - list.clientHeight / 2 + target.clientHeight / 2
    },

    /**
     * PHP date() tokens the picker can honour, on the time half only; anything
     * else passes through and `\x` escapes a literal. Kept in step with the
     * DateTimePicker's formatter.
     */
    format(time) {
        if (! this.displayFormat) return time

        const [h, mi, sec] = String(time).split(':')
        const num = (v) => String(parseInt(v ?? '0', 10) || 0)
        const tokens = { H: this.pad(h), G: num(h), i: this.pad(mi), s: this.pad(sec ?? '00') }

        let out = ''
        for (let i = 0; i < this.displayFormat.length; i++) {
            const c = this.displayFormat[i]
            if (c === '\\') { out += this.displayFormat[++i] ?? ''; continue }
            out += (c in tokens) ? tokens[c] : c
        }

        return out
    },

    /**
     * A typed time need not land on a slot. The interval is how the list *offers*
     * times, not a rule about which ones exist — isSelected() already tolerates a
     * stored value between two slots — and typing is the one way to say 08:07
     * when the list steps by fifteen. Only the bounds may refuse it.
     */
    applyTyped(parts) {
        if (! this.typeable) return false

        const hours = parts.hours ?? 0
        const minutes = parts.minutes ?? 0
        const seconds = this.hasSeconds ? (parts.seconds ?? 0) : 0
        if (hours > 23 || minutes > 59 || seconds > 59) return false

        const time = this.pad(hours) + ':' + this.pad(minutes)
            + (this.hasSeconds ? ':' + this.pad(seconds) : '')
        if (this.isDisabled(time)) return false

        this.value = time

        return true
    },

    get displayValue() {
        return this.value ? this.format(this.value) : ''
    },

    clear() {
        this.value = null
    },
})

export default wireTimePicker
