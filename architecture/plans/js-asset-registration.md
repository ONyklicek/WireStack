# JS asset registration — SPA-proof delivery (analysis & plan)

**Goal.** Make every wireStack Alpine controller available after any
`wire:navigate`, without the consuming app hand-including partials in its
layout. Today an app that navigates from a page with no table to a page with
one gets `wireRecordSelection is not defined`, dead dropdowns, and a sheet
backdrop stuck over the page.

Written 2026-07-29, after a consuming app had to work around it by including
four partials globally in its admin layout.

## 1. The defect

### 1.1 One combination cannot work

Delivery and registration are two independent choices, and only one pairing is
broken:

| Delivery | Registration | Works? |
| --- | --- | --- |
| Always in the document (layout) | `alpine:init` | ✅ — Filament's bundled components |
| Always in the document (layout) | unconditional | ✅ |
| Arrives late (per-component / lazy) | unconditional | ✅ — Filament's async components |
| **Arrives late** | **`alpine:init`** | ❌ **— every wireStack bundle** |

wireStack picked the one broken cell. Delivery is per-component (each surface
`@include`s its own asset partial), and registration is `alpine:init`-only.

### 1.2 Why `alpine:init` cannot carry a late script

Verified in `vendor/livewire/livewire/dist/livewire.esm.js` (Livewire 3.8.2,
bundling Alpine 3.15.12):

- **`alpine:init` fires exactly once per document.** `start2()` (line 2074) is
  guarded by a `started` flag and dispatches `alpine:init` at line 2081.
  Navigation calls `initTree`, never `start2()` again.
- **A late script still executes.** On forward `wire:navigate`, `mergeNewHead`
  injects head assets whose `outerHTML` is new, and line 10231
  (`afterNewScriptsAreDoneLoading(...)`) *waits* for them before
  `nowInitializeAlpineOnTheNewPage`.

So the bundle loads in time; it just registers nothing. It subscribes to an
event that already fired. Then `initTree` evaluates
`x-data="wireRecordSelection(...)"` against an empty registry.

`packages/table/resources/views/tables/index.blade.php:246` already documents
this exact mechanism for the lazy case — the fix generalises that comment.

### 1.3 Late registration is legal, and only affects future trees

`Alpine.data()` is a plain assignment with no timing guard (dist line 2928):

```js
var datas = {};
function data(name, callback) { datas[name] = callback; }
```

It is callable at any time. But Alpine never re-evaluates `x-data` on an
element it has already marked (`_x_marker`), so a late registration applies
only to trees initialised afterwards. Given §1.2's ordering guarantee — scripts
awaited *before* `initTree` — registering at script top level lands in time.

### 1.4 The residual race: cached Back/Forward

`swapCurrentPageWithNewHtml` (dist line 10008) defaults its continuation to a
no-op and lets the caller install a real one:

```js
let afterRemoteScriptsHaveLoaded = () => {};
mergeNewHead(newHead).finally(() => { afterRemoteScriptsHaveLoaded(); });
...
andThen((i) => afterRemoteScriptsHaveLoaded = i);
```

The forward path (line 10223) passes a callback that accepts that setter. The
cached back/forward path (line 10277) passes `() => {...}` which **ignores the
parameter**, so the no-op survives and `nowInitializeAlpineOnTheNewPage` runs
without awaiting head scripts.

No registration strategy fixes this — the script may still be in flight. Only
having the bundle already in the initial document does. This is undocumented
Livewire behaviour and the reason §3 is not optional.

## 2. State before the fix (measured)

This is the *before* picture — every row here has since been changed by §3. It is
recorded because it is the evidence the design rests on.

Every bundle registered exclusively inside `alpine:init`, except one:

| Controller | Source | Registration | Delivery |
| --- | --- | --- | --- |
| `wireDropdown`, `wireContextMenu`, `wireTabs`, `wireWizard`, `wireEditableCell`, `wireFillHandle` | `core/resources/js/dropdown.js:712` | `alpine:init` | `@assets` + route |
| `wireRecordSelection` | `table/resources/js/record-selection.js:19` | `alpine:init` | `@assets` + route (inline fallback) |
| `wireRecordActions` | `table/resources/js/record-actions.js:817` | `alpine:init` | `@assets` + route |
| `wireImageUpload` | `forms/resources/js/image-processor.js:308` | `alpine:init` | `@assets` + route |
| `wireSortable` | `sortable/…/partials/scripts.blade.php:9` | `alpine:init` | **raw inline `<script>`, emitted *after* its consumer** |
| `wireChart` | `core/…/widgets/chart.blade.php:36` | `alpine:init` | **`@push('scripts')` — no package renders that stack** |
| `tiptapEditor` | `forms/resources/js/tiptap-editor.js:210` | **unconditional + fallback** ✅ | `@assets` + route |

