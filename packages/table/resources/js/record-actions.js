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
    activeClasses: (config.keyboard?.activeClass || '').split(/\s+/).filter(Boolean),
    activeKey: null,
    anchorKey: null,

    init() {
        if (this.kb) this.initKeyboard()

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
        document.removeEventListener('click', this._onDocPointer)
        document.removeEventListener('keydown', this._onKey)
        window.removeEventListener('wheel', this._onScroll)
        hideRecordMenu(openRecordMenu)
    },

    // ── Keyboard navigation (grid pattern, roving tabindex) ──────────

    initKeyboard() {
        // One tabstop for the whole grid: the first row is reachable by Tab, the
        // rest by the arrow keys. Re-runs after a Livewire morph re-inits Alpine.
        const rows = this.navRows()
        rows.forEach((row, i) => row.setAttribute('tabindex', i === 0 ? '0' : '-1'))
    },

    // Main-body rows only (direct <tr data-row-key> children of this tbody). Group
    // headers have no key and sub-rows live in a nested table, so both drop out.
    navRows() {
        return [...this.$el.children].filter((el) => el.matches('tr[data-row-key]'))
    },

    onKeydown(event) {
        if (! this.kb) return

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
                this.anchorKey = rows[fromIdx < 0 ? toIdx : fromIdx]?.dataset.rowKey ?? null
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

    activate(rows, i) {
        rows.forEach((row, j) => {
            row.setAttribute('tabindex', j === i ? '0' : '-1')
            this.activeClasses.forEach((cls) => row.classList.toggle(cls, j === i))
        })
        this.activeKey = rows[i].dataset.rowKey
        rows[i].focus()
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
        const name = this.bindings[type]
        if (! name) return

        const row = this.row(event)
        if (! row || this.blocked(event, row)) return

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
        // own context menu through.
        if (! panel) return

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
