/**
 * wire-table-sticky — keeps a table's column headers in view while the page scrolls.
 *
 * CSS `position: sticky` pins an element to its nearest scrolling ancestor, and
 * the table's wrapper is one: it carries `overflow-x: auto` for a table wider
 * than the page, and CSS computes the other axis to `auto` alongside it. That
 * wrapper never scrolls vertically, so a sticky `<thead>` inside it never moves
 * — and dropping the wrapper's overflow would give up horizontal scrolling. So a
 * `<thead data-wire-sticky-head>` is moved instead: translated down by however
 * far the top of its table has gone past the pin line, and never further than
 * the table's own bottom. The translation stays inside the wrapper, so the
 * horizontal scroll still carries the header with the columns.
 *
 * The pin line is the top of whatever scrolls the table vertically — the window,
 * or a scrolling ancestor such as a modal body — plus `--wire-sticky-top`, the
 * height of any sticky chrome laid over that top (the admin top bar declares
 * it). It is read back through `scroll-margin-top`, which the assets partial
 * sets from the variable, because a computed `scroll-margin-top` is a pixel
 * value whatever unit the variable was written in.
 *
 * Against the window the movement itself is CSS, not script: a scroll-driven
 * animation (`animation-timeline: scroll(root)`) translates the header over an
 * `animation-range` this file computes — where the table's top meets the pin
 * line, and how far the header may travel. The browser runs it on the
 * compositor, in the same frame as the scroll. Writing the transform from a
 * `scroll` listener instead trails the compositor's scroll by a frame, and the
 * header visibly shudders behind the rows; so script positioning is only the
 * fallback, for an engine without scroll timelines and for a table inside a
 * scrolling ancestor rather than the page. The range only changes when the
 * layout does, which is what the ResizeObserver and the morph hooks are for.
 *
 * A morph rewrites the `<thead>`'s `style` back to what the server rendered,
 * which drops the translation, so a morph that touches one is followed by a sync
 * before the next paint. That is `morph.updated`, not `morphed`: the data region
 * is an island, and an island morph never fires `morphed` — the header would
 * sit where the server put it until the next scroll. Import-free on purpose: the
 * assets partial inlines this file verbatim when the bundle is missing.
 */
(() => {
    if (window.__wireTableSticky) return
    window.__wireTableSticky = true

    const SELECTOR = 'thead[data-wire-sticky-head]'
    const scrollParents = new WeakMap()

    // The nearest ancestor that scrolls vertically, past the table's own
    // wrapper (which only ever scrolls sideways), or null for the window.
    const scrollParent = (head) => {
        if (scrollParents.has(head)) return scrollParents.get(head)

        let found = null

        for (let el = head.closest('.wire-scroller')?.parentElement; el && el !== document.body && el !== document.documentElement; el = el.parentElement) {
            const overflow = getComputedStyle(el).overflowY

            if (overflow === 'auto' || overflow === 'scroll' || overflow === 'overlay') {
                found = el
                break
            }
        }

        scrollParents.set(head, found)

        return found
    }

    const timelines = CSS.supports?.('animation-timeline: scroll()') ?? false

    const write = (head, name, value) => {
        if (head.style.getPropertyValue(name) !== value) head.style.setProperty(name, value)
    }

    const place = (head) => {
        const table = head.closest('table')

        if (! table) return

        const parent = scrollParent(head)
        const chrome = parseFloat(getComputedStyle(head).scrollMarginTop) || 0
        const line = (parent ? parent.getBoundingClientRect().top + parent.clientTop : 0) + chrome
        const box = table.getBoundingClientRect()
        const room = Math.max(0, box.height - head.offsetHeight)
        const shift = Math.min(Math.max(0, line - box.top), room)

        head.toggleAttribute('data-pinned', shift > 0)

        if (timelines && ! parent) {
            // The scroll offset at which the table's top reaches the pin line,
            // in page coordinates — the same at every scroll position, so on a
            // scroll these writes are no-ops.
            const start = Math.round(box.top + window.scrollY - chrome)

            head.setAttribute('data-wire-sticky-timeline', '')
            write(head, '--wire-sticky-start', `${start}px`)
            write(head, '--wire-sticky-end', `${start + Math.round(room)}px`)
            write(head, '--wire-sticky-travel', `${Math.round(room)}px`)
            if (head.style.transform) head.style.transform = ''

            return
        }

        head.removeAttribute('data-wire-sticky-timeline')

        const transform = shift > 0 ? `translateY(${shift}px)` : ''

        if (head.style.transform !== transform) head.style.transform = transform
    }

    // The range moves whenever anything above the table, or the table itself,
    // changes height — an image loading, a row expanding, a notice closing.
    const resized = typeof ResizeObserver === 'function' ? new ResizeObserver(() => schedule()) : null
    const observed = new WeakSet()

    const observe = (head) => {
        const table = head.closest('table')

        if (! resized || ! table || observed.has(table)) return

        observed.add(table)
        resized.observe(table)
    }

    const sync = () => document.querySelectorAll(SELECTOR).forEach((head) => {
        observe(head)
        place(head)
    })

    let queued = false

    const schedule = () => {
        if (queued) return
        queued = true
        requestAnimationFrame(() => {
            queued = false
            sync()
        })
    }

    // Capture, so a scrolling ancestor's scroll is heard too — `scroll` does not bubble.
    document.addEventListener('scroll', schedule, { capture: true, passive: true })
    window.addEventListener('resize', schedule, { passive: true })
    document.addEventListener('livewire:navigated', sync)
    document.addEventListener('wire:partials-applied', sync)

    const hook = () => {
        window.Livewire?.hook('morph.updated', ({ el }) => {
            if (el.matches?.(SELECTOR)) schedule()
        })
        // A response can change the table's height without touching the header
        // (rows added or removed), which moves where the header has to stop.
        window.Livewire?.hook('commit', ({ succeed }) => succeed(schedule))
    }

    if (window.Livewire) hook()
    else document.addEventListener('livewire:init', hook)

    resized?.observe(document.documentElement)

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', sync)
    else sync()
})()
