# ADR 0024: JavaScript Asset Delivery and Alpine Registration

## Status

ACCEPTED — implemented 2026-07-29.

Supersedes [ADR 0002](0002-js-alpine-distribution.md), which is falsified on
every clause it asserts.

## Context

ADR 0002 recorded that "no custom JavaScript files are shipped with any package",
that there is "zero build step for JS. No npm dependencies", and that there is
"no shared JS bundle or asset pipeline to manage". None of that is true any more:
eight bundles ship across four `dist/` directories, four esbuild scripts build
them, `@floating-ui/dom`, `@tiptap/*` and `sortablejs` are npm dependencies, and
each package already exposes an asset route. The ADR describes a repository that
stopped existing several releases ago, and its consequences ("each package is
self-contained — its views include everything needed") are exactly the assumption
that produced the defect below.

The reported symptom: an application navigates with `wire:navigate` from a page
with no table to a page with one, and gets `wireRecordSelection is not defined`,
dead dropdowns, and a sheet backdrop stuck over the page. The consuming app
worked around it by hand-including four package partials in its admin layout.

### Delivery timing and registration timing are independent

They are two separate choices, and only one of the four pairings is broken:

| Delivery | Registration | Works? |
| --- | --- | --- |
| Always in the document (layout) | `alpine:init` | yes |
| Always in the document (layout) | unconditional | yes |
| Arrives late (per-component, lazy, AJAX) | unconditional | yes |
| **Arrives late** | **`alpine:init`** | **no** |

wireStack had picked the one broken cell on every bundle but one. Delivery was
per-surface — each surface `@include`d its own asset partial — and registration
was `alpine:init`-only.

Verified in `vendor/livewire/livewire/dist/livewire.esm.js` (Livewire 3.8.2,
bundling Alpine 3.15.12):

- **`alpine:init` fires exactly once per document.** `start2()` (line 2074) is
  guarded by a `started` flag and dispatches the event at line 2081. Navigation
  calls `initTree`, never `start2()` again.
- **The late script does execute.** On a forward `wire:navigate`, `mergeNewHead`
  injects head assets whose `outerHTML` is new, and line 10231
  (`afterNewScriptsAreDoneLoading(...)`) waits for them before
  `nowInitializeAlpineOnTheNewPage`.

So the bundle arrived in time and registered nothing: it subscribed to an event
that had already fired. `initTree` then evaluated `x-data="wireRecordSelection(…)"`
against an empty registry.

Late registration is legal. `Alpine.data()` is a plain assignment with no timing
guard (dist line 2928, `function data(name, callback) { datas[name] = callback }`),
and Alpine never re-evaluates `x-data` on an element it has already marked, so a
late registration simply applies to trees initialised afterwards.

`forms/resources/js/tiptap-editor.js` was the one bundle that already registered
unconditionally, and its comment named the bug: *"Alpine already started (e.g.
script loaded after a Livewire navigation)."*

### The residual race: cached Back/Forward

Unconditional registration does not close everything. `swapCurrentPageWithNewHtml`
(dist line 10008) **defaults its continuation to a no-op** and lets the caller
install a real one:

```js
let afterRemoteScriptsHaveLoaded = () => {};
mergeNewHead(newHead).finally(() => { afterRemoteScriptsHaveLoaded(); });
...
andThen((i) => afterRemoteScriptsHaveLoaded = i);
```

The forward path (line 10223) passes a callback that accepts that setter. The
**cached back/forward path (line 10277) passes `() => {…}`, which ignores the
parameter** — so the no-op survives and `nowInitializeAlpineOnTheNewPage` runs
without awaiting the newly injected head scripts.

No registration strategy fixes that, because the script may still be in flight
when Alpine initialises the tree. Only a bundle that was *already in the
document* is immune. This is undocumented Livewire behaviour and it is why the
second half of the decision is not optional.

## Decision

### 1. Both halves, for different reasons

**Every bundle registers unconditionally and idempotently**, and **the core
bundles are in the initial document**. Registration alone leaves the cached
back/forward race open; document-presence alone leaves lazily and AJAX-delivered
surfaces broken. Neither substitutes for the other.

The registration idiom, made binding in `AI_CODING_STANDARD.md`:

```js
let registered = false
const register = () => {
    if (registered || ! window.Alpine) return
    registered = true
    window.Alpine.data('wireX', wireX)
}
if (window.Alpine) register()
else document.addEventListener('alpine:init', register)
```

The `registered` guard is load-bearing, not defensive: the directive and a
per-surface partial can both emit the same `src`, and the browser will execute it
twice.

It is an inlined idiom per bundle rather than a shared imported module, because
`table/resources/js/record-selection.js` must stay import-free —
`selection-assets.blade.php` inlines its source verbatim when `dist/` is missing,
and `SelectionAssetTest` enforces it.

### 2. `Foundation/Assets/` is the canonical owner, in core

`NyonCode\WireCore\Foundation\Assets\AssetManager` is a container singleton
holding a `package => id => asset` registry; `Js` is the value object for one
bundle; `Contracts\Asset` is the interface a future `Css` would implement.
`Foundation/View/FloatingAssets`, which memoised exactly one bundle URL for the
same reason, was the seed and now delegates.

