/**
 * The mobile wheel picker (`->touchOnMobile()`): columns in a bottom sheet
 * that scroll, coast and snap like the phone's own picker — hours and minutes
 * for a time, day / month / year for a date, and a day column beside hours and
 * minutes for a datetime (the iOS shape).
 *
 * The feel is the browser's, not ours. Each column is a plain scroller with
 * `scroll-snap-type: y mandatory`, so momentum, rubber-banding and the snap are
 * the platform's own physics on iOS and Android alike. This controller builds
 * the columns, reads where each came to rest, tilts the rows for the drum look,
 * and keeps the combination on a value the field accepts.
 *
 * What is acceptable is the field's own rule: the slots (interval and bounds)
 * for a clock, `min` / `max` and the disabled days for a date. When the wheels
 * rest on a combination outside it (31 February, 18:30 after an 18:00 close, a
 * disabled day), the column the user did not just turn gives way to the
 * nearest value that makes it valid — the way iOS rolls back past a min/max.
 *
 * Nothing is written while the wheels turn: "Done" commits; "Cancel", the
 * backdrop and a swipe down leave the value. The sheet is a dialog, so a
 * scroll-through can never send a value nobody chose.
 */
const ROW = 44 // px — Apple's minimum touch target, and the snap pitch
const pad = (n) => String(n).padStart(2, '0')
const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`

const wireWheelPicker = (config = {}) => ({
    open: false,
    state: config.state,
    kind: config.kind ?? 'time', // 'time' | 'date' | 'datetime'
    hasSeconds: config.hasSeconds ?? false,
    // Each column: { key, label, width (rem — widths live here, not in classes a
    // stylesheet scan of the views would miss), items: [{ value, label }] }.
    columns: [],
    selected: {},
    _settle: {},

    init() {
        const locale = document.documentElement.lang || undefined
        this._locale = locale
        this._min = config.min ?? null
        this._max = config.max ?? null
        this._disabled = new Set(config.disabledDates ?? [])
        this._slots = new Set((config.slots ?? this.daySlots(config.interval ?? 1)).map((s) => String(s).slice(0, 5)))
        this.columns = this.buildColumns(locale)
    },

    // ─── Columns ─────────────────────────────────────────────────────

    daySlots(interval) {
        const out = []
        for (let m = 0; m < 24 * 60; m += Math.max(1, interval)) out.push(`${pad(Math.floor(m / 60))}:${pad(m % 60)}`)
        return out
    },

    clockColumns() {
        const slots = [...this._slots]
        const hours = [...new Set(slots.map((t) => t.slice(0, 2)))]
        const minutes = [...new Set(slots.map((t) => t.slice(3, 5)))].sort()
        return [
            { key: 'hour', label: config.labels?.hours, width: 4, items: hours.map((v) => ({ value: v, label: v })) },
            { key: 'minute', label: config.labels?.minutes, width: 4, items: minutes.map((v) => ({ value: v, label: v })) },
        ]
    },

    buildColumns(locale) {
        if (this.kind === 'time') return this.clockColumns()

        if (this.kind === 'date') {
            const month = new Intl.DateTimeFormat(locale, { month: 'long' })
            const [from, to] = this.yearRange()
            const years = []
            for (let y = from; y <= to; y++) years.push({ value: String(y), label: String(y) })
            return [
                { key: 'day', label: config.labels?.day, width: 3.5, items: Array.from({ length: 31 }, (_, i) => ({ value: pad(i + 1), label: String(i + 1) })) },
                { key: 'month', label: config.labels?.month, width: 8, items: Array.from({ length: 12 }, (_, i) => ({ value: pad(i + 1), label: month.format(new Date(2000, i, 1)) })) },
                { key: 'year', label: config.labels?.year, width: 5, items: years },
            ]
        }

        // datetime: one column of days (bounded by min/max, else a year each way),
        // then the clock.
        const weekday = new Intl.DateTimeFormat(locale, { weekday: 'short', day: 'numeric', month: 'numeric' })
        const today = ymd(new Date())
        const [from, to] = this.dayRange()
        const days = []
        for (let d = from; d <= to; d = new Date(d.getFullYear(), d.getMonth(), d.getDate() + 1)) {
            const value = ymd(d)
            days.push({ value, label: value === today ? (config.labels?.today ?? weekday.format(d)) : weekday.format(d) })
        }
        return [{ key: 'date', label: config.labels?.day, width: 8.5, items: days }, ...this.clockColumns()]
    },

    yearRange() {
        const now = new Date().getFullYear()
        return [
            this._min ? Number(this._min.slice(0, 4)) : now - 100,
            this._max ? Number(this._max.slice(0, 4)) : now + 50,
        ]
    },

    dayRange() {
        const now = new Date()
        const at = (s) => new Date(Number(s.slice(0, 4)), Number(s.slice(5, 7)) - 1, Number(s.slice(8, 10)))
        return [
            this._min ? at(this._min) : new Date(now.getFullYear() - 1, now.getMonth(), now.getDate()),
            this._max ? at(this._max) : new Date(now.getFullYear() + 1, now.getMonth(), now.getDate()),
        ]
    },

    // ─── Value ↔ wheels ──────────────────────────────────────────────

    compose(sel = this.selected) {
        const clock = `${sel.hour}:${sel.minute}${this.hasSeconds ? ':00' : ''}`
        if (this.kind === 'time') return clock
        if (this.kind === 'date') return `${sel.year}-${sel.month}-${sel.day}`
        return `${sel.date}T${clock}`
    },

    decompose(value) {
        const v = String(value)
        if (this.kind === 'time') return { hour: v.slice(0, 2), minute: v.slice(3, 5) }
        if (this.kind === 'date') return { year: v.slice(0, 4), month: v.slice(5, 7), day: v.slice(8, 10) }
        return { date: v.slice(0, 10), hour: v.slice(11, 13), minute: v.slice(14, 16) }
    },

    // The field's own rule, asked of a candidate combination.
    isValid(sel) {
        if (this.kind !== 'date' && ! this._slots.has(`${sel.hour}:${sel.minute}`)) return false
        if (this.kind === 'time') return true

        const day = this.kind === 'date' ? `${sel.year}-${sel.month}-${sel.day}` : sel.date
        // A real day: 31 February rolls over in Date, and then it is not the same day.
        const probe = new Date(Number(day.slice(0, 4)), Number(day.slice(5, 7)) - 1, Number(day.slice(8, 10)))
        if (ymd(probe) !== day || this._disabled.has(day)) return false

        // Bounds compare in the state's own shape, which sorts as it reads.
        const value = this.kind === 'date' ? day : `${day}T${sel.hour}:${sel.minute}`
        const width = value.length
        if (this._min && value < this._min.slice(0, width)) return false
        if (this._max && value > this._max.slice(0, width)) return false
        return true
    },

    get display() {
        if (! this.state) return ''
        const v = String(this.state)
        if (this.kind === 'time') return v.slice(0, this.hasSeconds ? 8 : 5)
        const [y, m, d] = [v.slice(0, 4), v.slice(5, 7), v.slice(8, 10)]
        const date = new Intl.DateTimeFormat(this._locale, { day: 'numeric', month: 'numeric', year: 'numeric' })
            .format(new Date(Number(y), Number(m) - 1, Number(d)))
        return this.kind === 'date' ? date : `${date} ${v.slice(11, 16)}`
    },

    // ─── Sheet ───────────────────────────────────────────────────────

    openSheet() {
        this.selected = this.startingSelection()
        this.open = true
        // Scroll positions mean nothing while the sheet is display:none; place
        // the columns once it has laid out, without animating there.
        this.$nextTick(() => requestAnimationFrame(() => {
            for (const column of this.columns) {
                this.scrollTo(column.key, this.indexOf(column.key, this.selected[column.key]), 'instant')
                this.paint(column.key)
            }
            // Focus the first wheel, not the first button: a screen reader lands
            // on the control, and a finger sees no focus ring on "Cancel".
            this.wheel(this.columns[0].key)?.focus({ preventScroll: true })
        }))
    },

    // The current value; or now — clamped onto something valid — as the phone
    // itself opens an empty picker on the present.
    startingSelection() {
        if (this.state) {
            const sel = this.decompose(this.state)
            if (this.isValid(sel)) return sel
        }
        const now = new Date()
        const clock = `${pad(now.getHours())}:${pad(now.getMinutes())}`
        const slot = [...this._slots].find((t) => t >= clock) ?? [...this._slots][0] ?? '00:00'
        const sel = this.decompose(this.kind === 'time' ? slot : this.kind === 'date' ? ymd(now) : `${ymd(now)}T${slot}`)
        // Now may sit outside the bounds (a booking that opens next month):
        // walk onto the nearest valid value, a column at a time.
        for (let pass = 0; pass < this.columns.length && ! this.isValid(sel); pass++) {
            this.makeValid(sel, null)
        }
        return sel
    },

    done() {
        if (this.isValid(this.selected)) this.state = this.compose()
        this.open = false
    },

    cancel() {
        this.open = false
    },

    clear() {
        this.state = null
        this.open = false
    },

    // ─── Turning ─────────────────────────────────────────────────────

    // Not `column()`: the view's x-for names its loop variable `column`, and
    // Alpine merges loop scope into `this`, so that name would shadow a method.
    columnOf(key) {
        return this.columns.find((c) => c.key === key)
    },

    indexOf(key, value) {
        return this.columnOf(key).items.findIndex((item) => item.value === value)
    },

    // The columns are rendered by x-for inside the teleported sheet, where a
    // ref cannot be dynamic; the sheet is the ref, the column its data-wheel.
    wheel(key) {
        return this.$refs.sheet?.querySelector(`[data-wheel="${key}"]`) ?? null
    },

    scrollTo(key, index, behavior = 'smooth') {
        const el = this.wheel(key)
        if (! el || index < 0) return
        el.scrollTo({ top: index * ROW, behavior })
    },

    onScroll(key) {
        requestAnimationFrame(() => this.paint(key))
        // `scrollend` settles modern browsers; the timer covers the rest.
        clearTimeout(this._settle[key])
        this._settle[key] = setTimeout(() => this.settle(key), 120)
    },

    // Tilt only the rows near the band: a datetime's day column runs to
    // hundreds of rows, and the ones far off-screen are masked out anyway.
    paint(key) {
        const el = this.wheel(key)
        if (! el) return
        const centre = el.scrollTop / ROW
        const rows = el.querySelectorAll('[data-wheel-row]')
        const from = Math.max(0, Math.floor(centre) - 5)
        const to = Math.min(rows.length - 1, Math.ceil(centre) + 5)
        for (let i = from; i <= to; i++) {
            const d = Math.max(-3, Math.min(3, i - centre))
            rows[i].style.transform = `rotateX(${-d * 20}deg) scale(${1 - Math.abs(d) * 0.06})`
            rows[i].style.opacity = String(1 - Math.abs(d) * 0.28)
        }
    },

    settle(key) {
        const el = this.wheel(key)
        if (! el || ! this.open) return
        const items = this.columnOf(key).items
        const index = Math.max(0, Math.min(items.length - 1, Math.round(el.scrollTop / ROW)))
        const value = items[index].value

        if (this.selected[key] !== value) {
            this.selected = { ...this.selected, [key]: value }
            // A tick under the thumb where the platform allows it (Android);
            // iOS exposes no vibration to the web, and that is a no-op there.
            navigator.vibrate?.(4)
        }

        if (! this.isValid(this.selected)) {
            const sel = { ...this.selected }
            if (this.makeValid(sel, key)) {
                for (const column of this.columns) {
                    if (sel[column.key] !== this.selected[column.key]) {
                        this.scrollTo(column.key, this.indexOf(column.key, sel[column.key]))
                    }
                }
                this.selected = sel
            }
        }
    },

    // Past a bound, the wheels jump to the bound itself — the way iOS lands on
    // its minimumDate rather than on whatever happens to be valid nearby.
    clampToBounds(sel) {
        if (this.kind === 'time') return
        const value = this.compose(sel).slice(0, this.kind === 'date' ? 10 : 16)
        const slots = [...this._slots].sort()

        if (this._min && value < this._min.slice(0, value.length)) {
            if (this.kind === 'date') return Object.assign(sel, this.decompose(this._min))
            const time = this._min.slice(11, 16)
            const slot = slots.find((t) => t >= time)
            Object.assign(sel, slot ? { date: this._min.slice(0, 10), hour: slot.slice(0, 2), minute: slot.slice(3, 5) } : { date: this._min.slice(0, 10) })
        } else if (this._max && value > this._max.slice(0, value.length)) {
            if (this.kind === 'date') return Object.assign(sel, this.decompose(this._max))
            const time = this._max.slice(11, 16) || '23:59'
            const slot = [...slots].reverse().find((t) => t <= time)
            Object.assign(sel, slot ? { date: this._max.slice(0, 10), hour: slot.slice(0, 2), minute: slot.slice(3, 5) } : { date: this._max.slice(0, 10) })
        }
    },

    // The order columns give way in, finest first: a minute before an hour, a
    // day before a month before a year.
    yieldOrder() {
        return { time: ['minute', 'hour'], date: ['day', 'month', 'year'], datetime: ['minute', 'hour', 'date'] }[this.kind]
    },

    // Move the columns the user did not just turn — finest first, nearest value
    // first — until the combination is valid. The turned column gives way only
    // when nothing else can.
    makeValid(sel, turned) {
        this.clampToBounds(sel)
        if (this.isValid(sel)) return true
        // A date's day always goes first, even when it was the one turned: onto a
        // disabled day, the neighbouring day is the answer, not another month.
        const order = this.kind === 'date' ? this.yieldOrder() : this.yieldOrder().filter((k) => k !== turned)
        if (turned && ! order.includes(turned)) order.push(turned)

        for (const key of order) {
            const items = this.columnOf(key).items
            const start = Math.max(0, items.findIndex((item) => item.value === sel[key]))
            for (let distance = 1; distance < items.length; distance++) {
                for (const i of [start - distance, start + distance]) {
                    if (i < 0 || i >= items.length) continue
                    const candidate = { ...sel, [key]: items[i].value }
                    if (this.isValid(candidate)) {
                        sel[key] = items[i].value
                        return true
                    }
                }
            }
        }
        return false
    },

    // A column is a spinbutton to assistive tech and the keyboard: arrows step,
    // Home/End jump — the same contract as the platform's adjustable picker.
    onKey(key, event) {
        const items = this.columnOf(key).items
        const current = this.indexOf(key, this.selected[key])
        const next = { ArrowUp: current - 1, ArrowDown: current + 1, Home: 0, End: items.length - 1 }[event.key]
        if (next === undefined) return
        event.preventDefault()
        this.scrollTo(key, Math.max(0, Math.min(items.length - 1, next)))
    },

    // Greyed like iOS greys 31 February: a row that, with the other columns as
    // they stand, would not make a value the field accepts.
    rowValid(key, value) {
        return this.isValid({ ...this.selected, [key]: value })
    },

    labelOf(key) {
        const item = this.columnOf(key)?.items[this.indexOf(key, this.selected[key])]
        return item ? item.label : ''
    },
})

export default wireWheelPicker
