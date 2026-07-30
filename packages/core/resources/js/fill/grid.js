/**
 * Read-only view of a table as a grid of fillable cells.
 *
 * Everything the fill needs is already in the DOM: the row order and record keys
 * on `<tr data-row-key>`, the column on `<td data-column>`, and the value plus
 * optimistic-lock version on the editable cell root that `wireEditableCell`
 * mounts on. So this asks the document rather than the server, and nothing about
 * a fill has to be serialised into the page a second time.
 *
 * Coordinates are {row, col} indices into the *fillable* column list, not the
 * visible one — a range then means the same thing whether it grew vertically or
 * (later) horizontally.
 */

import { bodyRows, rowAtY } from '../support/rows'

/**
 * The live optimistic-lock version of a cell root.
 *
 * The one thing above that the document does NOT keep current. `wireEditableCell`
 * mounts with `wire:ignore.self` on purpose, so a morph cannot overwrite the
 * optimistic state it is holding — and the cost is that Livewire never refreshes
 * that element's attributes either. The server does return a fresh version and
 * `commit()` moves the component's own `recordVersion`, but `data-record-version`
 * keeps whatever the first render wrote.
 *
 * So a cell edited inline still advertises the version the page loaded with, and
 * sending that makes the server reject the write as someone else's edit — which
 * the fill then rolls back with no error shown, because the refusal looked
 * legitimate. The component's state is the only current copy; the attribute is
 * the fallback for a cell that has no component (a plain, non-editable cell).
 */
export const versionOf = (el) => {
    try {
        return (window.Alpine.$data(el)?.recordVersion ?? el.dataset.recordVersion) || null
    } catch (e) {
        return el.dataset.recordVersion || null
    }
}

export const createGrid = (root) => {
    let columns = []

    try {
        columns = JSON.parse(root.dataset.fillColumns || '[]')
    } catch (e) {
        columns = []
    }

    // The first table in the root is the outer one; sub-row tables are nested
    // inside its cells and must not be mistaken for it.
    const table = root.querySelector('table')

    const grid = {
        columns,

        rows: () => bodyRows(table),

        columnAt: (col) => columns[col] ?? null,

        colOf: (name) => {
            const i = columns.indexOf(name)

            return i === -1 ? null : i
        },

        cellAt(row, col) {
            const name = columns[col]

            if (name == null) return null

            return this.rows()[row]?.querySelector(`:scope > td[data-column="${CSS.escape(name)}"]`) ?? null
        },

        /**
         * The cell's editable root, or null when this record did not render one —
         * a per-record disabled or readonly cell renders a plain value instead, so
         * the fill skips it without ever having to know why.
         */
        rootIn(cell) {
            return cell?.querySelector('[data-record-key][data-column-name]') ?? null
        },

        /** Everything needed to preview, write and reconcile one cell. */
        describe(row, col) {
            const cell = this.cellAt(row, col)
            const el = this.rootIn(cell)

            if (! cell || ! el) return null

            return {
                row,
                col,
                cell,
                el,
                recordKey: el.dataset.recordKey,
                version: versionOf(el),
                serialized: el.dataset.serverValue ?? '',
            }
        },

        /** Where an element sits in the grid, or null when it is outside it. */
        locate(target) {
            const cell = target?.closest?.('td[data-column]')
            const rowEl = cell?.closest('tr[data-row-key]')

            if (! cell || ! rowEl) return null

            const col = grid.colOf(cell.dataset.column)
            const row = grid.rows().indexOf(rowEl)

            return (col === null || row === -1) ? null : { row, col }
        },

        /** The row nearest a viewport y — used while dragging past the last row. */
        rowAtY(clientY) {
            return rowAtY(this.rows(), clientY)
        },
    }

    return grid
}
