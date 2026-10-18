import { typing, formatState } from './typing'
import { calendar } from './calendar'
import { clockDial } from './clock-dial'
import { mix } from './mixin'

/**
 * DateTimePicker's calendar-and-clock controller.
 *
 * Registered once as `wireDateTimePicker` rather than inlined per field: the
 * body is ~340 lines and identical for every picker on the page, and it measured
 * 28.4 kB of HTML per field — the largest per-instance blob in wire-forms. What
 * stays in the markup is the per-instance config plus `state`, which has to be
 * built in the `x-data` expression because `$wire.entangle` is an Alpine magic
 * and magics are only in scope there.
 *
 * The grid, its month and year views and its keyboard are `calendar.js`; the
 * desktop clock is `clock-dial.js`, the dial TimePicker draws, set live here
 * (its `draft` is this picker's own clock) rather than confirmed with OK. What stays here is what one
 * picked value means — a day, a clock, or both — and the split at the mobile
 * breakpoint: below it the panel is the phone's (steppers, a sheet) and stays
 * exactly that; from it up, `wide` draws the desktop panel.
 *
 * `config.typeable` is a runtime branch here where the view had a Blade `@if`:
 * an Alpine.data factory is compiled once for every instance, so anything that
 * used to vary the *shape* of the object has to vary its behaviour instead.
 */
