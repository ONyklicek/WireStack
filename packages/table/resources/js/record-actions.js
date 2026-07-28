/**
 * wireRecordActions — one delegated controller per table for whole-row
 * interaction (record actions) and the row context menu.
 *
 * Design: a single Alpine component on the main `<tbody>`, not one per row. It
 * listens for click / dblclick / contextmenu that bubble up from the rows and
 * resolves the target row from `data-row-key`, so there is no per-row Alpine
 * component and no per-row listener — the render-cost model forbids exactly that.
 *
 * A record action never fires from an interactive element (button, checkbox,
 * link, input, editable cell, nested dropdown): the guard is centralized here as
 * a `closest(INTERACTIVE)` test, so ordinary buttons and checkboxes need no
 * `stopPropagation()` of their own.
 *
 * Pointer triggers dispatch through the table's existing `openActionModal`
 * endpoint — which itself runs the action directly when it has no modal — so this
 * controller owns no execution pipeline. The context menu reuses the per-row
 * teleported panel, opened, positioned and closed centrally from here instead of
 * from a per-row `wireContextMenu` component.
 */

// Elements that keep the row inert: a click on any of these is theirs, not the
// row's. `[data-record-key]` is an editable cell; `[x-data]` is any nested Alpine
// island (dropdown, toggle, …).
const INTERACTIVE = [
    'a[href]',
    'button',
    'input',
    'select',
    'textarea',
    'label',
    '[role="checkbox"]',
    '[role="button"]',
    '[role="menuitem"]',
    '[contenteditable]',
    '[data-record-key]',
    // The whole selection cell, not just the checkbox inside it: the cell now
    // toggles on click, and without this the padding around the box would fall
    // through and run the row's record action instead.
    '[data-select-cell]',
    '[x-data]',
].join(', ')

import { createAutoScroller } from '../../../core/resources/js/support/autoscroll'
import { rowAtY } from '../../../core/resources/js/support/rows'

// Module-level handle to the single open context-menu panel, so opening one (or
// right-clicking another row) always closes any other first.
let openRecordMenu = null

// One morph guard for the page rather than one per table: Livewire's hook() has
// no off switch, so registering inside init() would stack a fresh hook every
// time a table re-initialises. Same pattern as the fill handle's guard — a poll
// landing mid-sweep would morph the rows out from under the pointer.
const sweepingControllers = new Set()
let sweepMorphGuardInstalled = false

const installSweepMorphGuard = () => {
    if (sweepMorphGuardInstalled || ! window.Livewire) return

    sweepMorphGuardInstalled = true

    window.Livewire.hook('morph.updating', ({ skip }) => {
        if (sweepingControllers.size > 0) skip()
    })
}

function hideRecordMenu(panel) {
    if (! panel) return
    panel.style.display = 'none'
    if (openRecordMenu === panel) openRecordMenu = null
}

