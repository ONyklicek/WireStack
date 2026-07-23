/**
 * The dragged selection: an anchor cell and the cell the pointer is over.
 *
 * Stored as two {row, col} points rather than a row span, because that is what
 * makes horizontal and rectangular fill a change of one clamp rather than a
 * rewrite. v1 pins the focus to the anchor's column via `clampToColumn`.
 */

export const makeRange = (anchor, focus = anchor) => ({ anchor, focus })

/** v1: vertical only. Drop this call and the range is already rectangular. */
export const clampToColumn = (range) => ({
    anchor: range.anchor,
    focus: { row: range.focus.row, col: range.anchor.col },
})

export const bounds = (range) => ({
    top: Math.min(range.anchor.row, range.focus.row),
    bottom: Math.max(range.anchor.row, range.focus.row),
    left: Math.min(range.anchor.col, range.focus.col),
    right: Math.max(range.anchor.col, range.focus.col),
})

export const isEmpty = (range) =>
    range.anchor.row === range.focus.row && range.anchor.col === range.focus.col

/**
 * Every cell the range covers except the anchor itself — the anchor holds the
 * value being copied, so writing it back would be a no-op that still costs a row
 * in the request and a version to reconcile.
 */
export const targets = (range) => {
    const { top, bottom, left, right } = bounds(range)
    const cells = []

    for (let row = top; row <= bottom; row++) {
        for (let col = left; col <= right; col++) {
            if (row === range.anchor.row && col === range.anchor.col) continue

            cells.push({ row, col })
        }
    }

    return cells
}