const wireDateTimePicker = (config = {}) => mix(typing(), calendar(), clockDial(), {

    open: false,
    value: config.state,

    hasDate: config.hasDate ?? true,
    hasTime: config.hasTime ?? false,
    hasSeconds: config.hasSeconds ?? false,
    firstDayOfWeek: config.firstDayOfWeek ?? 1,
    disabledDates: config.disabledDates ?? [],
    minDay: config.minDay ?? null,
    maxDay: config.maxDay ?? null,
    // The time a min/max bound carries, binding on its own day only…
    minTime: config.minTime ?? null,
    maxTime: config.maxTime ?? null,
    // …and the daily window (minTime()/maxTime()), binding on every day.
    windowMin: config.windowMin ?? null,
    windowMax: config.windowMax ?? null,
    hoursStep: config.hoursStep ?? 1,
    minutesStep: config.minutesStep ?? 1,
    secondsStep: config.secondsStep ?? 1,
    displayFormat: config.displayFormat ?? null,
    typedFormat: config.typedFormat ?? null,
    typeable: config.typeable ?? false,
    closeOnDateSelection: config.closeOnDateSelection ?? false,
    // Floating UI options the view used to interpolate into the $float() call.
    sheetOnMobile: config.sheetOnMobile ?? false,
    sheetBreakpoint: config.sheetBreakpoint ?? null,
    // The width (max-width px) below which the panel is the phone's.
    mobileBreakpoint: config.mobileBreakpoint ?? 639.98,

    // From the breakpoint up: the desktop panel.
    wide: true,

    currentMonth: null,
    currentYear: null,
    hours: 0,
    minutes: 0,
    seconds: 0,

    _float: null,

    init() {
        const query = window.matchMedia('(min-width: ' + (this.mobileBreakpoint + 0.02) + 'px)')
        this.wide = query.matches
        query.addEventListener?.('change', (e) => { this.wide = e.matches })

        // Teleport + Floating UI: pin the calendar panel to the input while open
        // so table/modal overflow can never clip it.
        this.$watch('open', (open) => {
            if (open) {
                this.view = 'days'
                this.dialUnit = 'hours'
                this.$nextTick(() => {
                    const options = { placement: 'bottom-start', offset: 4 }

                    if (this.sheetOnMobile) {
                        options.sheetOnMobile = true
                        options.sheetBreakpoint = this.sheetBreakpoint
                    }

                    this._float = this.$float(this.$refs.trigger, this.$refs.panel, options)
                })
            } else if (this._float) {
                this._float()
                this._float = null
            }
        })

        if (this.value) {
            const parsed = this.parseValue(this.value)
            this.currentMonth = parsed.getMonth()
            this.currentYear = parsed.getFullYear()
            this.hours = parsed.getHours()
            this.minutes = parsed.getMinutes()
            this.seconds = parsed.getSeconds()
        } else {
            // Open on today, or on the nearest month the bounds allow.
            this.showMonthOf(this.clampDay(this.todayStr()))
        }
        this.buildDayNames()
    },

    parseValue(val) {
        if (! val) return new Date()
        // Handle YYYY-MM-DD, YYYY-MM-DDTHH:mm, HH:mm formats
        if (/^\d{2}:\d{2}/.test(val)) {
            const parts = val.split(':')
            const d = new Date()
            d.setHours(parseInt(parts[0]), parseInt(parts[1]), parts[2] ? parseInt(parts[2]) : 0)
            return d
        }
        return new Date(val.replace(' ', 'T'))
    },

    // The day the value is on, or null.
    valueDay() {
        return this.hasDate && this.value ? this.value.split(/[T ]/)[0] : null
    },

    // Keyboard focus enters the grid on the picked day.
    focusAnchor() {
        return this.valueDay()
    },

    /**
     * Open from the keyboard: the panel, with focus on the grid (or the clock),
     * so the arrows mean days at once. A click on the box opens without taking
     * focus out of it — the caret is there to type.
     */
    openWithKeyboard() {
        this.open = true
        if (! this.wide) return
        if (this.hasDate) {
            this.$nextTick(() => this.focusGrid())
        } else {
            this.$nextTick(() => this.$refs.dial?.focus())
        }
    },

    // Escape out of the panel hands focus back to the box it came from.
    closeToTrigger() {
        this.open = false
        this.$refs.trigger?.querySelector('input')?.focus()
    },

    isSelected(dateStr) {
        if (! this.value || ! dateStr) return false
        return this.value.startsWith(dateStr)
    },

    selectDate(dateStr) {
        if (this.isDisabled(dateStr)) return
        this.focusDay = dateStr
        this.commitValue(dateStr)
        // A date-only picker has nothing left to ask, so it always closes. With a
        // time part the panel stays open to pick it — unless the owner opted out
        // via closeOnDateSelection().
        if (! this.hasTime || this.closeOnDateSelection) {
            this.open = false
        }
    },

    // The value's own time, in the shape the state is written in.
    timeValue() {
        return String(this.hours).padStart(2, '0') + ':' + String(this.minutes).padStart(2, '0')
            + (this.hasSeconds ? ':' + String(this.seconds).padStart(2, '0') : '')
    },

    /**
     * The clock's bounds on `day`. A bound's own time only binds on its own day
     * — 08:30 as a minimum says nothing about the days after it — while the
     * daily window binds on all of them; where both apply the stricter wins. A
     * time-only picker is always on its own day.
     */
    timeBoundsFor(_end = null, day = undefined) {
        if (day === undefined) day = this.valueDay()
        const onMin = ! this.hasDate || (!! this.minDay && day === this.minDay)
        const onMax = ! this.hasDate || (!! this.maxDay && day === this.maxDay)
        const lowers = [this.windowMin, onMin ? this.minTime : null].filter(Boolean)
        const uppers = [this.windowMax, onMax ? this.maxTime : null].filter(Boolean)

        return {
            lower: lowers.length ? lowers.sort().at(-1) : null,
            upper: uppers.length ? uppers.sort()[0] : null,
        }
    },

    // Pull the clock back inside the bounds before it reaches the value.
    clampTime(day) {
        if (! this.hasTime) return

        const { lower, upper } = this.timeBoundsFor(null, day)
        const current = String(this.hours).padStart(2, '0') + ':' + String(this.minutes).padStart(2, '0') + ':' + String(this.seconds).padStart(2, '0')

        let target = null
        if (lower && current < lower) target = lower
        else if (upper && current > upper) target = upper
        if (! target) return

        const [h, m, s] = target.split(':')
        this.hours = parseInt(h, 10)
        this.minutes = parseInt(m, 10)
        this.seconds = this.hasSeconds ? parseInt(s, 10) : 0
    },

    commitValue(dateStr = null) {
        if (this.hasDate && this.hasTime) {
            const d = dateStr || this.valueDay() || this.formatDateStr(this.currentYear, this.currentMonth + 1, 1)
            this.clampTime(d)
            this.value = d + ' ' + this.timeValue()
        } else if (this.hasDate) {
            this.value = dateStr
        } else {
            this.clampTime(null)
            this.value = this.timeValue()
        }
    },

    adjustHours(dir) {
        this.hours = ((this.hours + dir * this.hoursStep) % 24 + 24) % 24
        this.commitValue()
    },
    adjustMinutes(dir) {
        this.minutes = ((this.minutes + dir * this.minutesStep) % 60 + 60) % 60
        this.commitValue()
    },
    adjustSeconds(dir) {
        this.seconds = ((this.seconds + dir * this.secondsStep) % 60 + 60) % 60
        this.commitValue()
    },

    // ─── The dial (clock-dial.js) ──────────────────────────────────

    // The dial's draft is the picker's own clock: what it sets, it commits.
    get draft() {
        return { hours: this.hours, minutes: this.minutes, seconds: this.seconds }
    },

    set draft(time) {
        this.hours = time.hours
        this.minutes = time.minutes
        this.seconds = this.hasSeconds ? time.seconds : 0
        this.commitValue()
    },

    // Enter on the last part of the dial: the clock is already committed.
    confirmDial() {
        this.closeToTrigger()
    },

    timeOf() {
        return { hours: this.hours, minutes: this.minutes, seconds: this.seconds }
    },

    setTimePart(_end, unit, v) {
        this[unit] = v
        this.commitValue()
    },

    /**
     * "Today" on a calendar, "Now" on a clock: the current moment, rounded
     * down to the steps and pulled inside the bounds. Refused on a day the
     * rules exclude rather than landing somewhere else unasked.
     */
    pickNow() {
        const now = new Date()
        const today = this.todayStr()
        if (this.hasDate && this.isDisabled(today)) return

        if (this.hasTime) {
            const floor = (v, step) => v - (v % Math.max(1, step || 1))
            this.hours = floor(now.getHours(), this.hoursStep)
            this.minutes = floor(now.getMinutes(), this.minutesStep)
            this.seconds = this.hasSeconds ? floor(now.getSeconds(), this.secondsStep) : 0
        }

        if (this.hasDate) {
            this.showMonthOf(today)
            this.view = 'days'
            this.selectDate(today)
        } else {
            this.commitValue()
        }
    },

    get canPickNow() {
        return ! this.hasDate || ! this.isDisabled(this.todayStr())
    },

    get displayValue() {
        return formatState(this.value, this.displayFormat)
    },

    /**
     * What a parsed set of numbers means to a calendar-and-clock picker.
     *
     * Everything is checked before anything is written, so a refused entry leaves
     * the picker exactly as the user found it. Refuses outright where the field
     * is not typeable, which the view used to express by not emitting this
     * method at all.
     */
    applyTyped(parts) {
        if (! this.typeable) return false

        let hours = this.hours, minutes = this.minutes, seconds = this.seconds

        if (this.hasTime) {
            // A format with no clock in it, or a date typed without one, keeps
            // the time already showing.
            hours = parts.hours ?? hours
            minutes = parts.minutes ?? minutes
            seconds = this.hasSeconds ? (parts.seconds ?? seconds) : 0
            if (hours > 23 || minutes > 59 || seconds > 59) return false
        }

        let dateStr = null

        if (this.hasDate) {
            const { year, month, day } = parts
            if (! year || ! month || ! day || month > 12 || day < 1) return false
            // 31 February is a typo, and new Date() would quietly roll it forward
            // into March rather than say so.
            if (day > new Date(year, month, 0).getDate()) return false

            dateStr = this.formatDateStr(year, month, day)
            // The same gate the calendar cells go through: a day the bounds or
            // disabledDates exclude cannot be typed in either.
            if (this.isDisabled(dateStr)) return false
        }

        this.hours = hours
        this.minutes = minutes
        this.seconds = seconds

        if (dateStr) {
            // Walk the calendar to what was typed, so opening the panel after
            // typing shows the month the value is in.
            this.currentYear = parts.year
            this.currentMonth = parts.month - 1
        }

        // commitValue() clamps the clock into the bounds of the day it lands on,
        // exactly as it does for a picked date.
        this.commitValue(dateStr)

        return true
    },

    clear() {
        this.value = null
    },
})

export default wireDateTimePicker
