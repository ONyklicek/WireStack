/**
 * Route a programmatic Livewire call at an island.
 *
 * Livewire targets an island automatically, but only for an action fired FROM a
 * DOM element — `supportIslands.js` reads `action.origin` and returns straight
 * away when there is none:
 *
 *     let origin = action.origin
 *     if (! origin) return
 *
 * Everything these controllers do is a `$wire.method()` call from Alpine, which
 * has no origin. So a click on a sort header inside the table re-renders the
 * table alone, while the cell save next to it re-renders the whole component —
 * the one that happens far more often, and the one that matters on a wide grid.
 * Measured on the editable preview: 58,726 B against 42,374 B for the same
 * write.
 *
 * `$island(name)` sets the metadata for the next action and hands `$wire` back,
 * so the call reads normally. Passing no name is the default and changes
 * nothing, which is what surfaces with no island of their own want — an editable
 * panel entry uses the same controller.
 *
 * Naming an island the page does not have is harmless rather than fatal: the
 * server finds nothing under that name and falls through to an ordinary render.
 * Worth knowing, but not worth relying on — pass the name only where the island
 * is.
 *
 * **Not for a controller that guards itself against morphs.** The fill handle
 * suppresses rendering while a drag is in flight through Livewire's
 * `morph.updating` hook, and an island fragment is morphed by `morphFragment`,
 * which does not go through it — so a targeted fill wipes the cells it just
 * painted. It reconciles from the response payload and wants no render at all.
 * Learned from `verify-spa-navigate.mjs`, which was the only thing that saw it.
 */
export const targeting = ($wire, island) => (
    island && typeof $wire?.$island === 'function' ? $wire.$island(island) : $wire
)
