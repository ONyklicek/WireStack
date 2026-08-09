# Browser Assets

Owner package: `packages/core` (`NyonCode\WireCore\Foundation\Assets`)
Cross-package: every package ships bundles and registers them here.

How a `.js` bundle gets from `resources/js/` into a consuming app's `<head>`, and
what breaks when a step is skipped. This is the reference for the current state;
[ADR 0024](decisions/0024-js-asset-delivery-and-registration.md) records *why* it
looks like this, and [`plans/js-asset-registration.md`](plans/js-asset-registration.md)
carries the Livewire line references behind the claims. Consumer-facing
documentation is `docs/getting-started.md` § JavaScript Assets.

## The Two Independent Halves

Delivery timing and registration timing are separate choices, and only one of the
four pairings is broken:

| Delivery | Registration | Works? |
|---|---|---|
| in the initial document | `alpine:init` | yes |
| in the initial document | unconditional | yes |
| arrives late (per-surface, lazy, AJAX) | unconditional | yes |
| **arrives late** | **`alpine:init`** | **no** |

`alpine:init` fires exactly once per document; a `wire:navigate` visit never
restarts Alpine. A bundle delivered with the new page and listening only for that
event therefore registers nothing, and `initTree` evaluates
`x-data="wireRecordSelection(…)"` against an empty registry —
`wireRecordSelection is not defined`, dead dropdowns, a stuck sheet backdrop.

Unconditional registration fixes that but not the **cached Back/Forward** path,
where Livewire keeps a no-op continuation and does not await newly injected head
scripts. Only a bundle *already in the document* is immune to that one. So both
halves ship, for different reasons: **every bundle registers unconditionally, and
the bundles are in the initial document.** Neither substitutes for the other.

---

## What Ships

Committed IIFE bundles under each package's `dist/`, built with esbuild:

| Package | `dist/` | Registered id | Source |
|---|---|---|---|
| core | `wire-core-dropdown.js` | `dropdown` | `resources/js/dropdown.js` (+ `editable/`, `fill/`, `support/`) |
| core | `wire-core-chart.js` | `chart` (`loadedOnRequest`) | `resources/js/chart.js` |
| core | `wire-core-copy.js` | `copy` | `resources/js/copy.js` |
| forms | `wire-forms-image.js` | `image` | `resources/js/image-processor.js` |
| forms | `tiptap/` (code-split ESM) | — not registered | `resources/js/tiptap-editor{,-addons}.js` |
| table | `wire-table-records.js` | `records` | `resources/js/record-actions.js` |
| table | `wire-table-selection.js` | `selection` | `resources/js/record-selection.js` |
| table | `wire-table-live.js` | `live` | `resources/js/record-live.js` |
| sortable | `wire-sortable.js` | `sortable` | `resources/js/sortable.js` (SortableJS bundled in) |

The copy affordance is core's, not table's (`2137b46`) — it is the one bundle that
moved packages, and the id stayed `copy` while the file became `wire-core-copy.js`.

`wire-core-dropdown.js` carries the whole shared interaction layer — `wireDropdown`,
`wireContextMenu`, `wireTabs`, `wireWizard`, `wireEditableCell`, `wireFillHandle` —
which is exactly the set that must never arrive late.

**`dist/` is committed and is not rebuilt for you.** After editing anything under
a package's `resources/js/`, run its build script:

```bash
npm run build:core-assets       # dropdown + chart + copy
npm run build:forms-assets      # tiptap (ESM, split) + image processor
npm run build:table-assets      # records, selection, live
npm run build:sortable-assets   # sortable, SortableJS compiled in
```

Editing the source and shipping without rebuilding is a silent no-op: the page
loads the old bundle and the tests, which read markup rather than run it, pass.

---

## Registration (in the JS)

Binding idiom, one inlined copy per bundle (`AI_CODING_STANDARD.md` § Rendering):

```js
let registered = false

const registerWireX = () => {
    if (registered || ! window.Alpine) return
    registered = true
    window.Alpine.data('wireX', wireX)
}

if (window.Alpine) registerWireX()
else document.addEventListener('alpine:init', registerWireX)
```

- The `registered` guard is **load-bearing, not defensive**: `@wireStackScripts`
  and a per-surface partial can both emit the same `src`, and the browser executes
  it twice.
