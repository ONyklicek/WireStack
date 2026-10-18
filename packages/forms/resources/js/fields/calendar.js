/**
 * The month calendar, shared by DateTimePicker and DateRangePicker.
 *
 * Both draw the same grid over the same rules — bounds, disabled dates, the
 * first day of the week — and differ only in what a picked day *means* (one
 * value, or one end of a period). A second calendar written for ranges is the
 * copy that drifts by one bound check, so the grid, the month and year views
 * and the keyboard live here once.
 *
 * Days travel as `Y-m-d` strings and are compared as strings. Date objects are
 * built only to walk the calendar, from local parts, so the browser's timezone
 * never moves a day.
 *
 * Spread into a controller's returned object. The host must provide:
 *   - currentYear, currentMonth   the first month on screen (month is 0–11)
 *   - minDay, maxDay              `Y-m-d` bounds or null
 *   - disabledDates               `Y-m-d` strings no one may pick
 *   - firstDayOfWeek              0 (Sunday) – 6
 *   - focusAnchor()               the day keyboard focus lands on when the grid
 *                                 is entered — the picked one, as a rule
 *
 * `monthsShown` is how many months sit side by side (a range shows two); the
 * keyboard walks across all of them before it turns the page.
 */
export const calendar = () => ({
    // 'days', 'months' or 'years' — the Fiori header: the month name opens a
    // grid of months, the year a grid of years, so a birth date is three
    // clicks rather than four hundred.
    view: 'days',
    monthsShown: 1,
    focusDay: null,
    focusMonth: null,
    focusYear: null,
    yearPageStart: null,

    dayNames: [],

    pad(n) {
        return String(n).padStart(2, '0')
    },

    formatDateStr(y, m, d) {
        return y + '-' + this.pad(m) + '-' + this.pad(d)
    },

    // A `Y-m-d` string `n` days on. Local parts in, local parts out.
    addDays(dateStr, n) {
        const [y, m, d] = dateStr.split('-').map((v) => parseInt(v, 10))
        const date = new Date(y, m - 1, d + n)
        return this.formatDateStr(date.getFullYear(), date.getMonth() + 1, date.getDate())
    },

    addMonths(dateStr, n) {
        const [y, m, d] = dateStr.split('-').map((v) => parseInt(v, 10))
        const first = new Date(y, m - 1 + n, 1)
        // 31 January plus a month is the last of February, not 3 March.
        const last = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate()
        return this.formatDateStr(first.getFullYear(), first.getMonth() + 1, Math.min(d, last))
    },

    todayStr() {
        const t = new Date()
        return this.formatDateStr(t.getFullYear(), t.getMonth() + 1, t.getDate())
    },

    // Today, pulled inside the bounds: a calendar opening on a month where every
    // day is greyed out reads as a broken one.
    clampDay(dateStr) {
        if (this.minDay && dateStr < this.minDay) return this.minDay
        if (this.maxDay && dateStr > this.maxDay) return this.maxDay
        return dateStr
    },

    showMonthOf(dateStr) {
        const [y, m] = dateStr.split('-')
        this.currentYear = parseInt(y, 10)
        this.currentMonth = parseInt(m, 10) - 1
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

    // The month `offset` months after the first one on screen.
    monthAt(offset = 0) {
        const d = new Date(this.currentYear, this.currentMonth + offset, 1)
        return { year: d.getFullYear(), month: d.getMonth() }
    },

    /**
     * Six weeks of cells for one month. Days of the neighbouring months pad the
     * grid but carry no date: they belong to the month beside this one, and a
     * day that answered in two grids would be picked twice.
     */
    monthCells(offset = 0) {
        const { year, month } = this.monthAt(offset)
        const first = new Date(year, month, 1)
        let startDay = first.getDay() - this.firstDayOfWeek
        if (startDay < 0) startDay += 7

        const daysInMonth = new Date(year, month + 1, 0).getDate()
        const daysInPrevMonth = new Date(year, month, 0).getDate()

        const cells = []
        for (let i = startDay - 1; i >= 0; i--) {
            cells.push({ day: daysInPrevMonth - i, current: false, date: null })
        }
        for (let d = 1; d <= daysInMonth; d++) {
            cells.push({ day: d, current: true, date: this.formatDateStr(year, month + 1, d) })
        }
        const remaining = 42 - cells.length
        for (let d = 1; d <= remaining; d++) {
            cells.push({ day: d, current: false, date: null })
        }

        return cells
    },

    get days() {
        return this.monthCells(0)
    },

    isDisabled(dateStr) {
        if (! dateStr) return true
        if (this.disabledDates.includes(dateStr)) return true
        if (this.minDay && dateStr < this.minDay) return true
        if (this.maxDay && dateStr > this.maxDay) return true
        return false
    },

    isToday(dateStr) {
        return !! dateStr && dateStr === this.todayStr()
    },

    // ─── Labels ────────────────────────────────────────────────────

    monthName(month, style = 'long') {
        return new Date(2024, month, 1).toLocaleDateString(undefined, { month: style })
    },

    monthLabel(offset = 0) {
        const { year, month } = this.monthAt(offset)
        return new Date(year, month).toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
    },

    get monthYearLabel() {
        return this.monthLabel(0)
    },

    get yearPage() {
        const start = this.yearPageStart ?? this.currentYear - 10
        return Array.from({ length: 20 }, (_, i) => start + i)
    },

    get yearPageLabel() {
        const page = this.yearPage
        return page[0] + ' – ' + page[page.length - 1]
    },

    // ─── Paging ────────────────────────────────────────────────────

    // Nothing selectable lies before the bounds, so the arrows stop there.
    get canGoPrev() {
        if (! this.minDay) return true
        const last = new Date(this.currentYear, this.currentMonth, 0)
        return this.formatDateStr(last.getFullYear(), last.getMonth() + 1, last.getDate()) >= this.minDay
    },

    get canGoNext() {
        if (! this.maxDay) return true
        const { year, month } = this.monthAt(this.monthsShown)
        return this.formatDateStr(year, month + 1, 1) <= this.maxDay
    },

    shiftMonths(n) {
        const d = new Date(this.currentYear, this.currentMonth + n, 1)
        this.currentYear = d.getFullYear()
        this.currentMonth = d.getMonth()
    },

    prevMonth() {
        if (this.canGoPrev) this.shiftMonths(-1)
    },

    nextMonth() {
        if (this.canGoNext) this.shiftMonths(1)
    },

    monthDisabled(year, month) {
        const first = this.formatDateStr(year, month + 1, 1)
        const last = this.formatDateStr(year, month + 1, new Date(year, month + 1, 0).getDate())
        return (!! this.minDay && last < this.minDay) || (!! this.maxDay && first > this.maxDay)
    },

    yearDisabled(year) {
        return (!! this.minDay && year + '-12-31' < this.minDay) || (!! this.maxDay && year + '-01-01' > this.maxDay)
    },

    // The header arrows mean a month, a year or a page of years, by view.
    get canNavPrev() {
        if (this.view === 'months') return ! this.yearDisabled(this.currentYear - 1)
        if (this.view === 'years') return ! this.yearDisabled(this.yearPage[0] - 1)
        return this.canGoPrev
    },

    get canNavNext() {
        if (this.view === 'months') return ! this.yearDisabled(this.currentYear + 1)
        if (this.view === 'years') return ! this.yearDisabled(this.yearPage[19] + 1)
        return this.canGoNext
    },

    navPrev() {
        if (! this.canNavPrev) return
        if (this.view === 'months') this.currentYear--
        else if (this.view === 'years') this.yearPageStart = this.yearPage[0] - 20
        else this.shiftMonths(-1)
    },

    navNext() {
        if (! this.canNavNext) return
        if (this.view === 'months') this.currentYear++
        else if (this.view === 'years') this.yearPageStart = this.yearPage[0] + 20
        else this.shiftMonths(1)
    },

    // ─── Month and year views ──────────────────────────────────────

    // Where the picked value is, for the month and year grids to mark.
    isAnchorMonth(year, month) {
        const anchor = this.focusAnchor()
        return !! anchor && anchor.startsWith(year + '-' + this.pad(month + 1))
    },

    isAnchorYear(year) {
        const anchor = this.focusAnchor()
        return !! anchor && anchor.startsWith(year + '-')
    },

    // What the month or the year grid draws, one shape for both.
    get pickerItems() {
        if (this.view === 'months') {
            return Array.from({ length: 12 }, (_, m) => ({
                value: m,
                label: this.monthName(m, 'short'),
                testId: 'month-' + (m + 1),
                disabled: this.monthDisabled(this.currentYear, m),
                focused: this.focusMonth === m,
                anchor: this.isAnchorMonth(this.currentYear, m),
            }))
        }

        return this.yearPage.map((y) => ({
            value: y,
            label: String(y),
            testId: 'year-' + y,
            disabled: this.yearDisabled(y),
            focused: this.focusYear === y,
            anchor: this.isAnchorYear(y),
        }))
    },

    pickItem(value) {
        this.view === 'months' ? this.pickMonth(value) : this.pickYear(value)
    },

    showMonths(offset = 0) {
        // A range opened on its right-hand month keeps that month in view.
        if (offset) this.shiftMonths(offset)
        this.view = 'months'
        this.focusMonth = this.currentMonth
        this.focusIn('[data-month="' + this.focusMonth + '"]')
    },

    showYears() {
        this.view = 'years'
        this.yearPageStart = this.currentYear - 10
        this.focusYear = this.currentYear
        this.focusIn('[data-year="' + this.focusYear + '"]')
    },

    pickMonth(month) {
        if (this.monthDisabled(this.currentYear, month)) return
        this.currentMonth = month
        this.view = 'days'
        this.focusIn(null)
    },

    pickYear(year) {
        if (this.yearDisabled(year)) return
        this.currentYear = year
        // A year is half an answer; the month it is wanted in comes next.
        this.view = 'months'
        this.focusMonth = this.currentMonth
        this.focusIn('[data-month="' + this.focusMonth + '"]')
    },

    // ─── Keyboard ──────────────────────────────────────────────────

    /**
     * Put focus on an element of the open panel once Alpine has drawn it. Null
     * means the day grid: the focused day if there is one, else the anchor.
     */
    focusIn(selector) {
        this.$nextTick(() => {
            const panel = this.$refs.panel
            if (! panel) return
            const target = panel.querySelector(selector ?? '[data-date="' + this.focusDay + '"]')
            if (target && ! target.disabled) target.focus()
        })
    },

    // Enter the grid: on the anchor day, with its month on screen.
    focusGrid() {
        this.view = 'days'
        this.focusDay = this.clampDay(this.focusAnchor() || this.todayStr())
        this.revealDay(this.focusDay)
        this.focusIn(null)
    },

    // Turn the page only when the day is not already on one of the months shown.
    revealDay(dateStr) {
        const first = this.formatDateStr(this.currentYear, this.currentMonth + 1, 1)
        const { year, month } = this.monthAt(this.monthsShown)
        const after = this.formatDateStr(year, month + 1, 1)
        if (dateStr < first) {
            this.showMonthOf(dateStr)
        } else if (dateStr >= after) {
            this.showMonthOf(dateStr)
            this.shiftMonths(1 - this.monthsShown)
        }
    },

    /**
     * Arrows walk days and weeks, PageUp/PageDown months (with Shift, years),
     * Home/End the week — the grid keys every desktop calendar shares. A day the
     * rules exclude is stepped over in the same direction rather than landed on:
     * a disabled button cannot hold focus, and focus that vanished would leave
     * the keyboard user nowhere.
     */
    onGridKey(event) {
        if (! this.focusDay) return

        const weekday = (dateStr) => {
            const [y, m, d] = dateStr.split('-').map((v) => parseInt(v, 10))
            return (new Date(y, m - 1, d).getDay() - this.firstDayOfWeek + 7) % 7
        }

        let target = null
        let step = 1
        switch (event.key) {
            case 'ArrowLeft': target = this.addDays(this.focusDay, -1); step = -1; break
            case 'ArrowRight': target = this.addDays(this.focusDay, 1); break
            case 'ArrowUp': target = this.addDays(this.focusDay, -7); step = -1; break
            case 'ArrowDown': target = this.addDays(this.focusDay, 7); break
            case 'Home': target = this.addDays(this.focusDay, -weekday(this.focusDay)); step = -1; break
            case 'End': target = this.addDays(this.focusDay, 6 - weekday(this.focusDay)); break
            case 'PageUp': target = this.addMonths(this.focusDay, event.shiftKey ? -12 : -1); step = -1; break
            case 'PageDown': target = this.addMonths(this.focusDay, event.shiftKey ? 12 : 1); break
            default: return
        }
        event.preventDefault()

        target = this.clampDay(target)
        for (let i = 0; i < 366 && this.isDisabled(target); i++) {
            const next = this.addDays(target, step)
            if (this.clampDay(next) !== next) return
            target = next
        }
        if (this.isDisabled(target)) return

        this.focusDay = target
        this.revealDay(target)
        this.focusIn(null)
        this.onDayFocused?.(target)
    },

    // Months sit four to a row, years four to a row; arrows walk the grid.
    onListKey(event, kind) {
        const deltas = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -4, ArrowDown: 4 }
        if (! (event.key in deltas)) return
        event.preventDefault()

        if (kind === 'months') {
            const next = this.focusMonth + deltas[event.key]
            if (next < 0 || next > 11 || this.monthDisabled(this.currentYear, next)) return
            this.focusMonth = next
            this.focusIn('[data-month="' + next + '"]')
        } else {
            const next = this.focusYear + deltas[event.key]
            if (this.yearDisabled(next)) return
            if (next < this.yearPage[0] || next > this.yearPage[19]) this.yearPageStart = this.yearPage[0] + (next < this.yearPage[0] ? -20 : 20)
            this.focusYear = next
            this.focusIn('[data-year="' + next + '"]')
        }
    },
})
