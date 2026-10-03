/**
 * wire-table-viewport — tells the server which half of a stacked table to send.
 *
 * A table stacked on mobile is a `<table>` and a card per record, one of which
 * CSS hides. The server cannot see the window, so this writes the largest
 * breakpoint it reaches into the `wire_viewport` cookie (`base`, `sm` … `2xl`);
 * `ClientViewport` reads it back and the table emits only the half shown here.
 *
 * Every rendered table that trims leaves a marker in its data region:
 * `data-wire-layout` (`table`, `cards`, or `both` when the request had no cookie)
 * and `data-wire-layout-query` (its own stacking breakpoint as a media query).
 * When the window crosses a breakpoint, a table holding the wrong half — or both,
 * one of which the server has stopped refreshing — is re-rendered once. On load
 * only the wrong half counts: `both` is a complete page, and refreshing it would
 * cost a request on every first visit to trim markup nobody sees.
 *
 * The ladder mirrors `Breakpoint::minWidth()` (Tailwind v4's defaults, in rem so
 * matchMedia agrees with the `md:` utilities whatever the browser font size);
 * `ClientViewportTest` fails when the two drift. Import-free on purpose: the
 * assets partial inlines this file verbatim when the bundle is missing.
 */
(() => {
    if (window.__wireTableViewport) return
    window.__wireTableViewport = true

    const COOKIE = 'wire_viewport'
    const LADDER = [['sm', '40rem'], ['md', '48rem'], ['lg', '64rem'], ['xl', '80rem'], ['2xl', '96rem']]
    const queries = LADDER.map(([name, width]) => [name, window.matchMedia(`(min-width: ${width})`)])

    const current = () => queries.reduce((reached, [name, query]) => (query.matches ? name : reached), 'base')

    const stored = () => document.cookie
        .split('; ')
        .find((pair) => pair.startsWith(COOKIE + '='))
        ?.slice(COOKIE.length + 1)

    const sync = (refreshBoth) => {
        const value = current()

        if (stored() !== value) {
            document.cookie = `${COOKIE}=${value}; path=/; max-age=31536000; SameSite=Lax`
        }

        const refreshed = new Set()

        document.querySelectorAll('[data-wire-layout]').forEach((marker) => {
            const held = marker.dataset.wireLayout
            const needed = window.matchMedia(marker.dataset.wireLayoutQuery).matches ? 'table' : 'cards'

            if (held === needed || (held === 'both' && ! refreshBoth)) return

            const id = marker.closest('[wire\\:id]')?.getAttribute('wire:id')

            if (! id || refreshed.has(id)) return

            refreshed.add(id)
            window.Livewire?.find(id)?.$refresh()
        })
    }

    sync(false)
    queries.forEach(([, query]) => query.addEventListener('change', () => sync(true)))
    // A page rendered from a stale cookie (a tablet turned since) is put right
    // once Livewire can address its components, and again after each navigation.
    document.addEventListener('livewire:initialized', () => sync(false))
    document.addEventListener('livewire:navigated', () => sync(false))
})()
