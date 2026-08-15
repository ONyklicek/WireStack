import { isFillDragging } from '../fill/controller'

/**
 * Apply the regions a write chose to re-render.
 *
 * The client half of `InteractsWithPartials`. The server sends
 * `effects.wirePartials` — `{ 'row-42': '<tr …>' }` — and each one replaces the
 * element carrying `wire:partial="row-42"`. An anchor costs one HTML attribute:
 * nothing is registered, nothing enters the snapshot, and a table with 200 rows
 * pays 200 attributes rather than 200 anything-else. That is the whole reason
 * this exists where islands could not (an island's name cannot be per-record —
 * see `IslandSemanticsTest`).
 *
 * Three rules, each of them learned rather than designed:
 *
 *  - **never alongside a full render.** If the response also carries `html`,
 *    Livewire's own morph is authoritative and this would fight it.
 *  - **never over a drag.** The fill handle suppresses Livewire's morph through
 *    `morph.updating` while a drag is in flight; this path never reaches that
 *    hook, so it has to ask directly. Skipping the guard is what made a targeted
 *    fill wipe the cells it had just painted.
 *  - **morph, never replace.** Editable cells keep their own optimistic Alpine
 *    state and mark themselves `wire:ignore.self`; an `innerHTML =` would throw
 *    that away on every save, which is the bug this feature exists to avoid
 *    rather than cause.
 */
const isElement = (el) => typeof el?.hasAttribute === 'function'

const morphOptions = (component) => ({
    updating: (el, toEl, childrenOnly, skip) => {
        if (! isElement(el)) return

        // The same escape hatches Livewire's own morph honours. A cell that has
        // opted out of morphing has done so because it is holding state the
        // server does not have yet.
        if (el.__livewire_ignore === true) return skip()
        if (el.__livewire_ignore_self === true) childrenOnly()

        // A nested component keeps its own identity and its own update cycle.
        if (isElement(el) && el.hasAttribute('wire:id') && el.getAttribute('wire:id') !== component.id) {
            return skip()
        }
    },

    key: (el) => {
        if (! isElement(el)) return

        return el.getAttribute('wire:key') ?? el.getAttribute('wire:id') ?? el.id
    },

    lookahead: false,
})

document.addEventListener('livewire:init', () => {
    window.Livewire.interceptMessage(({ message, onSuccess }) => {
        onSuccess(({ payload }) => {
            const partials = payload.effects?.wirePartials

            if (! partials || payload.effects?.html) return
            if (isFillDragging()) return

            queueMicrotask(() => {
                for (const [name, html] of Object.entries(partials)) {
                    const anchors = Array.from(
                        message.component.el.querySelectorAll(`[wire\\:partial="${name}"]`),
                    )

                    if (anchors.length !== 1) {
                        // Zero: the row is not on this page any more, which is a
                        // normal outcome of a write, not an error. More than one
                        // is a name collision and worth saying out loud.
                        if (anchors.length > 1) {
                            console.error(`[wire] more than one element answers to partial "${name}"`)
                        }

                        continue
                    }

                    // A `<tr>` cannot be parsed inside a `<div>`, so the wrapper
                    // has to be whatever the anchor's own parent is.
                    const wrapper = document.createElement(
                        anchors[0].parentElement?.tagName?.toLowerCase() ?? 'div',
                    )

                    // Server-rendered markup from this component's own response —
                    // the same trust boundary Livewire's morph works across, and
                    // the reason it is parsed rather than sanitised: it IS the
                    // view.
                    wrapper.innerHTML = html

                    const incoming = wrapper.firstElementChild

                    if (! incoming) continue

                    window.Alpine.morph(anchors[0], incoming, morphOptions(message.component))
                }
            })
        })
    })
})
