import { typing, formatState } from './typing'
import { calendar } from './calendar'
import { clockDial } from './clock-dial'
import { mix } from './mixin'

/**
 * DateRangePicker's desktop control: one box reading `1. 10. 2026 – 31. 10.
 * 2026`, and one panel with two months side by side, the offered periods
 * beside them and — with `withTime()` — one clock dial with a From / To
 * switch above it, setting whichever end is chosen.
 *
 * The period is still the two columns the layout always wrote: `from` and `to`
 * are entangled with the two pickers' own state paths, so filling, validation
 * and saving are theirs and nothing here invents a state shape. Below the
 * breakpoint those two pickers are what the phone sees, untouched.
 *
 * The first click on a day starts the period and the second one ends it, with
 * the span between them previewed under the pointer (or the keyboard focus)
 * meanwhile. A second day before the first is read as the period backwards,
 * not refused — the two are swapped.
 */
const wireDateRangePicker = (config = {}) => mix(typing(), calendar(), clockDial(), {

    open: false,
    from: config.from,
    to: config.to,

    monthsShown: 2,
    firstDayOfWeek: config.firstDayOfWeek ?? 1,
    disabledDates: config.disabledDates ?? [],
    minDay: config.minDay ?? null,
    maxDay: config.maxDay ?? null,
    minTime: config.minTime ?? null,
    maxTime: config.maxTime ?? null,
    windowMin: config.windowMin ?? null,
    windowMax: config.windowMax ?? null,
    withTime: config.withTime ?? false,
    hasSeconds: false,
    hoursStep: config.hoursStep ?? 1,
    minutesStep: config.minutesStep ?? 1,
    secondsStep: 1,
    displayFormat: config.displayFormat ?? null,
    typedFormat: config.typedFormat ?? null,
    typeable: config.typeable ?? false,
    presets: config.presets ?? [],

    currentMonth: null,
    currentYear: null,

    // The day the first click landed on, while the second is awaited.
    anchor: null,
    hoverDay: null,
    // The end the dial is setting: 'from' or 'to'.
    dialEnd: 'from',
    fromTime: { hours: 0, minutes: 0, seconds: 0 },
    toTime: { hours: 0, minutes: 0, seconds: 0 },

    _float: null,

    init() {
        this.fromTime = this.readTime(this.from, { hours: 0, minutes: 0 })
        // An end with no time yet ends on the last step of its day, so a fresh
        // one-day period is that whole day rather than a zero-length instant.
        const step = Math.max(1, this.minutesStep || 1)
        this.toTime = this.readTime(this.to, { hours: 23, minutes: 60 - step })

        this.showMonthOf(this.clampDay(this.dayOf(this.from) || this.todayStr()))
        this.buildDayNames()

        this.$watch('open', (open) => {
            if (open) {
                this.view = 'days'
                this.dialEnd = 'from'
                this.dialUnit = 'hours'
                const start = this.dayOf(this.from)
                if (start) this.revealDay(start)
                this.$nextTick(() => {
                    this._float = this.$float(this.$refs.trigger, this.$refs.panel, { placement: 'bottom-start', offset: 4 })
                })
            } else {
                // Half a period is not an answer; closing drops it.
                this.anchor = null
                this.hoverDay = null
                if (this._float) {
                    this._float()
                    this._float = null
                }
            }
        })
    },

    dayOf(value) {
        return value ? String(value).split(/[T ]/)[0] : null
    },

    readTime(value, fallback) {
        const clock = value ? String(value).split(/[T ]/)[1] : null
        if (! clock) return { ...fallback, seconds: 0 }
        const [h, m] = clock.split(':').map((v) => parseInt(v, 10))
        return { hours: h || 0, minutes: m || 0, seconds: 0 }
    },

    timeStr(time) {
        return this.pad(time.hours) + ':' + this.pad(time.minutes)
    },

    focusAnchor() {
        return this.anchor ?? this.dayOf(this.from)
    },

    openWithKeyboard() {
        this.open = true
        this.$nextTick(() => this.focusGrid())
    },

    closeToTrigger() {
        this.open = false
        this.$refs.trigger?.querySelector('input')?.focus()
    },

    // ─── The span on screen ────────────────────────────────────────

    // The two ends being drawn: the preview while picking, else the value.
    get span() {
        if (this.anchor) {
            const other = this.hoverDay ?? this.anchor
            return other < this.anchor ? [other, this.anchor] : [this.anchor, other]
        }
        return [this.dayOf(this.from), this.dayOf(this.to)]
    },

    isRangeStart(day) {
        return !! day && day === this.span[0]
    },

    isRangeEnd(day) {
        return !! day && day === this.span[1]
    },

    isRangeEdge(day) {
        return this.isRangeStart(day) || this.isRangeEnd(day)
    },

    // The band runs under the days between the ends, and half into each end, so
    // the period reads as one shape across a week row.
    bandLeft(day) {
        const [a, b] = this.span
        return !! (day && a && b && a !== b && day > a && day <= b)
    },

    bandRight(day) {
        const [a, b] = this.span
        return !! (day && a && b && a !== b && day >= a && day < b)
    },

    onDayHover(day) {
        if (this.anchor && day) this.hoverDay = day
    },

    // calendar.js calls this as the keyboard walks the grid.
    onDayFocused(day) {
        this.onDayHover(day)
    },

    pickDay(day) {
        if (this.isDisabled(day)) return
        this.focusDay = day

        if (! this.anchor) {
            this.anchor = day
            this.hoverDay = day
            return
        }

        const [a, b] = day < this.anchor ? [day, this.anchor] : [this.anchor, day]
        this.anchor = null
        this.hoverDay = null
        this.commitRange(a, b)

        if (! this.withTime) this.closeToTrigger()
    },

    commitRange(start, end) {
        if (! this.withTime) {
            this.from = start
            this.to = end
            return
        }

        this.clampEnd('from', start)
        this.clampEnd('to', end)
        this.from = start + ' ' + this.timeStr(this.fromTime)
        this.to = end + ' ' + this.timeStr(this.toTime)
        this.orderTimes()
    },

    // ─── Presets ───────────────────────────────────────────────────

    applyPreset(preset) {
        this.anchor = null
        this.hoverDay = null
        this.view = 'days'
        this.commitRange(preset.from, preset.to)
        this.showMonthOf(preset.from)
        if (! this.withTime) this.closeToTrigger()
    },

    isActivePreset(preset) {
        return ! this.anchor && this.dayOf(this.from) === preset.from && this.dayOf(this.to) === preset.to
    },

    // ─── The two clocks (clock-dial.js host) ──────
    //
    // One dial sets both ends: an `end` of null means the one the From / To
    // switch has chosen.

    timeOf(end = null) {
        return (end ?? this.dialEnd) === 'to' ? this.toTime : this.fromTime
    },

    // The dial's draft is the chosen end's own clock, written as it moves.
    get draft() {
        return { ...this.timeOf(), seconds: 0 }
    },

    set draft(time) {
        const end = this.dialEnd
        const own = this.timeOf(end)
        own.hours = time.hours
        own.minutes = time.minutes
        this.writeEnd(end)
    },

    showDialEnd(end) {
        this.dialEnd = end
        this.dialUnit = 'hours'
    },

    // Enter on the minutes: on from the start to the end, then done.
    confirmDial() {
        if (this.dialEnd === 'from') this.showDialEnd('to')
        else this.closeToTrigger()
    },

    timeBoundsFor(end = null, day = undefined) {
        end = end ?? this.dialEnd
        if (day === undefined) day = this.dayOf(end === 'to' ? this.to : this.from)
        const lowers = [this.windowMin, day && day === this.minDay ? this.minTime : null].filter(Boolean)
        const uppers = [this.windowMax, day && day === this.maxDay ? this.maxTime : null].filter(Boolean)

        return {
            lower: lowers.length ? lowers.sort().at(-1) : null,
            upper: uppers.length ? uppers.sort()[0] : null,
        }
    },

    // Pull one end's clock inside the bounds of the day it is on.
    clampEnd(end, day) {
        const time = this.timeOf(end)
        const { lower, upper } = this.timeBoundsFor(end, day)
        const current = this.timeStr(time) + ':00'
        const target = (lower && current < lower) ? lower : (upper && current > upper) ? upper : null
        if (! target) return
        const [h, m] = target.split(':')
        time.hours = parseInt(h, 10)
        time.minutes = parseInt(m, 10)
    },

    // On a one-day period the end cannot come before the start.
    orderTimes() {
        if (this.from && this.to && this.from > this.to) {
            this.toTime = { ...this.fromTime }
            this.to = this.dayOf(this.to) + ' ' + this.timeStr(this.toTime)
        }
    },

    setTimePart(end, unit, v) {
        end = end ?? this.dialEnd
        this.timeOf(end)[unit] = v
        this.writeEnd(end)
    },

    // Write one end's clock into its value, inside the bounds of its day. An
    // end with no day yet keeps the clock for when it gets one.
    writeEnd(end) {
        const time = this.timeOf(end)
        const day = this.dayOf(end === 'to' ? this.to : this.from)
        if (! day) return
        this.clampEnd(end, day)
        if (end === 'to') this.to = day + ' ' + this.timeStr(time)
        else this.from = day + ' ' + this.timeStr(time)
        this.orderTimes()
    },

    // ─── The box ───────────────────────────────────────────────────

    get displayValue() {
        if (! this.from && ! this.to) return ''
        return formatState(this.from, this.displayFormat) + ' – ' + formatState(this.to, this.displayFormat)
    },

    /**
     * A typed period is two dates in the box's own format, split on a dash
     * with space around it or an en dash. Both halves go through the same
     * reader and the same day rules as a click; either half refused puts the
     * last good period back.
     */
    commitTyped() {
        if (! this.typing) return true

        this.typing = false
        const text = this.typed.trim()
        this.typed = ''

        if (text === '') {
            this.clear()
            return true
        }
        if (! this.typeable) return false

        const halves = text.split(/\s*[–—]\s*|\s+-\s+/)
        if (halves.length !== 2) return false

        const days = []
        const times = []
        for (const half of halves) {
            const parts = this.readTyped(half)
            if (! parts) return false
            const { year, month, day } = parts
            if (! year || ! month || ! day || month > 12 || day < 1) return false
            if (day > new Date(year, month, 0).getDate()) return false
            const dateStr = this.formatDateStr(year, month, day)
            if (this.isDisabled(dateStr)) return false
            if ((parts.hours ?? 0) > 23 || (parts.minutes ?? 0) > 59) return false
            days.push(dateStr)
            times.push(parts.hours === undefined ? null : { hours: parts.hours, minutes: parts.minutes ?? 0, seconds: 0 })
        }

        const swap = days[1] < days[0]
        const [a, b] = swap ? [days[1], days[0]] : days
        const [ta, tb] = swap ? [times[1], times[0]] : times
        if (this.withTime) {
            if (ta) this.fromTime = ta
            if (tb) this.toTime = tb
        }
        this.commitRange(a, b)
        this.showMonthOf(a)

        return true
    },

    clear() {
        this.anchor = null
        this.hoverDay = null
        this.from = null
        this.to = null
    },
})

export default wireDateRangePicker
