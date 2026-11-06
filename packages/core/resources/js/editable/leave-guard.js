/**
 * Leaving a page while an inline edit is still on its way.
 *
 * A write that has reached the server finishes there whatever the browser does
 * next — the host keeps running after the connection closes (the cell endpoints
 * also say so with `ignore_user_abort()`). What does not survive leaving is
 * what never left the browser:
 *
 *  - a write queued behind the record's previous one (editable/lanes.js) or
 *    held back by Livewire behind another request of the component;
 *  - text typed into a cell and never committed — the cell saves on blur and
 *    on Enter, and closing the tab is neither;
 *  - a refused write (invalid value, failed request) still showing in the cell.
 *
 * Two ways out, two answers, the same split the forms' `wireUnsavedChanges`
 * makes:
 *
 *  - **`wire:navigate`** — nothing has to be lost, so nothing is asked: the
 *    navigation is held, uncommitted text is committed, every write is waited
 *    for, and the navigation then carries on by itself. Only an edit that is
 *    still unsaved after that (refused, invalid) asks before it is dropped.
 *  - **the browser's own way out** (reload, closed tab, typed URL, an ordinary
 *    link) — the page cannot be held, so uncommitted text is committed on the
 *    spot (it lands if the user stays) and the browser's own prompt is raised
 *    while anything is unsaved or still being written.
 *
 * One listener pair for the whole page, however many cells: cells register on
 * init and leave on destroy, and the listeners are installed once, guarded on
 * `window` because the bundle can run twice.
 */

const GUARD = '__wireEditableLeaveGuard'
const FALLBACK = 'Some changes have not been saved yet. Leave anyway?'

/**
 * Live editable cell components -> their root element. On `window`, like the
 * install guard: the bundle can run twice, and the listeners installed by the
 * first run must see cells registered through the second.
 *
 * @type {Map<object, Element>}
 */
const cells = (window.__wireEditableCells ??= new Map())

const live = () => [...cells].filter(([, el]) => el.isConnected).map(([cell]) => cell)

/** Cells holding a value the server does not have and is not being sent. */
const unsaved = () => live().filter((cell) => ! cell.saving && cell.dirty)

const writing = () => live().filter((cell) => cell.saving)

/** Start writing what was typed and never committed. A refused value is left alone. */
const commitTyped = () => unsaved().filter((cell) => ! cell.error).forEach((cell) => cell.save())

const message = () => document.querySelector('[data-msg-unsaved-edits]')?.dataset.msgUnsavedEdits || FALLBACK

let holding = false

const onBeforeUnload = (event) => {
    commitTyped()

    if (writing().length === 0 && unsaved().length === 0) return

    event.preventDefault()
    // Older browsers show their prompt only when this is set.
    event.returnValue = ''
}

const onNavigate = (event) => {
    if (writing().length === 0 && unsaved().length === 0) return

    event.preventDefault()
    if (holding) return
    holding = true

    const url = event.detail?.url?.href ?? event.detail?.url ?? null

    commitTyped()
    Promise.all(writing().map((cell) => cell._writing).filter(Boolean))
        .catch(() => {})
        .then(() => {
            holding = false
            if (unsaved().length > 0 && ! window.confirm(message())) return

            // Our own listener sees the navigation again; nothing is pending
            // any more unless the user chose to drop it, so mark that.
            unsaved().forEach((cell) => { cell.value = cell.serverValue })
            if (url) window.Livewire?.navigate(String(url))
        })
}

const install = () => {
    if (window[GUARD]) return
    window[GUARD] = true

    window.addEventListener('beforeunload', onBeforeUnload)
    document.addEventListener('livewire:navigate', onNavigate)
}

/** Register an editable cell component; returns the function that unregisters it. */
export const guardCell = (cell, el) => {
    install()
    cells.set(cell, el)

    return () => cells.delete(cell)
}
