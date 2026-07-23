/**
 * Scroll the page while the pointer is dragged near the viewport edge.
 *
 * Written from scratch: the only auto-scroll in the repo is SortableJS's own
 * plugin, which is not reachable from a non-Sortable drag.
 *
 * Runs on rAF rather than on pointermove, so it keeps scrolling when the pointer
 * is held still past the edge — which is the whole point of the affordance.
 */

const EDGE = 60   // px from the viewport edge where scrolling starts
const MAX_SPEED = 22  // px per frame at the very edge

/** The nearest ancestor that can actually scroll vertically, else the window. */
const scrollParent = (el) => {
    let node = el?.parentElement

    while (node) {
        const style = getComputedStyle(node)
        const scrollable = /(auto|scroll|overlay)/.test(style.overflowY)

        if (scrollable && node.scrollHeight > node.clientHeight) return node

        node = node.parentElement
    }

    return null
}

export const createAutoScroller = (el) => {
    let frame = null
    let pointerY = 0
    let container = null

    const viewport = () => container
        ? container.getBoundingClientRect()
        : { top: 0, bottom: window.innerHeight }

    const scrollBy = (dy) => container
        ? (container.scrollTop += dy)
        : window.scrollBy(0, dy)

    const tick = () => {
        const { top, bottom } = viewport()
        const overTop = top + EDGE - pointerY
        const overBottom = pointerY - (bottom - EDGE)

        if (overTop > 0) {
            scrollBy(-Math.min(MAX_SPEED, (overTop / EDGE) * MAX_SPEED))
        } else if (overBottom > 0) {
            scrollBy(Math.min(MAX_SPEED, (overBottom / EDGE) * MAX_SPEED))
        }

        frame = requestAnimationFrame(tick)
    }

    return {
        start() {
            container = scrollParent(el)

            if (frame === null) frame = requestAnimationFrame(tick)
        },

        update(clientY) {
            pointerY = clientY
        },

        stop() {
            if (frame !== null) cancelAnimationFrame(frame)

            frame = null
        },
    }
}