const wireRecordActions = (config = {}) => ({
    bindings: config.bindings || {},
    contextMenu: !! config.contextMenu,
    kb: config.keyboard || null,
    // The mouse half of the gesture layer, switchable on its own: a table may
    // keep the checkbox sweep with the keyboard off, or drop the Shift-ranges
    // and keep everything else. Absent config means the full set, so a published
    // view predating the switches behaves exactly as it did.
    gestures: { sweep: true, ranges: true, ...(config.gestures || {}) },
    activeClasses: (config.active?.class || '').split(/\s+/).filter(Boolean),
    hoverClasses: (config.active?.hover || '').split(/\s+/).filter(Boolean),
    activeKey: null,

    init() {
        if (this.kb) {
            // Livewire morphs rows in and out on every sort / filter / search /
            // page change without re-creating this component. When the active row
            // is no longer on the page, drop the marker so the single tabstop
            // falls back to the first row and the grid stays reachable by Tab.
            // The range anchor gets the same treatment — but ONLY when its row
            // left the page: clearing it on every morph would also wipe the
            // range base, and a base holding keys from a previous page would
            // otherwise be unioned back by the next Shift+range, resurrecting
            // rows nobody sees selected.
            this._rowObserver = new MutationObserver(() => {
                const onPage = (key) => this.navRows().some((el) => el.dataset.rowKey === key)

                if (this.activeKey !== null && ! onPage(this.activeKey)) {
                    this.activeKey = null
                }

                const sel = this.selection()
                if (sel && sel.anchorKey !== null && ! onPage(sel.anchorKey)) {
                    sel.clearAnchor()
                }
            })
            this._rowObserver.observe(this.$el, { childList: true })

            // Focus rescue. A record action's modal is teleported to <body>; when
            // it closes, the element holding the focus (its Cancel button) is
            // hidden with it and the focus falls back to <body> — out of reach of
            // the grid's own keydown listener, so the arrow keys silently stop
            // working until the user clicks a row again. `relatedTarget === null`
            // is exactly that "focus fell into the void" case.
            this._onFocusOut = (event) => {
                if (event.relatedTarget || ! this.activeKey) return
                if (! event.target.closest?.('[role="dialog"], [data-record-menu]')) return

                // Past the modal's leave transition, so a parent modal revealed
                // underneath is already visible and keeps the focus for itself.
                clearTimeout(this._focusTimer)
                this._focusTimer = setTimeout(() => this.restoreFocus(), 300)
            }

            document.addEventListener('focusout', this._onFocusOut)
        }

        this.initSweep()

        if (! this.contextMenu) return

        // Global close triggers, bound once for the whole table (not per row).
        this._onDocPointer = () => hideRecordMenu(openRecordMenu)
        this._onKey = (event) => {
            if (event.key === 'Escape') hideRecordMenu(openRecordMenu)
        }
        this._onScroll = () => hideRecordMenu(openRecordMenu)

        document.addEventListener('click', this._onDocPointer)
        document.addEventListener('keydown', this._onKey)
        window.addEventListener('wheel', this._onScroll, { passive: true })
    },

    destroy() {
        clearTimeout(this._clickTimer)
        clearTimeout(this._focusTimer)
        this._rowObserver?.disconnect()
        document.removeEventListener('focusout', this._onFocusOut)
        document.removeEventListener('click', this._onDocPointer)
        document.removeEventListener('keydown', this._onKey)
        window.removeEventListener('wheel', this._onScroll)
        this.stopSweep()
        this._sweepRoot?.removeEventListener('pointerdown', this._onSweepDown)
        this._sweepRoot?.removeEventListener('click', this._onSweepClickCapture, true)
        document.removeEventListener('pointermove', this._onSweepMove)
        document.removeEventListener('pointerup', this._onSweepUp)
        document.removeEventListener('pointercancel', this._onSweepUp)
        hideRecordMenu(openRecordMenu)
    },

    // ── Selection sweep: drag down the checkbox column to select ─────

    initSweep() {
        // Not `kb.selectable`: the sweep is a mouse gesture and survives the
        // keyboard layer being switched off. The server has already folded the
        // selectable check into this flag.
        if (! this.gestures.sweep) return

        const root = this.$el.closest('[data-selection-root]')
        if (! root) return

        this._sweepRoot = root
        this._sweep = null

        this._onSweepDown = (event) => {
            // Mouse only, primary button, primary pointer: a finger dragging
            // the checkbox column must keep scrolling the page (touch-action
            // stays untouched for the same reason).
            if (event.pointerType !== 'mouse' || event.button !== 0 || ! event.isPrimary) return

            // The cell is found by its hook, never by column position —
            // sortable prepends a drag-handle <td> and would shift indexes.
            const cell = event.target.closest('[data-select-cell]')
            const row = cell?.closest('tr[data-row-key]')
            if (! cell || ! row || row.parentElement !== this.$el) return

            // ARM only — no preventDefault (it would kill the focus on the
            // <button role=checkbox> underneath) and no selection yet: the
            // gesture engages on the first move that changes rows, so a plain
            // click stays a plain click.
            this._sweep = { startKey: row.dataset.rowKey, engaged: false, scroller: null }
        }

        this._onSweepMove = (event) => {
            const sweep = this._sweep
            if (! sweep) return
            if (! (event.buttons & 1)) return this.stopSweep()

            const rows = this.navRows()
            const startIdx = rows.findIndex((r) => r.dataset.rowKey === sweep.startKey)
            if (startIdx < 0) return this.stopSweep()

            const current = rowAtY(rows, event.clientY)
            if (current === null) return

            if (! sweep.engaged) {
                if (current === startIdx) return

                sweep.engaged = true
                sweepingControllers.add(this)
                installSweepMorphGuard()
                sweep.scroller = createAutoScroller(rows[0])
                sweep.scroller.start()
                this.suppressSweepTransitions(rows)
            }

            event.preventDefault()
            sweep.scroller.update(event.clientY)

            // Additive only: the swept span is unioned in, backing up never
            // deselects. toggle() carries both shapes — in all mode a swept
            // excluded row is simply re-included.
            const sel = this.selection()
            if (! sel) return

            rows.slice(Math.min(startIdx, current), Math.max(startIdx, current) + 1)
                .map((r) => r.dataset.rowKey)
                .filter((key) => ! sel.isSelected(key))
                .forEach((key) => sel.toggle(key))
        }

        this._onSweepUp = () => {
            if (this._sweep?.engaged) {
                // The drag's trailing click retargets to the common ancestor of
                // the press and release rows — the selection root is an
                // ancestor of both, so a capture listener there kills it before
                // it reaches a checkbox or a record action. Pointer capture
                // would not help. The capture handler clears the flag one-shot;
                // the timeout is only the backstop for a click that never
                // arrives, and it must outlive the browser's own dispatch — the
                // click can land in a later task than a 0ms timer.
                this._suppressSweepClick = true
                clearTimeout(this._suppressSweepTimer)
                this._suppressSweepTimer = setTimeout(() => { this._suppressSweepClick = false }, 150)
            }

            this.stopSweep()
        }

        this._onSweepClickCapture = (event) => {
            if (! this._suppressSweepClick) return

            this._suppressSweepClick = false
            event.stopPropagation()
            event.preventDefault()
        }

        root.addEventListener('pointerdown', this._onSweepDown)
        document.addEventListener('pointermove', this._onSweepMove)
        document.addEventListener('pointerup', this._onSweepUp)
        document.addEventListener('pointercancel', this._onSweepUp)
        root.addEventListener('click', this._onSweepClickCapture, true)
    },

    stopSweep() {
        if (! this._sweep) return

        this._sweep.scroller?.stop()
        sweepingControllers.delete(this)
        this.restoreSweepTransitions()
        this._sweep = null
    },

    // Reduced motion means no animation during the sweep: the checkbox and row
    // tints carry transition utilities that would otherwise flicker through
    // every swept row.
    suppressSweepTransitions(rows) {
        if (! window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return

        this._sweepStyled = rows.flatMap((row) => [row, ...row.querySelectorAll('[data-select-cell] [role="checkbox"]')])
        this._sweepStyled.forEach((el) => { el.style.transitionDuration = '0s' })
    },

    restoreSweepTransitions() {
        (this._sweepStyled || []).forEach((el) => { el.style.transitionDuration = '' })
        this._sweepStyled = null
    },

    // ── Keyboard navigation (grid pattern, roving tabindex) ──────────

    // One tabstop for the whole grid: the active row is reachable by Tab (the
    // first row until one is chosen), the rest by the arrow keys. Bound per row
    // rather than assigned, because Livewire morphs every row back to the
    // server's markup on each update — an assigned tabindex would be wiped and
    // the grid would silently drop out of the tab order.
    rowTabindex(key, index) {
        return (this.activeKey === null ? index === 0 : this.activeKey === key) ? 0 : -1
    },

    // Main-body rows only (direct <tr data-row-key> children of this tbody). Group
    // headers have no key and sub-rows live in a nested table, so both drop out.
    navRows() {
        return [...this.$el.children].filter((el) => el.matches('tr[data-row-key]'))
    },

    onKeydown(event) {
        if (! this.kb) return

        // Only a focused row owns these keys. A keystroke inside the row — a row
        // action button, an inline-editable input, a dropdown — belongs to that
        // element: Enter would otherwise press the button *and* run the primary
        // record action, and Space would swallow a space typed into a cell.
        if (event.target !== this.row(event)) return

        // A modal opened from the keyboard leaves the focus on the row behind it,
        // so the grid would keep answering keys under the open dialog: the arrows
        // would move the marker out of sight and a shortcut would fire a second
        // action against a *different* record than the one the modal is asking
        // about. While a dialog is up, the grid is inert.
        if (this.dialogOpen()) return

        const rows = this.navRows()
        if (! rows.length) return

        // Ctrl/⌘+A → select every row on the page (before the shortcut matcher, so
        // it also pre-empts the browser's own select-all). mod+Shift+A is not
        // this gesture — without the check it would select the page too.
        if ((event.ctrlKey || event.metaKey) && ! event.shiftKey && event.key.toLowerCase() === 'a' && this.kb.selectable) {
            event.preventDefault()
            return this.selection()?.selectPage()
        }

        const idx = rows.findIndex((row) => row.dataset.rowKey === this.activeKey)
        const mod = event.ctrlKey || event.metaKey

        switch (event.key) {
            case 'ArrowDown':
            case 'ArrowUp': {
                // mod alone + arrow stays unbound: on macOS ctrl+arrows are
                // Mission Control (the page never even sees them) and ⌘+arrows
                // belong to the browser. mod+Shift+arrow means "to the edge" —
                // the same target Home/End reach.
                if (mod && ! event.shiftKey) return

                const down = event.key === 'ArrowDown'
                const step = idx < 0 ? 0 : (down ? Math.min(idx + 1, rows.length - 1) : Math.max(idx - 1, 0))
                const target = mod && event.shiftKey ? (down ? rows.length - 1 : 0) : step

                event.preventDefault()
                return this.moveActive(rows, idx, target, event.shiftKey)
            }
            case 'Home':
            case 'End':
                // Shift+Home/End lands on the same indices as mod+Shift+arrow —
                // deliberately one implementation.
                event.preventDefault()
                return this.moveActive(rows, idx, event.key === 'Home' ? 0 : rows.length - 1, event.shiftKey)
            case 'PageUp':
            case 'PageDown': {
                // mod+PageUp/PageDown is browser tab switching — never bind it.
                if (mod) return

                const step = this.pageStep(rows)
                const from = idx < 0 ? 0 : idx
                const target = event.key === 'PageDown'
                    ? Math.min(from + step, rows.length - 1)
                    : Math.max(from - step, 0)

                event.preventDefault()
                return this.moveActive(rows, idx, target, event.shiftKey)
            }
            case 'Enter':
                if (idx < 0) return
                event.preventDefault()
                return this.run(event.shiftKey ? this.kb.secondary : this.kb.primary)
            case ' ':
            case 'Spacebar':
                if (idx < 0) return
                event.preventDefault()
                if (! this.kb.selectable) return this.run(this.kb.primary)
                // Toggle the active row and drop the anchor here for a later range.
                this.toggleSelection(this.activeKey)
                this.setAnchor(this.activeKey)
                return
            case 'F10':
                // Shift+F10 is the other standard "context menu" key; it can't
                // go through the shortcut matcher, because kb.shortcuts maps to
                // action names and opening the menu is not an action. Plain F10
                // stays the browser's (menu-bar focus).
                if (! event.shiftKey) return
                // fall through
            case 'ContextMenu':
                if (idx < 0 || ! this.contextMenu) return
                event.preventDefault()
                return this.openMenuForRow(rows[idx])
            case '?':
                // Matched on event.key, never event.code: on a Czech layout ? is
                // Shift+comma, so the physical code would never line up. The
                // focus guard above already keeps a ? typed into the search box
                // out of here. The help modal listens for this window event and
                // opens client-side; the name carries the component id, so only
                // this table's help opens.
                if (! this.kb.help) return
                event.preventDefault()
                return window.dispatchEvent(new CustomEvent(this.kb.help))
        }

        // Any other key: match the record actions' own shortcuts (Delete, mod+d…).
        const name = this.matchShortcut(event)
        if (name && idx >= 0) {
            event.preventDefault()
            this.run(name)
        }
    },

    // Rows per PageUp/PageDown jump: the row PITCH (offset between adjacent row
    // tops — includes borders and spacing, unlike a bare offsetHeight) against
    // the height of the nearest scrolling ancestor. The package ships no scroll
    // container of its own, but tables render inside modals with overflow-y-auto
    // bodies and consumers wrap tables in their own max-h containers — the
    // window is only the fallback. A display:none table (the mobile card
    // breakpoint) reports zero pitch: fall back to a single step instead of
    // dividing by zero into Infinity and throwing in activate().
    pageStep(rows) {
        if (rows.length < 2) return 1

        const pitch = rows[1].getBoundingClientRect().top - rows[0].getBoundingClientRect().top
        const viewport = this.scrollViewportHeight(rows[0])
        if (! pitch || ! viewport) return 1

        return Math.max(1, Math.floor(viewport / pitch))
    },

    scrollViewportHeight(el) {
        for (let node = el.parentElement; node; node = node.parentElement) {
            // Computed overflow-y is 'auto' on any overflow-x-auto wrapper (CSS
            // pairs the axes), and the table's own horizontal scroller is such
            // a wrapper — only an ancestor that actually overflows vertically
            // is a viewport; anything else would report the full table height
            // and PageDown would jump straight to the last row.
            if (/(auto|scroll)/.test(getComputedStyle(node).overflowY) && node.scrollHeight > node.clientHeight + 1) {
                return node.clientHeight
            }
        }

        return window.innerHeight
    },

    // Move the active row. With Shift held (and ranges on), extend a contiguous
    // range from the anchor to the new row — desktop range-select. A plain move
    // drops the anchor so the next Shift-range starts fresh; so does a Shift move
    // on a table whose ranges are switched off, where Shift+arrow is just an
    // arrow.
    moveActive(rows, fromIdx, toIdx, shift) {
        const sel = this.selection()

        if (shift && this.gestures.ranges && this.kb.selectable && sel) {
            if (sel.anchorKey === null) {
                sel.anchorKey = sel.anchorFor(rows, fromIdx < 0 ? toIdx : fromIdx)
            }
            this.activate(rows, toIdx)
            sel.selectRange(rows, toIdx)
        } else {
            sel?.clearAnchor()
            this.activate(rows, toIdx)
        }
    },

    // ── Selection bridge: reach the one selection component (the checkboxes and
    // bulk bar use it too), so keyboard selection stays a single source of truth
    // and stays optimistic (no per-keystroke roundtrip). ──────────────────────

    selection() {
        const root = this.$el.closest('[data-selection-root]')
        if (! root) return null

        // The anchor and the range gestures live in wireRecordSelection, which
        // ships with the package — a published table view predating it still
        // renders a selection root, so without this check the ranges would
        // just select wrong, silently. Reject the stale markup out loud.
        if (root.dataset.selectionVersion !== '1') {
            if (! this._staleSelectionWarned) {
                this._staleSelectionWarned = true
                console.error(
                    '[wire-table] The published tables/index.blade.php predates the '
                    + 'selection component this wire-table version ships. Republish the '
                    + 'views (php artisan vendor:publish --tag="wire-table::views" --force) '
                    + 'or delete the published copy; selection gestures are disabled until then.'
                )
            }

            return null
        }

        return window.Alpine.$data(root)
    },

    toggleSelection(key) {
        const sel = this.selection()
        if (sel) sel.toggle(key)
        else this.$wire.toggleRecordSelection(key) // fallback: server path
    },

    setAnchor(key) {
        this.selection()?.setAnchor(key)
    },

    // Shift+click / mod+click / mod+Shift+click — the pointer counterparts of
    // the keyboard range gestures, sharing the same anchor, base and range
    // arithmetic in the selection component.
    onSelectionClick(row, event) {
        const sel = this.selection()
        if (! sel) return

        const rows = this.navRows()
        const idx = rows.indexOf(row)
        if (idx < 0) return

        if (event.shiftKey) {
            // Range from the anchor; with no anchor set, fall back the same
            // way the keyboard does — from the block edge of the row the user
            // was on BEFORE this click, so resolve it before marking.
            if (sel.anchorKey === null) {
                const fromIdx = rows.findIndex((r) => r.dataset.rowKey === this.activeKey)
                sel.anchorKey = sel.anchorFor(rows, fromIdx < 0 ? idx : fromIdx)
            }

            this.markActive(row)
            sel.selectRange(rows, idx, { additive: event.ctrlKey || event.metaKey })
            return
        }

        // mod alone: toggle the single row and anchor the next range on it,
        // exactly like a checkbox click.
        this.markActive(row)
        sel.toggle(row.dataset.rowKey)
        sel.setAnchor(row.dataset.rowKey)
    },

    // A row was focused directly (click / Tab): adopt it as the active row so
    // arrow keys continue from there.
    onRowFocus(event) {
        if (! this.kb) return
        const row = event.target.closest('[data-row-key]')
        if (row && row.parentElement === this.$el) this.activeKey = row.dataset.rowKey
    },

    // The class object for one row, consumed by that row's `:class` binding.
    // Bound rather than toggled from here on purpose: an Alpine binding is
    // re-evaluated after a Livewire morph, so the marker survives the roundtrip
    // the click itself triggers, which a `classList.toggle()` would not.
    //
    // The active row also drops its hover tint: `hover:bg-*` is emitted after the
    // plain `bg-*` utility, so it would otherwise paint over the marker for as
    // long as the pointer rests on the row the user just clicked.
    rowClass(key) {
        const active = this.activeKey === key
        const classes = {}

        this.activeClasses.forEach((cls) => { classes[cls] = active })
        this.hoverClasses.forEach((cls) => { classes[cls] = ! active })

        return classes
    },

    // A pointer landed on a row: adopt it as the active row, so the click is
    // visible, the roving tabindex follows the pointer and arrow keys continue
    // from the row the user just touched.
    markActive(row) {
        const rows = this.navRows()
        const i = rows.indexOf(row)

        if (i >= 0) this.activate(rows, i, { scroll: false })
    },

    // Whether any modal is on screen right now — a stacked parent modal revealed
    // by closing the top one owns the keyboard, not the grid behind it.
    dialogOpen() {
        return [...document.querySelectorAll('[role="dialog"]')].some(
            (el) => (el.checkVisibility ? el.checkVisibility() : el.getClientRects().length > 0)
        )
    },

    restoreFocus() {
        // Anything that claimed the focus in the meantime (a modal input, another
        // table, the user's own click) keeps it.
        if (document.activeElement !== document.body) return
        if (this.dialogOpen()) return

        const row = this.navRows().find((el) => el.dataset.rowKey === this.activeKey)
        row?.focus({ preventScroll: true })
    },

    activate(rows, i, { scroll = true } = {}) {
        // The roving tabindex follows `activeKey` through the rows' own binding.
        this.activeKey = rows[i].dataset.rowKey

        // Focus belongs to the keyboard layer; with keyboard nav off the rows are
        // not focusable at all and only the marker moves.
        if (this.kb) rows[i].focus({ preventScroll: ! scroll })
    },

    run(name) {
        if (name) this.$wire.openActionModal(this.activeKey, name)
    },

    openMenuForRow(row) {
        const panel = document.querySelector(`[data-record-menu="${CSS.escape(row.dataset.rowKey)}"]`)
        if (! panel) return

        // In a real (headed) browser, Shift+F10 and the ContextMenu key also
        // emit a native `contextmenu` event right after the keydown; without
        // this flag onContextMenu would immediately reposition the menu to the
        // event's stale pointer coordinates. Headless Chrome never emits that
        // follow-up event, so this guard is deliberately NOT covered by the
        // CDP driver — keep it when refactoring.
        this._menuFromKey = true
        setTimeout(() => { this._menuFromKey = false }, 0)

        const rect = row.getBoundingClientRect()
        this.openMenu(panel, rect.left + 16, rect.top + rect.height)

        // Keyboard-opened, so the focus follows into the panel: dialogOpen()
        // cannot see a role=menu surface, and with the focus left on the row
        // the arrows would keep moving the marker — and shortcuts firing —
        // behind the open menu.
        panel.querySelector('[role="menuitem"], button, a')?.focus()
    },

    matchShortcut(event) {
        const shortcuts = this.kb.shortcuts || {}

        // Exact pass first, so an explicit Backspace binding beats the alias.
        for (const raw of Object.keys(shortcuts)) {
            if (this.eventMatchesShortcut(event, raw)) return shortcuts[raw]
        }

        // Alias pass: Delete and Backspace are one equivalence class — the big
        // key on a Mac keyboard reports Backspace where the gesture means
        // "delete this record". A JS alias, deliberately not a PHP reservation:
        // in PHP it would forbid ->onKey('Backspace') forever, and the legend
        // can render the pair as one row.
        const alias = { delete: 'Backspace', backspace: 'Delete' }[event.key.toLowerCase()]
        if (! alias) return null

        const aliased = {
            ctrlKey: event.ctrlKey, metaKey: event.metaKey,
            shiftKey: event.shiftKey, altKey: event.altKey, key: alias,
        }

        for (const raw of Object.keys(shortcuts)) {
            if (this.eventMatchesShortcut(aliased, raw)) return shortcuts[raw]
        }

        return null
    },

    eventMatchesShortcut(event, raw) {
        const parts = raw.toLowerCase().split('+').map((s) => s.trim())
        const key = parts[parts.length - 1]
        const mods = parts.slice(0, -1)
        const isMac = /mac/i.test(navigator.userAgent)
        const wantCtrl = mods.includes('ctrl') || mods.includes('control') || (mods.includes('mod') && ! isMac)
        const wantMeta = mods.includes('meta') || mods.includes('cmd') || mods.includes('command') || (mods.includes('mod') && isMac)
        const wantShift = mods.includes('shift')
        const wantAlt = mods.includes('alt') || mods.includes('option')

        if (!! event.ctrlKey !== wantCtrl) return false
        if (!! event.metaKey !== wantMeta) return false
        if (!! event.shiftKey !== wantShift) return false
        if (!! event.altKey !== wantAlt) return false

        return event.key.toLowerCase() === key
    },

    // The main-tbody row this event belongs to, or null. Sub-rows live in a
    // nested <table> and group headers carry no key, so both resolve to null.
    row(event) {
        const el = event.target.closest('[data-row-key]')
        if (! el || el.closest('tbody') !== this.$el) return null

        return el
    },

    // An interaction is inert only when it lands on an interactive element
    // *inside the row* — a button, checkbox, link, editable cell or nested Alpine
    // island. The search is scoped to the row on purpose: the controller's own
    // root (`<tbody x-data>`) is an ancestor of every row and would otherwise
    // match the `[x-data]` clause and swallow every event.
    blocked(event, row) {
        const hit = event.target.closest(INTERACTIVE)

        return !! hit && row.contains(hit)
    },

    onPointer(type, event) {
        const row = this.row(event)
        if (! row) return

        // A click in the row's own selection cell runs nothing, but it is a
        // selection gesture: it decides where a following Shift+arrow range grows
        // from, the same way a click does in a file explorer. Anywhere in the
        // cell counts, since the whole cell toggles.
        //
        // Never on a Shift+click, though: that gesture ranges FROM the existing
        // anchor, and moving the anchor to the row being clicked collapsed the
        // range to the two rows around it — a Shift+click on the checkbox
        // selected 2 rows where the same click on the row body selected 5.
        if (type === 'click' && this.gestures.ranges && ! event.shiftKey && event.target.closest('[role="checkbox"], [data-select-cell]')) {
            this.setAnchor(row.dataset.rowKey)
        }

        // A modifier click is a selection gesture, never an action gesture:
        // Shift+click ranges from the anchor, mod+click toggles the row (even
        // outside the checkbox), mod+Shift+click adds a whole block — and none
        // of them may fall through into a bound click action.
        //
        // The selection cell is exempt from blocked() here, and only here: it is
        // inert for plain clicks (its own handler toggles), but a modified click
        // on the checkbox means the same thing as one on the row body, and
        // letting it stop at the cell was why Shift+clicking a checkbox toggled
        // one row where the row body ranged over five. Every other interactive
        // element keeps its modified clicks — Shift+clicking an editable cell
        // belongs to that cell.
        const modified = event.shiftKey || event.ctrlKey || event.metaKey
        const inSelectCell = !! event.target.closest('[data-select-cell]')

        // Ranges off: a modified click is an ordinary click, and falls through to
        // whatever the row is bound to — the plain-web reading of Ctrl+click.
        if (type === 'click' && this.gestures.ranges && modified && (inSelectCell || ! this.blocked(event, row))) {
            return this.onSelectionClick(row, event)
        }

        if (this.blocked(event, row)) return

        // Marking is unconditional: a click on a row of an interactive table
        // moves the active row even when the gesture itself is bound to
        // something else (double-click) or to nothing at all.
        this.markActive(row)

        const name = this.bindings[type]
        if (! name) return

        // A double-click still emits `click` (twice) first. When both gestures are
        // bound — the view/edit pattern — defer the single-click action so a
        // following `dblclick` can cancel it; otherwise double-clicking to edit
        // would also run (and re-run) the single-click view action.
        if (type === 'click' && this.bindings.dblclick) {
            clearTimeout(this._clickTimer)
            const key = row.dataset.rowKey
            this._clickTimer = setTimeout(() => {
                this._clickTimer = null
                this.$wire.openActionModal(key, name)
            }, 250)
            return
        }

        if (type === 'dblclick') {
            clearTimeout(this._clickTimer)
            this._clickTimer = null
        }

        // openActionModal opens the modal when the action has one and runs it
        // directly otherwise — the single modal-aware entry point.
        this.$wire.openActionModal(row.dataset.rowKey, name)
    },

    onContextMenu(event) {
        if (! this.contextMenu) return

        // The native contextmenu a real browser fires right after Shift+F10 /
        // the ContextMenu key — the menu is already open at the row; eat the
        // event or it would jump to stale pointer coordinates.
        if (this._menuFromKey) {
            event.preventDefault()
            return
        }

        const row = this.row(event)
        if (! row || this.blocked(event, row)) return

        const panel = document.querySelector(
            `[data-record-menu="${CSS.escape(row.dataset.rowKey)}"]`
        )

        // No panel means this row has no visible menu action — let the browser's
        // own context menu through, and leave the active row where it was.
        if (! panel) return

        this.markActive(row)
        event.preventDefault()
        this.openMenu(panel, event.clientX, event.clientY)
    },

    openMenu(panel, x, y) {
        if (openRecordMenu && openRecordMenu !== panel) hideRecordMenu(openRecordMenu)
        openRecordMenu = panel

        panel.style.display = ''

        // Position at the cursor, nudged back inside the viewport so it is never
        // clipped at the right/bottom edge.
        const pad = 8
        const { width, height } = panel.getBoundingClientRect()

        if (x + width + pad > window.innerWidth) x = window.innerWidth - width - pad
        if (y + height + pad > window.innerHeight) y = window.innerHeight - height - pad

        panel.style.left = `${Math.max(pad, x)}px`
        panel.style.top = `${Math.max(pad, y)}px`
    },
})

document.addEventListener('alpine:init', () => {
    window.Alpine.data('wireRecordActions', wireRecordActions)
})
