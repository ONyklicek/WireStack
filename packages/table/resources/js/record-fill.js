import wireFillHandle from './fill/controller'

/**
 * The Excel-style fill handle, as its own bundle.
 *
 * It used to ride along in `wire-core-dropdown.js`, which every consumer of
 * wire-core ships — so a forms-only application downloaded a table gesture it
 * could never trigger. Measured: 9,148 of that bundle's 38,365 bytes. Splitting
 * it out costs a table 86 bytes (the shared `editable/sync`, `autoscroll` and
 * `rows` modules are small enough that a second copy is noise) and saves
 * everyone else the whole 9 KB. See ADR 0025 § step 10.
 */
// ─── Self-registration ──────────────────────────────────────────
// `alpine:init` fires exactly once per document, so a bundle that only listens
// for it registers nothing when it arrives after a `wire:navigate`. Inside
// `wire-core-dropdown.js` this half came for free — that bundle has carried the
// pattern since it was written, and `wireFillHandle` was one line in its
// registrar. Split out, the line moved and the pattern did not: the bundle was
// delivered on the hop, the factory was never registered, and the whole data
// region died on `wireFillHandle is not defined`. Server-side tests see the
// script tag and call that delivered; verify-spa-navigate.mjs is what does not.
//
// The `registered` guard is load-bearing: the same src can be emitted twice.
let registered = false

const registerWireFillHandle = () => {
    if (registered || ! window.Alpine) return
    registered = true

    window.Alpine.data('wireFillHandle', wireFillHandle)
}

if (window.Alpine) {
    // Alpine already started (e.g. the script loaded after a Livewire navigation).
    registerWireFillHandle()
} else {
    document.addEventListener('alpine:init', registerWireFillHandle)
}