- It is inlined per bundle rather than imported from a shared module because
  `table/resources/js/record-selection.js` must stay import-free — see the traps
  below.
- `Alpine.data()` is a plain assignment with no timing guard, and Alpine never
  re-evaluates `x-data` on an element it already initialised, so a late
  registration simply applies to trees initialised afterwards. Late is legal;
  never-registering is not.

---

## Declaration (in PHP)

`Foundation/Assets/` is the canonical owner, in core because that is the lowest
layer that can hold it — the same placement as `Foundation/Icons/IconManager`.

```text
Foundation/Assets/
├─ Contracts/Asset.php   the interface a future Css would implement
├─ Js.php                one bundle, as a value object
└─ AssetManager.php      container singleton: package => id => asset
```

Each provider declares its own bundles from `bootedPackage()`. **Core never learns
that downstream packages exist**; the registry is assembled by whoever is
installed:

```php
protected function registerAssets(): void
{
    app(AssetManager::class)->register([
        Js::make('selection', self::ASSETS_PATH.'/wire-table-selection.js')
            ->navigateTrack()
            ->navigateOnce(),
    ], 'wire-table');
}
```

`Js::make($id, $path)` takes an **absolute filesystem path** (needed for the
`?id=<mtime>` cache-buster) or an absolute URL. `$id` doubles as the `{asset}`
parameter of the package's `{package}.asset` route, which keeps a declaration to
one line.

| Builder | Effect |
|---|---|
| `->module()` | `type="module"` |
| `->defer()` | `defer` |
| `->navigateTrack()` | `data-navigate-track` — Livewire full-reloads a `wire:navigate` visit when the query string changed, so a deploy is picked up instead of running new markup against a cached bundle |
| `->navigateOnce()` | `data-navigate-once` — never re-execute on a navigate visit |
| `->loadedOnRequest()` | keep out of the always-emitted set; the surface fetches it itself |

A path starting `http://`, `https://` or `//` is treated as remote and used
verbatim — the way Filament detects remoteness. There is deliberately no
`remote()` builder to get wrong.

### `AssetManager`

| Method | Use |
|---|---|
| `register(array $assets, string $package)` | from a provider's `bootedPackage()`; re-registering an id replaces it |
| `renderScripts(?string $package)` | what `@wireStackScripts` compiles to; memoised per package |
| `url(string $package, string $id)` | for a surface emitting its own tag |
| `get(string $package, string $id)` | the asset object; throws `AssetRegistrationException` when unknown |
| `flushUrls()` | Octane: drop every resolved URL and rendered tag, keep the registry |

`Foundation/View/FloatingAssets` is a thin facade over `url('wire-core',
'dropdown')`, kept because a dozen partials already ask for it by that name. It
holds no cache of its own — a canonical owner *is* the resolve-once.

### The directive

`@wireStackScripts` is the repo's only `Blade::directive` (registered in
`WireCoreServiceProvider::bootFoundation()`). Its compiled output is a one-line
passthrough — no presentation logic lives in the compiled string:

```php
<?php echo app(\NyonCode\WireCore\Foundation\Assets\AssetManager::class)->renderScripts(); ?>
```

`@wireStackScripts('wire-table')` narrows to one package. It is **additive**:
every surface still `@include`s its own asset partial, so an app that never adds
the directive keeps working, and where the directive *is* present those partials
dedupe to no-ops. That is precisely what the idempotent registration guard buys.

---

## Delivery (where the file comes from)

`Js::getUrl()`, in order:

1. **Remote path** → used verbatim, no route, no mtime.
2. **The published mirror** (the default) — `PublishedAssets::url()` returns
   `/vendor/{package}/{file}?id=<mtime>`.
3. **The package route** — `route('{package}.asset', ['asset' => $id])`, reached
   only when the mirror could not write and nothing was published before.

Static files are the default because a route only works when the request reaches
PHP, and a very common nginx layout answers `.js` from `try_files $uri =404` and
never forwards it — the same block 404s Livewire's own `/livewire/livewire.js`, and
on shared hosting it is frequently not the app's to change. **A file that exists is
served by every web server configuration there is.**

### Ownership split

