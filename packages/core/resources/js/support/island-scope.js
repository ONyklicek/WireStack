/**
 * Keep an island fragment inside the component that rendered it.
 *
 * Livewire applies `effects.islandFragments` by searching the component's DOM
 * for the fragment marker with the same token — and the search does not stop at
 * a nested component. `supportIslands.js` calls `findFragment(component.el, …)`
 * without a `hasReachedBoundary`, and nothing in Livewire passes one. On top of
 * that `walkElements()` honours `stop()` only for the element that matched: its
 * siblings are still walked and a later match overwrites the first. Present in
 * every 4.x release (checked v4.0.0 through v4.4.6 and `main`).
 *
 * The token is not per component. It names the compiled island view, so every
 * component rendering the same Blade file carries the same token — a table's
 * `data-region` and `action-modals` on every table on the page. Put a table
 * component inside another table's page (an order's delivery notes inside the
 * order detail) and the parent's sort or page size lands in the child's grid:
 * the parent does not change, the child shows the parent's rows, and the rows,
 * now inside the wrong Alpine scope, throw `isSelected is not defined`. When the
 * island marker sits directly in the component root the first match wins and it
 * happens to work, which is why a flat repro does not show it.
 *
 * The fix cannot be server-side: Livewire resolves the compiled island view
 * from the token (`IslandCompiler::getCachedPathFromToken()`), so it has to stay
 * the view's token. Instead, for the length of one message, the markers of the
 * same tokens that belong to a NESTED component are renamed so Livewire's
 * `isStartFragmentMarker()` does not recognise them, and put back once the
 * message is done.
 *
 * Timing is what makes this safe. Livewire calls every interceptor's
 * `onSuccess` before any of them runs `onMorph`, and its own island renderer
 * works in `onMorph` — so the markers are hidden before it looks and restored
 * in `onFinish`, which fires after the morph and on cancel/error/failure too.
 *
 * Remove when Livewire scopes `renderIsland()` to the component boundary —
 * https://github.com/livewire/livewire/pull/10737.
 */

const START = '[if FRAGMENT'
const HIDDEN = '[if WIRE-NESTED-FRAGMENT'

const tokenOf = (html) => /token=([^|\]]+)/.exec(html)?.[1] ?? null

const ownerOf = (node) => node.parentElement?.closest('[wire\\:id]') ?? null

/**
 * The start markers inside `root` that carry one of `tokens` but belong to a
 * component nested in `root`, not to `root` itself.
 */
const nestedMarkers = (root, tokens) => {
    const found = []
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_COMMENT)

    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
        if (! node.data.startsWith(START)) continue
        if (! tokens.has(tokenOf(node.data))) continue
        if (ownerOf(node) === root) continue

        found.push(node)
    }

    return found
}

let registered = false

const register = () => {
    if (registered || ! window.Livewire?.interceptMessage) return
    registered = true

    window.Livewire.interceptMessage(({ message, onSuccess, onFinish }) => {
        let hidden = []

        const restore = () => {
            for (const node of hidden) {
                if (node.data.startsWith(HIDDEN)) node.data = START + node.data.slice(HIDDEN.length)
            }

            hidden = []
        }

        onSuccess(({ payload }) => {
            const fragments = payload?.effects?.islandFragments

            if (! fragments?.length) return

            const root = message.component.el
            const tokens = new Set(fragments.map(tokenOf).filter(Boolean))

            hidden = nestedMarkers(root, tokens)

            for (const node of hidden) node.data = HIDDEN + node.data.slice(START.length)
        })

        onFinish(restore)
    })
}

if (window.Livewire) register()
else document.addEventListener('livewire:init', register)
