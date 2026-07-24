import { createAutoScroller } from './autoscroll'
import { createGrid } from './grid'
import { bounds, clampToColumn, isEmpty, makeRange, targets } from './range'

/**
 * The fill handle: drag a value from one editable cell down over the rows below.
 *
 * Nothing is written, and no request is made, until the pointer is released —
 * the drag only paints. On release it sends exactly ONE request for the whole
 * range and then reconciles each cell from the per-record results.
 *
 * It talks to the cells only through `data-server-value` / `data-record-version`,
 * the same attributes `wireEditableCell` already watches with a MutationObserver
 * for polling and external re-renders. So the fill adds no code to the cell
 * component and cannot drift from how a single edit reconciles.
 */

const TARGET_CLASS = 'wire-fill-target'

// How close the pointer has to be to the handle for it to stop re-targeting.
// The handle straddles a row boundary, so the last few pixels of any approach
// pass over the row BELOW — without this the handle jumps down exactly as the
// user reaches for it, and can never be caught.
const GRAB_RADIUS = 18

// How far inside the cell the handle sits, from its bottom-right corner.
// Excel puts it exactly ON the corner; here it is tucked in, so the handle lives
// wholly within the cell it belongs to. Sitting on the boundary meant every
// approach from above grazed the row below, and the expanded button spilled over
// the next row and column.
// 12 rather than 10 so the *expanded* 20px button still clears the cell edges
// instead of sitting flush against them.
const HANDLE_INSET = 12

// One morph guard for the page rather than one per table: Livewire's hook() has
// no off switch, so registering inside init() would stack a fresh hook every time
// a table re-initialises.
const draggingControllers = new Set()
let morphGuardInstalled = false

const installMorphGuard = () => {
    if (morphGuardInstalled || ! window.Livewire) return

    morphGuardInstalled = true

    // A poll landing mid-drag would morph the rows out from under the pointer.
    window.Livewire.hook('morph.updating', ({ skip }) => {
        if (draggingControllers.size > 0) skip()
    })
}

