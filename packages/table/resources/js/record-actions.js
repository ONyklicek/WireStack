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
    '[x-data]',
].join(', ')

// Module-level handle to the single open context-menu panel, so opening one (or
// right-clicking another row) always closes any other first.
let openRecordMenu = null

function hideRecordMenu(panel) {
    if (! panel) return
    panel.style.display = 'none'
    if (openRecordMenu === panel) openRecordMenu = null
}

const wireRecordActions = (config = {}) => ({
    bindings: config.bindings || {},
    contextMenu: !! config.contextMenu,
    kb: config.keyboard || null,
    activeClasses: (config.active?.class || '').split(/\s+/).filter(Boolean),
    hoverClasses: (config.active?.hover || '').split(/\s+/).filter(Boolean),
    activeKey: null,
    anchorKey: null,

    init() {
        if (this.kb) {
            // Livewire morphs rows in and out on every sort / filter / search /
            // page change without re-creating this component. When the active row
            // is no longer on the page, drop the marker so the single tabstop
            // falls back to the first row and the grid stays reachable by Tab.
            this._rowObserver = new MutationObserver(() => {
                if (this.activeKey === null) return
                if (this.navRows().some((el) => el.dataset.rowKey === this.activeKey)) return

                this.activeKey = null
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
        hideRecordMenu(openRecordMenu)
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
        // it also pre-empts the browser's own select-all).
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'a' && this.kb.selectable) {
            event.preventDefault()
            return this.selectPage()
        }

        const idx = rows.findIndex((row) => row.dataset.rowKey === this.activeKey)

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault()
                return this.moveActive(rows, idx, idx < 0 ? 0 : Math.min(idx + 1, rows.length - 1), event.shiftKey)
            case 'ArrowUp':
                event.preventDefault()
                return this.moveActive(rows, idx, idx < 0 ? 0 : Math.max(idx - 1, 0), event.shiftKey)
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
                this.anchorKey = this.activeKey
                return
            case 'ContextMenu':
                if (idx < 0 || ! this.contextMenu) return
                event.preventDefault()
                return this.openMenuForRow(rows[idx])
        }

        // Any other key: match the record actions' own shortcuts (Delete, mod+d…).
        const name = this.matchShortcut(event)
        if (name && idx >= 0) {
            event.preventDefault()
            this.run(name)
        }
    },

    // Move the active row. With Shift held (and selection on), extend a contiguous
    // range from the anchor to the new row — desktop range-select. A plain move
    // drops the anchor so the next Shift-range starts fresh.
    moveActive(rows, fromIdx, toIdx, shift) {
        if (shift && this.kb.selectable) {
            if (this.anchorKey === null) {
                this.anchorKey = this.anchorFor(rows, fromIdx < 0 ? toIdx : fromIdx)
            }
            this.activate(rows, toIdx)
            this.selectRange(rows, toIdx)
        } else {
            this.anchorKey = null
            this.activate(rows, toIdx)
        }
    },

    // ── Selection bridge: reach the one selection component (the checkboxes and
    // bulk bar use it too), so keyboard selection stays a single source of truth
    // and stays optimistic (no per-keystroke roundtrip). ──────────────────────

    selection() {
        const root = this.$el.closest('[data-selection-root]')
        return root ? window.Alpine.$data(root) : null
    },

    toggleSelection(key) {
        const sel = this.selection()
        if (sel) sel.toggle(key)
        else this.$wire.toggleRecordSelection(key) // fallback: server path
    },

    // Where a Shift+range grows from when the keyboard has not set an anchor
    // itself: the far edge of the contiguous selected block the active row sits
    // in. A selection made with mod+A, a checkbox or the select-all strip carries
    // no anchor, and without this the first Shift+arrow would replace the whole
    // selection with a two-row range — every other row silently deselected.
    // Anchoring at the far edge makes that first Shift+arrow shrink or grow the
    // block the user is looking at instead.
    anchorFor(rows, idx) {
        const key = rows[idx]?.dataset.rowKey ?? null
        const sel = this.selection()
        const selected = (i) => sel?.isSelected(rows[i].dataset.rowKey)

        if (key === null || ! selected(idx)) return key

        let start = idx
        let end = idx
        while (start > 0 && selected(start - 1)) start--
        while (end < rows.length - 1 && selected(end + 1)) end++

        return (idx - start >= end - idx ? rows[start] : rows[end]).dataset.rowKey
    },

    // Replace the selection with the contiguous [anchor … active] block.
    selectRange(rows, activeIdx) {
        const sel = this.selection()
        if (! sel) return

        const anchorIdx = rows.findIndex((row) => row.dataset.rowKey === this.anchorKey)
        if (anchorIdx < 0) return

        const from = Math.min(anchorIdx, activeIdx)
        const to = Math.max(anchorIdx, activeIdx)
        const keys = rows.slice(from, to + 1).map((row) => row.dataset.rowKey)

        sel.mode = 'keys'
        sel.selected = keys
        sel.queueCommit?.()
    },

    selectPage() {
        const sel = this.selection()
        if (! sel) return

        sel.mode = 'keys'
        sel.selected = [...sel.pageKeys]
        sel.queueCommit?.()
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
        const rect = row.getBoundingClientRect()
        this.openMenu(panel, rect.left + 16, rect.top + rect.height)
    },

    matchShortcut(event) {
        const shortcuts = this.kb.shortcuts || {}
        for (const raw of Object.keys(shortcuts)) {
            if (this.eventMatchesShortcut(event, raw)) return shortcuts[raw]
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

        // A click on the row's own selection checkbox runs nothing, but it is a
        // selection gesture: it decides where a following Shift+arrow range grows
        // from, the same way a click does in a file explorer.
        if (type === 'click' && event.target.closest('[role="checkbox"]')) {
            this.anchorKey = row.dataset.rowKey
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
