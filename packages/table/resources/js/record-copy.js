/*
 * wireTableCopy — one delegated clipboard controller for the whole document.
 *
 * The copy affordance used to be an Alpine component per cell: an `x-data`, an
 * inline multi-line `x-on:click`, two `<template x-if>` icons and a feedback span
 * carrying six transition attributes. Measured at **2042 bytes and 11 whitespace
 * text nodes per cell** — a 500-cell table shipped a megabyte of it, and Livewire's
 * morph walked every one of those nodes on every commit. The render-cost model
 * forbids exactly that shape: a cell carries data, behaviour is bound once.
 *
 * So a copyable cell is now a plain `<button data-copy="…">` and this file owns the
 * click, the clipboard and the feedback for every such button on the page —
 * including ones morphed in by a later commit, because a delegated listener on the
 * document needs no re-binding and no per-row teardown.
 *
 * Deliberately NOT an Alpine component. It needs neither `$wire` nor per-table
 * config, and staying off `Alpine.data` also keeps it clear of the registration
 * race ADR 0024 documents: a document listener is installed when the script runs,
 * so it cannot subscribe to an `alpine:init` that already fired.
 *
 * The feedback element is rendered once per table by `copy-assets.blade.php` rather
 * than built here, and that is load-bearing rather than stylistic: a consumer's
 * Tailwind scans `resources/views` and `src` (see docs/getting-started.md), never
 * `dist` — so a utility class that existed only inside this bundle would never be
 * generated in their CSS, and the pill would render unstyled.
 */

const FEEDBACK_MS = 2000

let hideTimer = null

/**
 * Put the value on the clipboard, reporting whether it actually landed.
 *
 * `navigator.clipboard` is undefined outside a secure context and `writeText` can
 * be refused by permissions policy, so both shapes of failure are caught here. The
 * feedback is then withheld rather than shown over a copy that did not happen —
 * the previous inline handler announced "copied" unconditionally and left the
 * rejection unhandled on the console.
 */
async function writeToClipboard(value) {
    if (! navigator.clipboard?.writeText) return false

    try {
        await navigator.clipboard.writeText(value)

        return true
    } catch {
        return false
    }
}

/**
 * Show the table's single feedback pill beside the button that was pressed.
 *
 * One element for the page, moved to the button rather than one per cell — the
 * whole point of the exercise. It is positioned in viewport coordinates
 * (`position: fixed` in the partial), so it needs no positioned ancestor and
 * cannot be clipped by the table's own horizontal scroller.
 */
function showFeedback(button) {
    const pill = document.querySelector('[data-copy-feedback]')
    if (! pill) return

    const label = pill.querySelector('[data-copy-feedback-text]')
    if (label) label.textContent = button.getAttribute('data-copy-message') ?? ''

    const rect = button.getBoundingClientRect()
    pill.style.left = `${rect.right + 6}px`
    pill.style.top = `${rect.top + rect.height / 2}px`
    pill.hidden = false

    // A second copy while the first pill is still up re-aims it and restarts the
    // clock, instead of letting the earlier timer hide it mid-announcement.
    clearTimeout(hideTimer)
    hideTimer = setTimeout(() => { pill.hidden = true }, FEEDBACK_MS)
}

function onClick(event) {
    const button = event.target.closest('[data-copy]')
    if (! button) return

    // The button already stops the row's record action on its own —
    // record-actions.js treats every `button` as interactive — so this only keeps
    // the press from submitting a form the table happens to be rendered inside.
    event.preventDefault()

    writeToClipboard(button.getAttribute('data-copy') ?? '')
        .then((copied) => { if (copied) showFeedback(button) })
}

// ─── Self-installation ──────────────────────────────────────────
// Guarded on `window`, not on a module-local flag: when the compiled bundle is
// absent the partial can inline this source, and two IIFE copies in one document
// would each hold their own flag and bind a second listener — so every click
// would be copied, and announced, twice.
if (! window.wireTableCopyInstalled) {
    window.wireTableCopyInstalled = true
    document.addEventListener('click', onClick)
}
