import { typing, formatState } from './typing'

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
 * `config.typeable` is a runtime branch here where the view had a Blade `@if`:
 * an Alpine.data factory is compiled once for every instance, so anything that
 * used to vary the *shape* of the object has to vary its behaviour instead.
 */
const wireDateTimePicker = (config = {}) => ({
    ...typing(),

    open: false,
    value: config.state,

    hasDate: config.hasDate ?? true,
    hasTime: config.hasTime ?? false,
    hasSeconds: config.hasSeconds ?? false,
    firstDayOfWeek: config.firstDayOfWeek ?? 1,
    disabledDates: config.disabledDates ?? [],
    minDay: config.minDay ?? null,
    maxDay: config.maxDay ?? null,
    minTime: config.minTime ?? null,
    maxTime: config.maxTime ?? null,
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

    currentMonth: null,
    currentYear: null,
    hours: 0,
    minutes: 0,
    seconds: 0,

    dayNames: [],
    days: [],
    _float: null,

    init() {
        // Teleport + Floating UI: pin the calendar panel to the input while open
        // so table/modal overflow can never clip it.
        this.$watch('open', (open) => {
            if (open) {
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
            // Open on today, or on the nearest month the bounds allow — landing
            // on a month where every day is greyed out reads as a broken
            // calendar.
            const today = new Date()
            let anchor = this.formatDateStr(today.getFullYear(), today.getMonth() + 1, today.getDate())
            if (this.minDay && anchor < this.minDay) anchor = this.minDay
            if (this.maxDay && anchor > this.maxDay) anchor = this.maxDay
            const [y, m] = anchor.split('-')
            this.currentYear = parseInt(y, 10)
            this.currentMonth = parseInt(m, 10) - 1
        }
        this.buildDayNames()
        this.buildCalendar()
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

    buildDayNames() {
        const names = []
        const base = new Date(2024, 0, 1) // Monday = 2024-01-01
        const offset = this.firstDayOfWeek === 0 ? 6 : this.firstDayOfWeek - 1
        for (let i = 0; i < 7; i++) {
            const d = new Date(base)
            d.setDate(d.getDate() + i - offset)
            names.push(d.toLocaleDateString(undefined, { weekday: 'short' }).slice(0, 2))
        }
        this.dayNames = names
    },

    buildCalendar() {
        const first = new Date(this.currentYear, this.currentMonth, 1)
        let startDay = first.getDay() - this.firstDayOfWeek
        if (startDay < 0) startDay += 7

        const daysInMonth = new Date(this.currentYear, this.currentMonth + 1, 0).getDate()
        const daysInPrevMonth = new Date(this.currentYear, this.currentMonth, 0).getDate()

        const cells = []

        // Previous month padding
        for (let i = startDay - 1; i >= 0; i--) {
            cells.push({ day: daysInPrevMonth - i, current: false, date: null })
        }

        // Current month
        for (let d = 1; d <= daysInMonth; d++) {
            const dateStr = this.formatDateStr(this.currentYear, this.currentMonth + 1, d)
            cells.push({ day: d, current: true, date: dateStr })
        }

        // Next month padding
        const remaining = 42 - cells.length
        for (let d = 1; d <= remaining; d++) {
            cells.push({ day: d, current: false, date: null })
        }

        this.days = cells
    },

    formatDateStr(y, m, d) {
        return y + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0')
    },

    // Nothing selectable lies before the bounds, so the arrows stop there.
    get canGoPrev() {
        if (! this.minDay) return true
        const last = new Date(this.currentYear, this.currentMonth, 0)
        return this.formatDateStr(last.getFullYear(), last.getMonth() + 1, last.getDate()) >= this.minDay
    },

    get canGoNext() {
        if (! this.maxDay) return true
        const first = new Date(this.currentYear, this.currentMonth + 1, 1)
        return this.formatDateStr(first.getFullYear(), first.getMonth() + 1, first.getDate()) <= this.maxDay
    },

    prevMonth() {
        if (! this.canGoPrev) return
        if (this.currentMonth === 0) {
            this.currentMonth = 11
            this.currentYear--
        } else {
            this.currentMonth--
        }
        this.buildCalendar()
    },

    nextMonth() {
        if (! this.canGoNext) return
        if (this.currentMonth === 11) {
            this.currentMonth = 0
            this.currentYear++
        } else {
            this.currentMonth++
        }
        this.buildCalendar()
    },

    isDisabled(dateStr) {
        if (! dateStr) return true
        if (this.disabledDates.includes(dateStr)) return true
        if (this.minDay && dateStr < this.minDay) return true
        if (this.maxDay && dateStr > this.maxDay) return true
        return false
    },

    isSelected(dateStr) {
        if (! this.value || ! dateStr) return false
        return this.value.startsWith(dateStr)
    },

    isToday(dateStr) {
        if (! dateStr) return false
        const today = new Date()
        return dateStr === this.formatDateStr(today.getFullYear(), today.getMonth() + 1, today.getDate())
    },

    selectDate(dateStr) {
        if (this.isDisabled(dateStr)) return
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
     * Pull the clock back inside the bounds before it reaches the value.
     *
     * A bound's time only binds on its own day — 08:30 as a minimum says nothing
     * about the days after it — so a datetime picker checks the day first, while
     * a time-only one is always on its own day.
     */
    clampTime(day) {
        if (! this.hasTime) return

        const lower = (! this.hasDate || (this.minDay && day === this.minDay)) ? this.minTime : null
        const upper = (! this.hasDate || (this.maxDay && day === this.maxDay)) ? this.maxTime : null
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
            const d = dateStr || (this.value ? this.value.split(/[T ]/)[0] : this.formatDateStr(this.currentYear, this.currentMonth + 1, 1))
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
            this.buildCalendar()
        }

        // commitValue() clamps the clock into the bounds of the day it lands on,
        // exactly as it does for a picked date.
        this.commitValue(dateStr)

        return true
    },

    get monthYearLabel() {
        const d = new Date(this.currentYear, this.currentMonth)
        return d.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
    },

    clear() {
        this.value = null
    },
})

export default wireDateTimePicker
