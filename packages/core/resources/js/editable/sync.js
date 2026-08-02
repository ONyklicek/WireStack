/**
 * The server→client channel of an inline-editable cell.
 *
 * Every editable surface — a table's text/select/toggle column, a panel entry —
 * mounts `wireEditableCell` on a root carrying `wire:ignore.self`, so a Livewire
 * morph cannot stomp the optimistic state it holds mid-edit. Livewire honours
 * that by leaving the element's OWN attributes alone (`childrenOnly()` in its
 * morph hook) while still morphing its children. So the two things the server
 * has to keep telling the cell — the value it now holds, and the optimistic-lock
 * version to send with the next write — ride on a child node instead, rendered
 * by `wire-core::partials.cell-sync`.
 *
 * They used to sit on the ignored root, which made an editable cell write-only:
 * whatever the FIRST render put there stood for the lifetime of the page, so no
 * re-render, poll tick or modal write could ever put a newer value on screen,
 * and the version the cell kept sending was the one the page loaded with — the
 * user's own next edit came back refused as somebody else's.
 *
 * One module, because three readers have to agree on where the channel is: the
 * cell component's MutationObserver, the fill handle's grid, and the fill
 * controller that writes results back into it.
 */

/**
 * The node carrying `data-server-value` / `data-record-version` for a cell root.
 *
 * Falls back to the root itself, which is not dead code: a surface outside this
 * repo may still render the attributes the old way, and a cell with no sync node
 * at all should read `undefined` rather than throw.
 */
export const syncNodeOf = (el) => el?.querySelector?.('[data-cell-sync]') ?? el ?? null

/** The value the server last rendered for this cell, as a string. */
export const serverValueOf = (el) => syncNodeOf(el)?.dataset?.serverValue ?? ''

/**
 * The live optimistic-lock version of a cell.
 *
 * Component state first: between two renders the cell's own commits move the
 * version and the DOM has not caught up yet. The sync node is the fallback for a
 * cell that has no component — a plain, non-editable cell the fill handle passes
 * over.
 */
export const versionOf = (el) => {
    try {
        return (window.Alpine.$data(el)?.recordVersion ?? syncNodeOf(el)?.dataset?.recordVersion) || null
    } catch (e) {
        return syncNodeOf(el)?.dataset?.recordVersion || null
    }
}
