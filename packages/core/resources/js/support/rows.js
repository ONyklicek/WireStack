/**
 * Row geometry helpers shared by every drag-over-rows gesture (the fill
 * handle, the table selection sweep). Promoted out of fill/grid.js — the
 * grid keeps its richer cell/column view and delegates the row arithmetic
 * here.
 */

/** Direct-child rows only: group headers and sub-row rows carry no data-row-key,
 *  and a sub-row's own <table> has its own tbody, so it is never reached here. */
export const bodyRows = (table) => {
    const tbody = table?.tBodies?.[0]

    if (! tbody) return []

    return Array.from(tbody.children).filter((el) => el.matches('tr[data-row-key]'))
}

/** The index of the row nearest a viewport y — used while dragging past the
 *  first or last row, so the gesture keeps tracking instead of going dead. */
export const rowAtY = (rows, clientY) => {
    if (rows.length === 0) return null

    let nearest = 0

    for (let i = 0; i < rows.length; i++) {
        const rect = rows[i].getBoundingClientRect()

        if (clientY >= rect.top && clientY <= rect.bottom) return i
        if (clientY > rect.bottom) nearest = i
    }

    return clientY < rows[0].getBoundingClientRect().top ? 0 : nearest
}
