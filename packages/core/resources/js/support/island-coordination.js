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
 * What is tracked is the ACTION, from the moment this sees it until its
 * `onFinish` (which also fires on cancel, skip and error) — not the message.
 * Livewire attaches message interceptors only when a request is sent, so a
 * message is invisible between being created and leaving: 5 ms in the buffer,
 * and much longer for an action held back behind another one. A tick landing
 * in that window saw nothing in flight and went out beside it — found by an
 * application where an event dispatched on load held the page-size change back,
 * and the tick slipped in the moment it was released.
 *
 * Runs after Livewire's own interceptor — that one is registered in a microtask
 * when Livewire loads, this one on `livewire:init` or later. An action Livewire
 * already cancelled is ignored; one it deferred within its own scope is tracked
 * but left to it.
 *
 * Registered once per page even though the bundle may execute twice (the
 * directive and a surface's fallback partial can both emit it): the guard is on
 * `window`, because each execution of an IIFE has a scope of its own.
 */

const MARKER = '[data-wire-islands="shared-state"]'
const GUARD = '__wireIslandCoordination'

/** @type {WeakMap<object, Set<object>>} component -> actions not yet finished */
const outstanding = new WeakMap()

const scopeOf = (action) => action.metadata?.island?.name ?? null

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

const track = (action, onFinish) => {
    let actions = outstanding.get(action.component)

    if (! actions) outstanding.set(action.component, actions = new Set())

    actions.add(action)
    onFinish(() => actions.delete(action))
}

const register = () => {
    if (window[GUARD] || ! window.Livewire?.interceptAction) return
    window[GUARD] = true

    window.Livewire.interceptAction(({ action, onFinish }) => {
        if (action.isCancelled?.()) return

        const others = Array.from(outstanding.get(action.component) ?? [])
            .filter((other) => ! other.isCancelled?.() && scopeOf(other) !== scopeOf(action))

        track(action, onFinish)

        if (action.isDeferred?.() || action.isAsync?.()) return
        if (! others.length || ! sharesState(action.component)) return
        if (others.some((other) => other.isAsync?.())) return

        if (isPoll(action)) return action.cancel()

        others.filter(isPoll).forEach((poll) => poll.cancel())

        const waitFor = others.filter((other) => ! isPoll(other))

        if (! waitFor.length) return

        action.defer()

        let waiting = waitFor.length

        waitFor.forEach((other) => other.addInterceptor(({ onFinish: settled }) => {
            settled(() => {
                if (--waiting === 0 && ! action.isCancelled?.()) action.fire()
            })
        }))
    })
}

if (window.Livewire) register()
else document.addEventListener('livewire:init', register)
