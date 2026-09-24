/**
 * One request at a time per component, across its islands and its root.
 *
 * Livewire 4 coordinates overlapping requests per SCOPE: an action fired inside
 * an island has the island's scope, anything else the component's. Within one
 * scope it keeps state consistent — a new poll is thrown away while a request is
 * in flight, an in-flight poll is cancelled for a user action, anything else
 * waits (`coordinateNetworkInteractions()` in `js/request/interactions.js`).
 * Across scopes it does nothing, on purpose: "Component and island scopes
 * remain independent" (livewire/livewire#10513), and the islands page says what
 * follows from it — "both islands and the root component can mutate the same
 * component state. If multiple requests are in flight simultaneously, there can
 * be divergent state — the last response to return will win the state battle."
 *
 * A table is exactly that shape. Its rows are an island (`data-region`), its
 * modals another (`action-modals`), and its `wire:poll`, search box and filters
 * sit outside both. A page-size change or a sort fired inside the island while a
 * poll tick is in flight goes out beside it carrying the same snapshot, comes
 * back first, and is then overwritten by the tick's older snapshot: the select
 * jumps back and the rows never change. Every island write races the same way —
 * a cell save, an opened modal — and so does the search box against a sort still
 * on its way.
 *
 * So a component that declares its islands share state gets Livewire's own
 * rules applied across all of its scopes, and nothing more:
 *
 *   - a poll fired while another scope of the component is in flight is dropped;
 *   - an action fired while only polls are in flight cancels those polls;
 *   - anything else waits for the other scope to finish, then fires.
 *
 * Opt-in by a marker, because the independence is Livewire's documented
 * default and islands elsewhere in an application may rely on it:
 * `data-wire-islands="shared-state"` on any element the component itself owns
 * (not one inside a nested component). wire-table renders it on its wrapper.
 *
 * Runs after Livewire's own interceptor — that one is registered in a microtask
 * when Livewire loads, this one on `livewire:init` or later — so an action it
 * already cancelled or deferred within its scope is left to it.
 */

const MARKER = '[data-wire-islands="shared-state"]'

/** @type {WeakMap<object, Set<object>>} component -> messages in flight */
const inFlight = new WeakMap()

const scopeOf = (action) => action.metadata?.island?.name ?? null

const messageScope = (message) => {
    const actions = Array.from(message.actions ?? [])

    return actions.length && actions.every((action) => action.metadata?.island)
        ? actions.map(scopeOf).sort().join('|')
        : null
}

const isPoll = (action) => action.metadata?.type === 'poll'

const sharesState = (component) => {
    const root = component?.el

    if (! root?.querySelectorAll) return false

    if (root.matches?.(MARKER)) return true

    for (const el of root.querySelectorAll(MARKER)) {
        if (el.closest('[wire\\:id]') === root) return true
    }

    return false
}

let registered = false

const register = () => {
    if (registered || ! window.Livewire?.interceptMessage || ! window.Livewire?.interceptAction) return
    registered = true

    window.Livewire.interceptMessage(({ message, onFinish }) => {
        const component = message.component
        let messages = inFlight.get(component)

        if (! messages) inFlight.set(component, messages = new Set())

        messages.add(message)
        onFinish(() => messages.delete(message))
    })

    window.Livewire.interceptAction(({ action }) => {
        if (action.isCancelled?.() || action.isDeferred?.()) return
        if (action.isAsync?.()) return

        const scope = scopeOf(action)
        const others = Array.from(inFlight.get(action.component) ?? [])
            .filter((message) => ! message.isCancelled?.() && messageScope(message) !== scope)

        if (! others.length || ! sharesState(action.component)) return

        if (others.some((message) => message.isAsync?.())) return

        if (isPoll(action)) return action.cancel()

        const polls = others.filter((message) => Array.from(message.actions).every(isPoll))

        polls.forEach((message) => message.cancel())

        const pending = others.filter((message) => ! polls.includes(message))

        if (! pending.length) return

        action.defer()

        let waiting = pending.length

        pending.forEach((message) => message.addInterceptor(({ onFinish }) => {
            onFinish(() => {
                if (--waiting === 0 && ! action.isCancelled?.()) action.fire()
            })
        }))
    })
}

if (window.Livewire) register()
else document.addEventListener('livewire:init', register)