const wireFillHandle = () => ({
    grid: null,
    handle: null,
    overlay: null,
    scroller: null,
    max: Infinity,

    active: null,   // {row, col} of the cell being copied
    range: null,
    dragging: false,
    painted: [],
    _pending: null, // tail of the serialised request chain

    init() {
        this.grid = createGrid(this.$el)
        this.handle = this.$el.querySelector('[data-fill-handle]')
        this.overlay = this.$el.querySelector('[data-fill-overlay]')
        this.scroller = createAutoScroller(this.$el)

        const max = parseInt(this.$el.dataset.fillMax || '', 10)
        this.max = Number.isFinite(max) && max > 0 ? max : Infinity

        installMorphGuard()

        this._onFocusIn = (e) => this.onFocusIn(e)
        this._onPointerOver = (e) => this.onHover(e)
        this._onPointerLeave = () => this.onLeave()
        this._onPointerDown = (e) => this.startDrag(e)
        this._reposition = () => { if (this.active && ! this.dragging) this.place() }

        this.$el.addEventListener('focusin', this._onFocusIn)
        this.$el.addEventListener('pointerover', this._onPointerOver)
        this.$el.addEventListener('pointerleave', this._onPointerLeave)
        this.handle?.addEventListener('pointerdown', this._onPointerDown)
        window.addEventListener('resize', this._reposition)
        window.addEventListener('scroll', this._reposition, true)
    },

    destroy() {
        this.stopDrag()
        this.$el.removeEventListener('focusin', this._onFocusIn)
        this.$el.removeEventListener('pointerover', this._onPointerOver)
        this.$el.removeEventListener('pointerleave', this._onPointerLeave)
        this.handle?.removeEventListener('pointerdown', this._onPointerDown)
        window.removeEventListener('resize', this._reposition)
        window.removeEventListener('scroll', this._reposition, true)
    },

    // ── Which cell the handle belongs to ──────────────────────────────

    onFocusIn(e) {
        if (this.dragging) return
        if (this.handle?.contains(e.target)) return  // focusing the handle keeps the cell

        const point = this.grid.locate(e.target)
        const cell = point && this.grid.describe(point.row, point.col)

        if (! cell || this.isLocked(cell)) {
            this.deactivate()

            return
        }

        this.active = point
        this.place()
    },

    /**
     * Hovering a fillable cell moves the handle onto it, so a value can be
     * cloned without editing anything first — clicking into a text cell to
     * reveal the handle would start an edit the user never wanted.
     *
     * A focused fillable cell wins: the handle must not wander off the cell
     * someone is typing in just because the pointer drifted.
     */
    onHover(e) {
        if (this.dragging) return
        if (this.handle?.contains(e.target)) return  // hovering the handle keeps it put
        if (this.withinGrabRadius(e)) return         // …and so does heading for it
        if (this.focusedPoint()) return

        const point = this.grid.locate(e.target)
        const cell = point && this.grid.describe(point.row, point.col)

        // Over a column that cannot be filled: leave the handle where it is
        // rather than flickering it away on every pass across the table.
        if (! cell || this.isLocked(cell)) return

        this.active = point
        this.place()
    },

    /**
     * Whether the pointer is close enough to the handle to be reaching for it.
     *
     * Measured from the handle's centre rather than its box, so it holds while
     * the pointer is still in the neighbouring row on the way there.
     */
    withinGrabRadius(e) {
        if (! this.handle || this.handle.hidden) return false

        const rect = this.handle.getBoundingClientRect()

        return Math.abs(e.clientX - (rect.left + rect.width / 2)) <= GRAB_RADIUS
            && Math.abs(e.clientY - (rect.top + rect.height / 2)) <= GRAB_RADIUS
    },

    /** Pointer left the table entirely — drop the handle unless a cell holds focus. */
    onLeave() {
        if (this.dragging || this.focusedPoint()) return

        this.deactivate()
    },

    /** Where the focus sits in the grid, if it sits in a cell at all. */
    focusedPoint() {
        const el = document.activeElement

        if (! el || ! this.$el.contains(el)) return null

        return this.grid.locate(el)
    },

    /**
     * A cell whose control is disabled cannot be the source of a fill, nor a
     * target of one. This also covers a cell mid-save, whose input is disabled
     * while its own commit is in flight.
     */
    isLocked(cell) {
        return !! cell.el.querySelector('input, select, textarea, button')?.disabled
    },

    deactivate() {
        this.active = null

        if (this.handle) this.handle.hidden = true
    },

    /**
     * Park the handle just inside the active cell's bottom-right corner.
     *
     * The anchor is the handle's CENTRE — the element is translated by -50%, so
     * it can grow on hover without the anchor point moving.
     */
    place() {
        const cell = this.active && this.grid.describe(this.active.row, this.active.col)

        if (! cell || ! this.handle) {
            this.deactivate()

            return
        }

        const root = this.$el.getBoundingClientRect()
        const rect = cell.cell.getBoundingClientRect()

        this.handle.style.left = `${rect.right - root.left + this.$el.scrollLeft - HANDLE_INSET}px`
        this.handle.style.top = `${rect.bottom - root.top + this.$el.scrollTop - HANDLE_INSET}px`
        this.handle.hidden = false
    },

    // ── The drag ──────────────────────────────────────────────────────

    startDrag(e) {
        if (! this.active) return

        e.preventDefault()  // never start a text selection or a native drag

        // Capture keeps the pointer stream on the handle even once it leaves it,
        // which is what lets mouse, touch and pen share one code path. It is an
        // enhancement, not a requirement: the listeners below are on window, so
        // the drag still works if capture is unavailable or refused.
        try {
            this.handle.setPointerCapture?.(e.pointerId)
        } catch (err) {
            // Not a real active pointer — carry on uncaptured.
        }

        this.dragging = true
        this.range = makeRange(this.active)
        draggingControllers.add(this)
        document.body.classList.add('wire-filling')
        this.scroller.start()

        this._onMove = (ev) => this.onMove(ev)
        this._onUp = (ev) => this.finish(ev)
        this._onCancel = () => this.cancel()
        this._onKey = (ev) => { if (ev.key === 'Escape') this.cancel() }

        window.addEventListener('pointermove', this._onMove)
        window.addEventListener('pointerup', this._onUp)
        window.addEventListener('pointercancel', this._onCancel)
        window.addEventListener('keydown', this._onKey)
    },

    onMove(e) {
        if (! this.dragging) return

        this.scroller.update(e.clientY)

        const row = this.grid.rowAtY(e.clientY)

        if (row === null) return

        // Clamp to the table's own cap, so the drag simply stops growing rather
        // than building a range the server would refuse.
        const anchor = this.range.anchor.row
        const limited = row >= anchor
            ? Math.min(row, anchor + this.max)
            : Math.max(row, anchor - this.max)

        this.range = clampToColumn(makeRange(this.range.anchor, { row: limited, col: this.range.anchor.col }))
        this.paint()
    },

    paint() {
        this.clearPaint()

        for (const point of targets(this.range)) {
            const cell = this.grid.describe(point.row, point.col)

            if (! cell || this.isLocked(cell)) continue

            cell.cell.classList.add(TARGET_CLASS)
            this.painted.push(cell.cell)
        }

        this.placeOverlay()
    },

    clearPaint() {
        for (const cell of this.painted) cell.classList.remove(TARGET_CLASS)

        this.painted = []
    },

    placeOverlay() {
        if (! this.overlay) return

        const box = bounds(this.range)
        const first = this.grid.cellAt(box.top, box.left)
        const last = this.grid.cellAt(box.bottom, box.right)

        if (! first || ! last) return

        const root = this.$el.getBoundingClientRect()
        const a = first.getBoundingClientRect()
        const z = last.getBoundingClientRect()

        this.overlay.style.left = `${a.left - root.left + this.$el.scrollLeft}px`
        this.overlay.style.top = `${a.top - root.top + this.$el.scrollTop}px`
        this.overlay.style.width = `${z.right - a.left}px`
        this.overlay.style.height = `${z.bottom - a.top}px`
        this.overlay.hidden = isEmpty(this.range)
    },

    stopDrag() {
        if (this.dragging) {
            window.removeEventListener('pointermove', this._onMove)
            window.removeEventListener('pointerup', this._onUp)
            window.removeEventListener('pointercancel', this._onCancel)
            window.removeEventListener('keydown', this._onKey)
        }

        this.dragging = false
        draggingControllers.delete(this)
        document.body.classList.remove('wire-filling')
        this.scroller?.stop()
        this.clearPaint()

        if (this.overlay) this.overlay.hidden = true
    },

    /** Escape or a cancelled pointer: drop the range, write nothing. */
    cancel() {
        this.stopDrag()
        this.range = null
    },

    finish() {
        const range = this.range
        const source = this.active && this.grid.describe(this.active.row, this.active.col)

        this.stopDrag()
        this.range = null

        if (! range || isEmpty(range) || ! source) return

        const cells = targets(range)
            .map((point) => this.grid.describe(point.row, point.col))
            .filter((cell) => cell && ! this.isLocked(cell))

        if (cells.length === 0) return

        this.write(this.grid.columnAt(source.col), source, cells)
    },

    // ── The one request ───────────────────────────────────────────────

    write(column, source, cells) {
        const value = this.liveValue(source)
        const previous = new Map()

        for (const cell of cells) {
            previous.set(cell.recordKey, { value: this.liveValue(cell), version: cell.version })
            // Optimistic: the cells show the value immediately, before the request.
            this.applyValue(cell.el, value)
        }

        // Requests are serialised, the optimistic paint is not. Two fills in
        // quick succession used to go out together, and the second carried the
        // versions the DOM held BEFORE the first one wrote — so the server
        // rightly refused the whole range as a stale write and the user's last
        // drag was silently rolled back. Queueing lets each fill send versions
        // the one before it has already handed back.
        this._pending = (this._pending ?? Promise.resolve())
            .catch(() => {})
            .then(() => this.send(column, value, cells, previous))

        return this._pending
    },

    /**
     * @param {Map<string, {value: *, version: string|null}>} previous
     */
    async send(column, value, cells, previous) {
        const records = {}

        // Read the versions HERE rather than when the drag ended: by the time a
        // queued fill runs, the one before it has written the versions it was
        // given back onto these very cells.
        for (const cell of cells) {
            records[cell.recordKey] = cell.el.dataset.recordVersion || null
        }

        let response = null

        try {
            response = await this.$wire.fillTableCells([{ column, value, records }])
        } catch (e) {
            response = null
        }

        const results = response?.results?.[column] ?? null

        for (const cell of cells) {
            const result = results?.[cell.recordKey]

            if (result?.success) {
                this.applyVersion(cell.el, result.version)
                this.announce(cell, column, result.version)

                continue
            }

            // Refused, or the whole call failed: put this cell back. A conflict
            // hands back the winning value, so adopt that rather than the stale one.
            const before = previous.get(cell.recordKey)

            if (result?.conflict) {
                const state = this.stateOf(cell.el)

                this.applyValue(cell.el, state ? state.parse(result.currentValue) : result.currentValue)
                this.applyVersion(cell.el, result.currentVersion)
            } else {
                this.applyValue(cell.el, before.value)
                this.applyVersion(cell.el, before.version)
            }
        }
    },

    /** The cell's own Alpine state, or null when it has none. */
    stateOf(el) {
        try {
            return window.Alpine.$data(el)
        } catch (e) {
            return null
        }
    },

    /**
     * The cell's live value, with its type intact — a toggle holds a boolean,
     * not the '1' its data attribute serialises to.
     *
     * NOT named `valueOf`: that is an Object.prototype method, so JS calls it
     * implicitly whenever the component is coerced (a template literal, `==`, a
     * devtools inspection). With no argument it read `undefined.el` and threw,
     * and inside the component `this.valueOf` could resolve to the prototype's
     * version instead — which returns the component itself, so the *controller
     * object* went out as the value to write.
     */
    liveValue(cell) {
        const state = this.stateOf(cell.el)

        return state ? state.value : cell.serialized
    },

    /**
     * Write a value into a cell.
     *
     * Through the component's own state, NOT `data-server-value`: that attribute
     * is a server→client channel written by Blade and watched by the cell's
     * MutationObserver, and nothing keeps it current afterwards. A cell edited
     * inline still carries the value it was rendered with, so copying the
     * attribute would fill the range with a stale value while sending the live
     * one to the server — the two would disagree until the next full render.
     */
    applyValue(el, value) {
        // The attribute has to move WITH the component, not instead of it. The
        // cell observes data-server-value, so leaving it on what Blade rendered
        // means the next attribute change — applyVersion touching
        // data-record-version — wakes the observer, which re-reads the stale
        // value and quietly undoes the fill a moment after it landed.
        el.dataset.serverValue = this.serialize(value)

        const state = this.stateOf(el)

        if (! state) return

        state.value = value
        state.serverValue = value
        state.error = null
    },

    /**
     * The attribute form of a value, matching what the cell partials render:
     * a toggle writes '1'/'0', everything else its string.
     */
    serialize(value) {
        if (typeof value === 'boolean') {
            return value ? '1' : '0'
        }

        return value === null || value === undefined ? '' : String(value)
    },

    applyVersion(el, version) {
        if (! version) return

        el.dataset.recordVersion = version

        const state = this.stateOf(el)

        if (state) state.recordVersion = version
    },

    /** Tell other cells of the same record their lock version moved. */
    announce(cell, column, version) {
        if (! version) return

        window.dispatchEvent(new CustomEvent('wire-editable-committed', {
            detail: {
                componentId: this.$el.closest('[wire\\:id]')?.getAttribute('wire:id') ?? null,
                recordKey: cell.recordKey,
                column,
                version,
            },
        }))
    },
})

export default wireFillHandle
