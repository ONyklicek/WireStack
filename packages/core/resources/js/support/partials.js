/**
 * Whether a fill drag is in flight, and therefore whether anything else is
 * allowed to morph the rows.
 *
 * Read from the body class rather than imported, because the fill handle ships
 * in wire-table's own bundle (ADR 0025 § step 10) and the bundles are separate
 * IIFEs — this module cannot import across that line, and core must not learn
 * that downstream packages exist. `wire-filling` is not a convenience: the
 * controller sets and clears it on exactly the same two lines that add to and
 * remove from its own drag registry, the fill handle's CSS already depends on
 * it, and two browser drivers already assert it. Absent the table bundle the
 * class never appears, so the answer is `false` — which is the correct answer
 * when there is no fill handle on the page.
 */
const isFillDragging = () => document.body.classList.contains('wire-filling')


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
 *
 * And one thing this path owes the rest of the page: **say what it replaced.**
 * See `PARTIALS_APPLIED` below.
 */

/**
 * Dispatched on `document` after a batch of partials has been morphed in, with
 * `detail.elements` listing the anchors that were replaced.
 *
 * Client-side markup that is not in the server's render is invisible to this
 * path and gets silently destroyed by it. wire-sortable's drag handle is the
 * case that proved it: the handle `<td>` is created in the browser and prepended
 * to every `<tr>`, and it is rebuilt from Livewire's `morph.updated` hook — which
 * this path never reaches, because it drives `Alpine.morph()` itself. A row
 * partial therefore replaced a three-cell row with the server's two-cell one and
 * left the row undraggable, with no error anywhere.
 *
 * Firing Livewire's own `morph.updating` / `morph.updated` instead was the
 * obvious fix and is the wrong one. Those listeners are written for a
 * whole-component morph: wire-sortable's `morph.updating` guard `skip()`s the
 * cell the user is typing in, which is right when the whole table is being
 * re-rendered around it and exactly backwards for a partial, whose entire
 * purpose is to carry that cell's saved value back. An announcement lets a
 * listener repair what it owns without giving it a veto over a targeted write.
 *
 * A DOM event rather than an exported function because the bundles are separate
 * IIFEs — wire-sortable cannot import from wire-core at runtime — and because
 * core must not learn that downstream packages exist.
 */
const PARTIALS_APPLIED = 'wire:partials-applied'

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
                const applied = []

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

                    // Alpine.morph patches the anchor in place, so the node
                    // reference survives and is what a listener has to repair.
                    window.Alpine.morph(anchors[0], incoming, morphOptions(message.component))
                    applied.push(anchors[0])
                }

                if (applied.length === 0) return

                document.dispatchEvent(new CustomEvent(PARTIALS_APPLIED, {
                    detail: { component: message.component, elements: applied },
                }))
            })
        })
    })
})
