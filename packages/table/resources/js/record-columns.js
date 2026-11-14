/**
 * wire-table-columns — a column the user drags wider, and columns pinned to the
 * start of the table.
 *
 * **Resizing.** A header carrying `[data-column-resize]` (Table::resizableColumns())
 * is dragged by that grip: the width follows the pointer on the `<th>` itself, so
 * nothing round-trips while the user drags, and on release the one value goes to
 * the server (`resizeTableColumn(name, px)`), which keeps it in the table state —
 * the remembered layout and saved views carry it from there. A double click puts
 * the column back to its own width. Keyboard: the grip is focusable, and ←/→
 * move it by 16px.
 *
 * **Pinning.** Every cell marked `[data-pinned]` is `position: sticky`; what CSS
 * cannot know is how far from the start each one sits, because that is the sum of
 * the widths of the pinned cells before it — the selection checkbox, then each
 * pinned column. So the offsets are measured from the header row and written as
 * `left` onto every cell of that column, after load, after every morph and
 * whenever the table changes size.
 *
 * Delegated and import-free, like wire-table-sticky.js: one listener for the
 * document, nothing per table to initialise or forget.
 */
(() => {
    if (window.__wireTableColumns) return
    window.__wireTableColumns = true

    const MIN = 48
    const MAX = 1200

    // ── Pinned offsets ─────────────────────────────────────────────────

    // The selection checkbox column is compiled once per table and knows
    // nothing of pins, so it is made to stick here: a pinned column that slid
    // over the checkboxes would leave rows nobody can tick while scrolled.
    const SELECT_STICKY = ['sticky', 'z-[1]', 'bg-white', 'dark:bg-gray-800']

    const pinSelection = (table) => {
        const head = table.querySelector(':scope > thead > tr > th:has([data-testid=table-select-all])')
        const cells = table.querySelectorAll(':scope > tbody > tr > td[data-select-cell]')

        if (! head && cells.length === 0) return 0

        for (const el of [head, ...cells]) {
            if (! el) continue
            el.classList.add(...SELECT_STICKY)
            el.style.left = '0px'
        }

        // The header row has its own tint; the checkbox header takes it from
        // the pinned header beside it rather than the body's white.
        const tinted = table.querySelector(':scope > thead > tr > th[data-pinned]')
        if (head && tinted) head.style.backgroundColor = getComputedStyle(tinted).backgroundColor

        return head ? head.getBoundingClientRect().width : 0
    }

    const layout = (table) => {
        const head = table.querySelector(':scope > thead > tr')
        if (! head) return
        if (! head.querySelector(':scope > th[data-pinned]')) return

        let left = pinSelection(table)
        const offsets = new Map()

        // Floored: widths are fractional, and an offset half a pixel too far
        // leaves a hairline of the scrolling columns showing between two
        // pinned ones. Overlapping by a fraction shows nothing.
        for (const th of head.querySelectorAll(':scope > th[data-pinned]')) {
            const at = Math.floor(left)
            offsets.set(th.dataset.pinned, at)
            th.style.left = `${at}px`
            left += th.getBoundingClientRect().width
        }

        if (offsets.size === 0) return

        for (const td of table.querySelectorAll(':scope > tbody > tr > td[data-pinned]')) {
            const at = offsets.get(td.dataset.pinned)

            if (at !== undefined) td.style.left = `${at}px`
        }
    }

    const layoutAll = () => {
        for (const table of document.querySelectorAll('table[data-wire-pinned]')) layout(table)
    }

    const observer = typeof ResizeObserver === 'function'
        ? new ResizeObserver((entries) => entries.forEach((entry) => layout(entry.target)))
        : null

    const watch = () => {
        layoutAll()
        document.querySelectorAll('table[data-wire-pinned]').forEach((table) => observer?.observe(table))
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', watch)
    } else {
        watch()
    }

    document.addEventListener('livewire:navigated', watch)
    document.addEventListener('livewire:init', () => {
        // A morph rewrites a cell's style to what the server rendered, which
        // drops the offset — so measure again before the next paint.
        window.Livewire?.hook('morph.updated', () => requestAnimationFrame(watch))
    })

    // ── Resizing ───────────────────────────────────────────────────────

    const wireOf = (el) => {
        const id = el.closest('[wire\\:id]')?.getAttribute('wire:id')

        return id ? window.Livewire?.find(id) : null
    }

    const commit = (th, px) => {
        const name = th.dataset.column

        th.style.width = px === null ? '' : `${px}px`
        th.style.minWidth = px === null ? '' : `${px}px`
        wireOf(th)?.call('resizeTableColumn', name, px)
        layout(th.closest('table'))
    }

    const clamp = (px) => Math.max(MIN, Math.min(MAX, Math.round(px)))

    document.addEventListener('pointerdown', (event) => {
        const grip = event.target.closest?.('[data-column-resize]')
        if (! grip || event.button !== 0) return

        const th = grip.closest('th')
        if (! th) return

        event.preventDefault()
        event.stopPropagation()

        const startX = event.clientX
        const startWidth = th.getBoundingClientRect().width
        const rtl = getComputedStyle(th).direction === 'rtl'
        let width = startWidth

        grip.setPointerCapture?.(event.pointerId)
        document.documentElement.classList.add('wire-column-resizing')

        const move = (e) => {
            width = clamp(startWidth + (rtl ? startX - e.clientX : e.clientX - startX))
            th.style.width = `${width}px`
            th.style.minWidth = `${width}px`
            layout(th.closest('table'))
        }

        const up = () => {
            grip.removeEventListener('pointermove', move)
            grip.removeEventListener('pointerup', up)
            grip.removeEventListener('pointercancel', up)
            document.documentElement.classList.remove('wire-column-resizing')

            if (Math.abs(width - startWidth) >= 2) commit(th, width)
        }

        grip.addEventListener('pointermove', move)
        grip.addEventListener('pointerup', up)
        grip.addEventListener('pointercancel', up)
    })

    // A click on the grip must not sort the column it sits in.
    document.addEventListener('click', (event) => {
        if (event.target.closest?.('[data-column-resize]')) {
            event.preventDefault()
            event.stopPropagation()
        }
    }, true)

    document.addEventListener('dblclick', (event) => {
        const grip = event.target.closest?.('[data-column-resize]')
        if (! grip) return

        event.preventDefault()
        commit(grip.closest('th'), null)
    })

    document.addEventListener('keydown', (event) => {
        const grip = event.target.closest?.('[data-column-resize]')
        if (! grip || (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight')) return

        event.preventDefault()

        const th = grip.closest('th')
        const step = event.key === 'ArrowRight' ? 16 : -16

        commit(th, clamp(th.getBoundingClientRect().width + step))
    })
})()