The mirror lives in **`nyoncode/laravel-package-toolkit`**, not here: the toolkit
already owns `hasAssets()`, the `{package}::assets` publish tag and the
`public/vendor/{short-name}` destination, and `PublishedAssets` is that same rule
read back. Splitting write and read across two repos on separate release cycles is
drift this removed once already.

| Side | Owner | What |
|---|---|---|
| declare | `MirrorsPackageAssets` (toolkit) | `$packager->assetDirectory()` → `PublishedAssets::mirrors()`, from `register()` |
| copy | `PublishedAssets::sync()` (toolkit) | lazy, incremental, atomic mirror of the whole directory |
| fall back | `Js::getUrl()` (core) | the package's own asset route when the mirror returns `null` |
| warn | `AssetManager::stalePublishWarning()` (core) | `console.warn` off `PublishedAssets::isStale()` |

Nothing in wire-core registers, declares or copies.

### What the toolkit owns as of 2.4

The split above was drawn against toolkit **2.3**, where the toolkit had a mirror
and no renderer, so "the toolkit cannot render a tag" was the whole reason
`AssetManager` exists. **2.4 has a renderer**, and since `768c299` the constraint
is `^2.4.0` — so that reason no longer holds on its own and the divergence has to
be argued rather than assumed. Declared with `hasAssets(entries: [...])`:

| Toolkit 2.4 | Ours | Overlap |
|---|---|---|
| `@packageAssets` / `@packageStyles` / `@packageScripts` / `@packageAssetUrl` | `@wireStackScripts`, `FloatingAssets` | full — both render tags for a named package, both narrow by package, both take an explicit entry list |
| `Asset::make()->classic()`, `->attributes()`, `->asStylesheet()`, `->asScript()` | `Js::module()`, `->defer()`, `->navigateTrack()`, `->navigateOnce()` | full — `navigateOnce()` is `->attributes(['data-navigate-once' => true])`, and `data-navigate-track="reload"` is on every toolkit tag by default |
| `PackageAssets::resolution()`, the `hasAbout()` row | — | toolkit only |
| `hasViteAssets()` — the consuming app's Vite build compiles the package's sources | — | toolkit only |
| — | `Js::getUrl()`'s route fallback | ours only: `PackageAssets::url()` returns `null` and stops |
| — | `loadedOnRequest()` | ours only, as a *default*; the toolkit expresses it per call site instead (`@packageScripts('wire-core', 'js/chart.js')`) |
| — | `AssetManager::stalePublishWarning()` | ours only, though it is built on the toolkit's `isStale()` |

So one thing keeps the renderer here rather than making it a thin wrapper: **the
route fallback**. ADR 0024 chose static files first *and* a route behind them for
the app whose `public/` cannot be written, and the toolkit's renderer has no
second place to look. Everything else in the left column is now expressible.

Two things to know before anyone migrates:

- **Our bundles are IIFE, and the toolkit renders `.js` as `type="module"`.** A
  module is deferred and its top-level declarations never reach `window`, which
  is exactly how the registration idiom above works — so a naive port registers
  nothing and every `x-data` fails, with no error at the point of the mistake.
  Each entry needs `Asset::make(...)->classic()`.
- **Declaring `entries:` while the per-surface partials still emit their own tags
  means two tags per bundle.** Harmless only because the `registered` guard makes
  the second execution a no-op — which is a reason the guard is load-bearing, not
  a reason to rely on it.

`hasViteAssets()` is the one piece with no counterpart here and a real consumer
benefit: an app on Tailwind currently has to point `@source` at this repo's Blade
markup by hand or watch half the classes get purged. Not adopted yet.

### Properties of the mirror

- **Lazy** — triggered by the first bundle of a package to resolve a URL in a
  request, never from `boot()`. Mirroring at boot would put a directory walk on
  every queue job and API route that will never emit a `<script>`.
- **Incremental** — only a file missing or older than the shipped one is copied.
  Steady state is a handful of `stat` calls and no writes.
- **Atomic** — copies land through `<name>.<random>.tmp` + `rename()`. A truncated
  bundle would be a syntax error taking every controller in it down.
- **Whole-directory** — every shipped file, not only the registered ones: TipTap's
  entry imports `./chunk-<hash>.js`, which the browser fetches itself and PHP is
  never asked to resolve.