`tiptap-editor.js` is the in-repo precedent, and its comment names the bug:
*"Alpine already started (e.g. script loaded after a Livewire navigation)."*

Two further defects surfaced while mapping this. **Both are now fixed** — kept
here because they are the evidence for §3, not open work:

- **`wireChart` was never delivered in a consuming app.** Its `@push('scripts')`
  had no `@stack('scripts')` in any package layout; the only consumer was a
  workbench preview, which is precisely why the bug survived — it looked fine in
  development. Now a real bundle (`dist/wire-core-chart.js`) delivered through
  `@assets`, registered `loadedOnRequest()` so the heavy body stays off pages
  without a chart while the URL keeps one owner.
- **`wire-sortable` had no `resources/js/`, no `dist/`, no asset route, and no
  `hasAssets()`.** 400 lines of JS and 145 of CSS lived inline in a Blade
  partial, and SortableJS was fetched from a CDN at runtime (breaking offline and
  strict CSP). Now `dist/wire-sortable.js` with SortableJS bundled, served by a
  `wire-sortable.asset` route. `config('wire-sortable.sortablejs_cdn')` defaults
  to `null` but still emits its tag when set — the controller closes over the
  bundled import and never reads `window.Sortable`, so the key can no longer
  break reordering either way.

## 3. The plan

Two parts with distinct justifications. Both are required; part A alone leaves
§1.4 open, part B alone leaves lazily/AJAX-delivered surfaces broken.

### A. Unconditional, idempotent registration (root cause)

Every bundle adopts the `tiptap-editor.js` idiom:

```js
let registered = false
function register() {
    if (registered || !window.Alpine) return
    registered = true
    window.Alpine.data('wireX', wireX)
}
if (window.Alpine) register()
else document.addEventListener('alpine:init', register)
```

The `registered` guard is load-bearing, not defensive: once part B ships, the
directive and a per-surface partial can both emit the same `src`, and the
browser will execute it twice.

`record-selection.js` must stay import-free (`SelectionAssetTest` enforces it,
because `selection-assets.blade.php` inlines the source verbatim when `dist/`
is missing). So this cannot be a shared imported module — it is an inlined
idiom per bundle, made binding in `AI_CODING_STANDARD.md`.

### B. Core bundle + provider registration + one directive

Canonical owner: `packages/core/src/Foundation/Assets/` — the lowest layer that
can own it, matching `Foundation/Icons/IconManager`. `Foundation/View/FloatingAssets`
is the seed to generalise.

Each provider registers its bundle in `bootedPackage()`; the app puts one
`@wireStackScripts` in its layout `<head>`. Delivery mechanics are unchanged —
the existing `Route::get('/wire-core/assets/{asset}.js')` per package already
matches Nova's serve-from-package-path model, so no publishing step is added.

Adopted from Filament: `data-navigate-track` on the tag (our mtime `?id=`
buster already supplies the query string that makes it meaningful) and
`data-navigate-once`.

Not adopted: `Js::remote()` — it does not exist in Filament v3 or v4;
remoteness is detected from the path.

### C. Lazy stays for heavy bodies only

Per §4 of the brief, and matching Filament exactly: core interaction
controllers are never lazy per-component; heavy/optional assets (tiptap, image
processing, charts) may be, but the *loader* ships in the always-present
bundle. Lazy the bodies, never the registrators.

## 4. Backward compatibility

| Change | Impact |
| --- | --- |
| Unconditional registration | None. Same controllers, same names, strictly more cases work. |
| `@wireStackScripts` | Additive. Apps without it keep the per-surface `@assets` includes. |
| Per-surface partials retained | Become dedup no-ops when the directive is present. |
| Lazy `@once` block deleted (`index.blade.php:246`) | Its stated reason no longer holds once A ships. `LazyTableAssetsTest` must be rewritten to assert the new invariant, not deleted. |
| SortableJS bundled | `config('wire-sortable.sortablejs_cdn')` must keep working when set; default becomes the bundled copy. Apps that set it explicitly are unaffected. |
| `wireChart` delivery | Bug fix; no app can be relying on a controller that never loads. |

## 5. Verification

The gate is the CDP drivers (`npm run verify:drivers`) — Pest sees markup, not
what the browser does with it. `workbench/scripts/verify-spa-navigate.mjs` must
go **red before** any fix and green after; a driver that was never seen red
proves nothing.

Plus: a feature test that a page with no table still carries the core
registration, AssetManager unit tests, and the existing driver suite kept green.

## 6. Supersedes

`architecture/decisions/0002-js-alpine-distribution.md` is falsified on every
clause — "No custom JavaScript files are shipped with any package" (7 bundles
ship), "Zero build step for JS" (3 esbuild scripts), "No npm dependencies"
(`@floating-ui/dom`, `@tiptap/*`), "no shared JS bundle or asset pipeline"
(three asset routes). A new ADR must supersede it.