Core is the lowest layer that can own this, matching `Foundation/Icons/IconManager`.
**Core does not learn that downstream packages exist**: each provider registers
its own bundles from its own `bootedPackage()`, so the registry is assembled by
whoever is installed.

`Js::make($id, $path)` takes a **filesystem** path — used for the `?id=<mtime>`
cache-buster — and resolves the URL from the `{package}.asset` named route each
package already registers. Assets are therefore served straight out of `dist/`:
no `vendor:publish`, no npm, no build step for a consumer. A path starting
`http://`, `https://` or `//` is treated as remote and used verbatim; there is no
`remote()` builder to get wrong, matching how Filament detects remoteness (and
unlike a `Js::remote()`, which exists in neither Filament v3 nor v4).

Adopted from Filament: `data-navigate-track` (meaningful because the mtime query
string already changes on deploy, so Livewire full-page-reloads instead of
running new markup against a stale bundle) and `data-navigate-once`.

### 3. One directive the app puts in its layout

`@wireStackScripts` — the repository's first `Blade::directive` — emits every
registered bundle, once, into the app's `<head>`. `@wireStackScripts('wire-table')`
narrows to one package. The compiler output is a one-line passthrough to
`AssetManager::renderScripts()`; no presentation logic lives in the compiled
string.

It is **additive**. Every surface still `@include`s its own asset partial, so an
app that never adds the directive keeps working exactly as before; when the
directive is present those partials dedupe to no-ops, which is precisely what the
idempotent registration guard buys.

### 4. Lazy is for heavy bodies, never for registrators

Core interaction controllers (dropdown, context menu, tabs, wizard, editable
cell, fill handle, record selection, record actions, sortable) are never lazy
per-component — that is the practice that produced the defect. Heavy, optional
bodies may be: `wire-core-chart.js` is registered `loadedOnRequest()`, so the
directive leaves it out of every page while the chart widget's own partial fetches
it — registering it anyway keeps a single owner of its URL, since the partial asks
`AssetManager::url()` instead of recomputing route and mtime. The code-split TipTap
ESM bundle stays outside the registry entirely and is delivered by the editor
field.

Lazy-load bodies, never registrators.

### 5. Two bundles that did not exist

- **`wire-sortable`** had no `resources/js/`, no `dist/`, no asset route: 400
  lines of JS and 145 of CSS lived inline in a Blade partial, and SortableJS was
  fetched from a CDN at runtime, which breaks offline use and a strict CSP. It is
  now `dist/wire-sortable.js` with SortableJS compiled in, served by a
  `wire-sortable.asset` route.
- **`wireChart` was never delivered at all** in a consuming app. It used
  `@push('scripts')` and no package layout renders a matching `@stack('scripts')`;
  the sole consumer was a workbench preview, which is exactly why the bug survived
  — it looked fine in development. It is now `dist/wire-core-chart.js` delivered
  through `@assets`. Chart.js itself remains the application's own dependency and
  is deliberately not bundled.

## Consequences

- **Good:** a controller reaching a page for the first time via `wire:navigate`,
  a lazily rendered table, or an AJAX-loaded modal now registers. `wireX is not
  defined` and the scrim-over-a-dead-table symptom are gone.
- **Good:** the consuming app's workaround — four package partials pasted into an
  admin layout — collapses to one directive, and the app stops depending on
  internal partial paths.
- **Good:** reordering works offline and under a strict CSP; no runtime CDN
  request remains anywhere in the stack.
- **Good:** delivery stays publish-free. A consumer needs no npm, no
  `vendor:publish` and no build step for any package asset.
- **Trade-off:** `config('wire-sortable.sortablejs_cdn')` now defaults to `null`.
  It still emits its tag when an app sets it, but the drag controller closes over
  the bundled import and never reads `window.Sortable`, so the key can no longer
  affect reordering either way. **An application whose own code relied on that
  CDN script providing a global `window.Sortable` must now load SortableJS
  itself** — this broke one in-repo browser driver, so it is a real migration
  note, not a theoretical one.
- **Trade-off:** the always-emitted set is a small fixed cost on pages that use
  none of it. That is the price of §1's second half; it is bounded by keeping
  heavy bodies `loadedOnRequest()`.
- **Reversed:** `Table::lazy()` no longer force-ships the table's bundles with
  the placeholder render. That existed only because of `alpine:init`; Livewire
  awaits `payload.intercept` (which runs new `@assets` to completion) before
  morphing an AJAX response in, so the factory exists before the deferred table
  is initialised. `lazy()` is a lever for query and render cost, and now for
  first-paint script weight too.
- **Not done:** no `Css` asset type. The contract is shaped for one, but the only
  package CSS that cannot be Tailwind-scanned (the `.wire-sortable-*` classes,
  applied from JS to elements JS creates) is already emitted once per page by
  `@assets`, so a second delivery channel would buy nothing.

## See also

- `architecture/plans/js-asset-registration.md` — the analysis, with the verified
  Livewire line references this ADR cites
- `AI_CODING_STANDARD.md` § Rendering — the binding registration idiom
- ADR 0002 — superseded
- ADR 0015 — the performance extensions `lazy()` belongs to
