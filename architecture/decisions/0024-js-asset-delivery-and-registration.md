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

### 6. Static delivery is the default, and the mirror maintains itself

*Amended 2026-08-07.* Serving a bundle from a route only works when the request
reaches PHP. A very common nginx layout answers `.js` from a `try_files $uri =404`
block and never forwards it — the same block 404s Livewire's own
`/livewire/livewire.js` — and on shared hosting that block is frequently not the
application's to change. A delivery mode whose correctness depends on a vhost the
application cannot edit is not a delivery mode. **A file that exists is served by
every web server configuration there is**, so bundles are delivered as real files
under `public/vendor/{package}` and the route is what remains for the case where
they cannot be written.

Making the app run `vendor:publish` to get there was the first attempt, and it is
not enough: a deploy that uploads `vendor/` over FTP, or runs `composer install`
against a lock file, never re-publishes, and the app silently runs last release's
JavaScript — or, before this, dropped back to a 404. So the mirror maintains itself.

`NyonCode\LaravelPackageToolkit\Support\PublishedAssets` (a request-scoped
singleton) copies each package's `dist/` into `public/vendor/{package}` the first
time one of its bundles resolves a URL, and `Js::getUrl()` emits that path. The
properties that make it affordable to do on a web request:

- **Incremental.** Only a file that is missing or older than the shipped one is
  copied. Steady state is a handful of `stat` calls and no writes; an upgrade is one
  copy per changed bundle, on one request.
- **Atomic.** Copies land through `<name>.<random>.tmp` and `rename()`, which is
  atomic within a filesystem. A concurrent request reads the whole old file or the
  whole new one, never a truncated bundle — which would be a syntax error taking
  every controller in it down.
- **Whole-directory.** Every shipped file is mirrored, not only the registered
  bundles: TipTap's entry imports `./chunk-<hash>.js`, which the browser fetches
  itself and PHP is never asked to resolve.
- **Lazy, not booted.** Mirroring from `boot()` would put a directory walk on every
  request the application serves, including the queue worker and the API route that
  will never emit a `<script>`.
- **Non-fatal.** Where `public/` cannot be written nothing throws: the route serves
  the bundle as before, and an older copy already present is still preferred over a
  route that may be unreachable.

**There is no configuration at all** — no `assets.url`, no CDN base, no toggle. The
alternative shape, publishing plus re-registering every bundle in the app's provider
against `asset('vendor/…')`, was rejected outright: `Js` treats a URL-shaped path as
remote and drops the `?id=<mtime>` that makes `data-navigate-track` mean anything,
the app ends up hand-maintaining an id → filename map for four packages, and TipTap's
code-split ESM breaks on its relative chunk import.

#### The toolkit owns the mirror; core owns the fallback and the warning

The mechanism lives in `nyoncode/laravel-package-toolkit` (2.3.0), not in wire-core.
It belongs there: the toolkit already owns `hasAssets()`, the `{package}::assets`
publish tag and the `public/vendor/{short-name}` destination, and `PublishedAssets`
is the same rule read back. Splitting write and read across two repositories on
separate release cycles is the drift this section removed once already.

Its `MirrorsPackageAssets` trait declares the directory from each provider's
`register()` — `$packager->assetDirectory()`, the only place that knows it for
certain, rather than string-matching `/dist/` in a bundle path — and `publishAssets()`
carries `laravel-assets` alongside the per-package tag in one `publishes()` call, so
the two cannot point at different directories. `hasAssets(mirror: false)` opts a
package out while keeping the publish tags.

Nothing in wire-core registers, declares or copies any more. What stays here is what
the toolkit deliberately does not do: `Js::getUrl()` falls back to the package's own
asset route when the mirror returns `null`, and `AssetManager::renderScripts()`
renders the console warning off `PublishedAssets::isStale()` — the toolkit has no
renderer and no route to fall back to.

This raised wire-core's floor to the toolkit's: `illuminate/support ^12.0|^13.0`.
Laravel 10 and 11 are no longer supported by the stack.

#### A stale copy is served, not skipped

An earlier draft had the resolver skip a published copy older than the shipped one
and fall back to the route, reasoning that a route is always correct. That is wrong
in exactly the deployment this section exists for: the app that ends up with an
unrefreshable copy is the one whose `public/` is not writable, which is also the one
whose nginx answers `.js` itself. Falling back would trade a bundle one release old
for no bundle at all.

The stale copy is announced instead, and the warning is **not** gated on `app.debug`
— Livewire does not gate its equivalent either. It is a production condition, and a
warning nobody can see is not a warning.

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
- **Good:** static delivery needs no command, no composer hook and no vhost change,
  which is what makes it work on hosting the application does not control. The
  `.js`-blocking nginx layout is no longer a failure mode; it is the layout the
  default path is built for.
- **Good:** an upgrade is picked up by the first request that renders a page, so a
  deploy that only uploads `vendor/` is as correct as one that runs the publish.
- **Trade-off:** the framework writes into `public/vendor` from a web request. It is
  the same directory and the same bytes `vendor:publish` writes, guarded by an atomic
  rename, and inert where the directory is not writable — but it is a write during a
  request, and a deployment that wants none should run the publish at build time.
- **Trade-off:** every asset URL resolution costs a directory walk of one package,
  once per request. Measured against four `dist/` directories of ten files total it
  is a handful of `stat` calls; a package shipping hundreds of files would want a
  cheaper freshness check than comparing them all.
- **Trade-off:** where `public/` is not writable and an old copy is present, the page
  loads a bundle one release old. The unconditional console warning is the only
  signal; there is no server-side log line, because it would fire on every request of
  a busy site to say something one filesystem permission fixes.
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