- **Non-fatal** — where `public/` cannot be written nothing throws, and an older
  copy already present is still preferred over a route that may be unreachable.

A stale copy is **served, not skipped**: the app that ends up with an unrefreshable
copy is the one whose `public/` is not writable, which is also the one whose nginx
answers `.js` itself. The warning is not gated on `app.debug` — it is a production
condition, and Livewire does not gate its equivalent either.

**There is no configuration at all** — no `assets.url`, no CDN base, no toggle.

---

## Traps

- **`loadedOnRequest()` is for heavy optional bodies, never for a registrator.**
  Per-component lazy delivery of an interaction controller is the practice that
  produced the original defect. `wire-core-chart.js` is the legitimate case: the
  directive leaves it out, the widget's partial fetches it, and it is still
  registered so `AssetManager::url()` stays the single owner of its URL.
- **`record-selection.js` must stay import-free.** `selection-assets.blade.php`
  inlines its source verbatim (wrapped in an IIFE, matching what `--format=iife`
  does) when `dist/` is missing, because the `x-data` on the table wrapper owns
  search, filters, the bulk bar, pagination, mobile cards and the modal hosts — a
  dangling factory reference would kill Alpine for all of it, silently.
  `SelectionAssetTest` enforces the constraint.
- **Per-surface partials use `@assets`, never `@push`.** No package layout renders
  a matching `@stack('scripts')`, and a DOM-morphed `<script>` inside a
  Livewire-loaded modal never executes. `wireChart` was undelivered in consuming
  apps for exactly this reason, and survived because the only caller was a
  workbench preview.
- **Octane.** `AssetManager::flushUrls()` and `PublishedAssets::flush()` run on
  `RequestTerminated`. Without them a worker alive across a deploy keeps emitting
  last release's `?id=`, and `data-navigate-track` — which exists to catch exactly
  that — never fires.
- **TipTap stays outside the registry.** The field delivers it; the mirror still
  copies the directory, which is what makes its relative chunk import resolve.
- **`Table::lazy()` no longer force-ships the table's bundles.** That existed only
  because of `alpine:init`; Livewire awaits `payload.intercept` before morphing an
  AJAX response in, so the factory exists before the deferred table initialises.

---

## Typical Changes

| Goal | Touch |
|---|---|
| change a controller's behaviour | `packages/<pkg>/resources/js/*.js` **+ its `npm run build:<pkg>-assets`** |
| ship a new bundle | source + build script + `Js::make()` in the provider's `registerAssets()` |
| new package joining the stack | `hasAssets('dist')`, an asset route named `{package}.asset`, `registerAssets()` |
| a surface needs a URL | `AssetManager::url($package, $id)` from the partial — never recompute route + mtime |
| make a bundle lazy | `->loadedOnRequest()` + a per-surface `@assets` partial. Bodies only, never registrators |
| change delivery/mirroring | the toolkit (`PublishedAssets`, `MirrorsPackageAssets`), not wire-core |

---

## Tests To Run

```bash
composer test:core        # AssetManagerTest, JsTest, PublishedAssetsTest, WireStackScriptsTest,
                          # DropdownAssetTest, ChartAssetTest, FloatingAssetsTest
composer test:table       # SelectionAssetTest, RecordActionAssetTest, CopyAssetTest, LazyTableAssetsTest
composer test:forms       # ImageAssetTest, TiptapAssetTest
composer test:sortable    # SortableAssetTest
```

Every package has its own `WireStackScriptsTest` asserting its bundles reach the
directive's output.

Pest sees the markup, not what the browser does with it, so a change to a bundle's
body is only verified by the browser gate:

```bash
npm run verify:drivers
```

---

## Related

- [ADR 0024](decisions/0024-js-asset-delivery-and-registration.md) — the decision, with the verified Livewire line references
- [ADR 0002](decisions/0002-js-alpine-distribution.md) — superseded; falsified on every clause
- [`plans/js-asset-registration.md`](plans/js-asset-registration.md) — the analysis behind the fix
- `AI_CODING_STANDARD.md` § Rendering — the binding registration idiom
- `docs/getting-started.md` § JavaScript Assets — the consumer-facing page
- `docs/troubleshooting.md` — the 404 and `wireX is not defined` entries
