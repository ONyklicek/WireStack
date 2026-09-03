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

| Package | `dist/` (also the entry key) | Source |
|---|---|---|
| core | `wire-core-dropdown.js` | `resources/js/dropdown.js` (+ `editable/`, `support/`) |
| core | `wire-core-chart.js` | `resources/js/chart.js` |
| core | `wire-core-copy.js` | `resources/js/copy.js` |
| forms | `wire-forms-image.js` | `resources/js/image-processor.js` |
| forms | `wire-forms-fields.js` | `resources/js/fields.js` (+ `fields/`) |
| forms | `tiptap/` (code-split ESM) | not an entry — the field delivers it | `resources/js/tiptap-editor{,-addons}.js` |
| table | `wire-table-records.js` | `resources/js/record-actions.js` |
| table | `wire-table-selection.js` | `resources/js/record-selection.js` |
| table | `wire-table-live.js` | `resources/js/record-live.js` |
| table | `wire-table-fill.js` | `resources/js/record-fill.js` (+ `fill/`) |
| sortable | `wire-sortable.js` | `resources/js/sortable.js` (SortableJS bundled in) |

The copy affordance is core's, not table's (`2137b46`) — it is the one bundle that
moved packages.

`wire-core-dropdown.js` carries the whole shared interaction layer — `wireDropdown`,
`wireContextMenu`, `wireTabs`, `wireWizard`, `wireEditableCell`,
`wireSearchableSelect` — which is exactly the set that must never arrive late. The
combobox is core's rather than forms' because
`wire-core::partials.searchable-select` is included by seven surfaces across forms
*and* table.

`wireFillHandle` used to be in that list and is the second bundle to move packages
(ADR 0025 § step 10). It is a table gesture, so every wire-core consumer was
shipping it: measured at **9,148 of the bundle's 38,365 bytes**, or 23.8 %. Split
out, a table pays 100 bytes more in total — `editable/sync`, `support/autoscroll`
and `support/rows` are small enough that a second copy is noise — and a forms-only
application pays none of it.

That split created one contract, and it is the kind that fails silently. The
partial morph below must not run over a fill drag; the two halves now live in
separate IIFEs, which cannot import from each other. The seam is the
`wire-filling` class on `<body>`: the controller writes it on the same line it
joins its own drag registry, `support/partials.js` reads it, and both ends are
asserted together in wire-table's `FillHandleAssetTest`. Absent the table bundle
the class never appears, so the answer is `false` — correct, since there is then
no fill handle to drag.

`wire-core-dropdown.js` also carries `support/partials.js`, the client half of
`wire:partial` — which is why any surface emitting an anchor has to deliver it.
Every table does already (a dropdown, an editable cell, a filter or a sheet pulls
it in through `floating-assets`); a widget-only dashboard has none of those, so
the widget grid includes `wire-core::partials.partial-assets` when a widget polls.
Without it the anchor is inert: the response carries the region, the browser
receives it, and nothing on the page changes — no error, no warning, and only
`npm run verify:drivers` sees it.

`wire-forms-fields.js` carries the field controllers whose bodies used to be
inlined per instance — `wireDateTimePicker`, `wireTimePicker`, `wireTagsInput`,
`wireRating`, `wireRichEditor`, `wireMarkdownEditor` — and is delivered to views
that do not have `@wireStackScripts` by `wire-forms::partials.field-assets`.

**`dist/` is committed and is not rebuilt for you.** After editing anything under
a package's `resources/js/`, run its build script:

```bash
npm run build:core-assets       # dropdown + chart + copy
npm run build:forms-assets      # tiptap (ESM, split) + image processor + field controllers
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

`Foundation/Assets/Bundle` is what wire-core still owns. Everything else — the
registry, the URL, the tag, the memo — is `nyoncode/laravel-package-toolkit`'s
`PackageAssets`, which each provider hands its declaration to through the packager.

```text
Foundation/Assets/
└─ Bundle.php   how a wireStack bundle is declared, and the route that serves it
```

Each provider declares its own bundles in `configure()`. **Core never learns that
downstream packages exist**; the registry is assembled by whoever is installed:

```php
$packager
    ->bootedPackage(fn () => Bundle::serve('wire-table', self::ASSETS_PATH))
    ->hasAssets('dist', entries: [
        Bundle::make('wire-table-selection.js'),
    ])
    ->hasAssetFallback(Bundle::servedByRoute('wire-table'));
```

The entry key is the **shipped filename**, not a short id — that is the toolkit's
vocabulary, and it is what `@packageScripts('wire-table', 'wire-table-selection.js')`
and `PackageAssets::url()` take.

### `Bundle`, and why it exists

Three properties are true of every bundle in this repo, and saying them once beats
saying them in four providers:

| Property | Why |
|---|---|
| `classic()` | every bundle is built `--format=iife`. The toolkit renders a `.js` entry as `type="module"` unless told otherwise, and a module is deferred with its top-level declarations sealed off from `window` — which is exactly how the registration idiom works. A module here registers nothing, and every `x-data` fails with no error at the point of the mistake. |
| `defer => null` | `classic()` adds `defer` by default. Removing it keeps the emitted tag byte-identical to what the stack shipped before the toolkit owned it. Nothing is known to break under `defer` — the registrars are order-independent by construction — but nothing had watched it in a browser either, and a structural change should not carry a timing change in with it. |
| `data-navigate-once` | parity with what the old `Js::navigateOnce()` emitted. |

`data-navigate-track="reload"` is the toolkit's own default and is kept — Livewire
tests it with `hasAttribute`, so the value it gained is immaterial.

`Bundle::servedByRoute($package)` and `Bundle::serve($package, $dist)` are the two
halves of the fallback, and they are in one class because they are one mapping read
in opposite directions. `servedByRoute()` reads the route's id back off the filename
(`wire-core-dropdown.js` → `dropdown`, `wire-sortable.js` → `sortable`), because the
route takes an id and the toolkit speaks filenames; `serve()` registers the route
that turns that id back into the file.

Each provider used to write that route out itself, and the four copies had already
split: three built `{package}-{id}.js`, wire-sortable built `wire-{id}.js`. What they
were repeating was not `Route::get` but the contract around it — the `404` that keeps
a missing bundle from surfacing as a `500`, the `[A-Za-z0-9_-]+` id pattern, and the
`public, max-age=31536000` that stops a fallback the renderer reaches on every page
from costing a request every page. Deleting any one of those three from the wire-table
or wire-forms copy passed both suites; the pair is now covered in
`packages/core/tests/Feature/BundleRouteTest.php`, and the round trip over every
shipped bundle in `tests/Integration/AssetPublishingEndToEndTest.php`.

Traversal is barred by the route pattern rather than by the `basename()` each copy
carried: Symfony compiles a route parameter as `[^/]++` — possessive, so it cannot
give characters back — and the literal `.js` after it means a segment holding a slash
*or a dot* never matches. `../../composer.js`, encoded or not, is a 404 before any
closure runs, so that `basename()` call could not fire and is gone. The `where()`
still earns its place: without it `a~b`, `a b` and `á` are ids the lookup would go to
disk with.

### The directive

`@wireStackScripts` is the repo's only `Blade::directive` (registered in
`WireCoreServiceProvider::bootFoundation()`). Its compiled output is a one-line
passthrough — no presentation logic lives in the compiled string:

```php
<?php echo app(\NyonCode\LaravelPackageToolkit\Support\PackageAssets::class)->tags(); ?>
```

It is an **alias for the toolkit's `@packageAssets`**, kept because it is already in
consuming apps' layouts and a minor release is no place to break a `<head>`. Note the
widened meaning: the no-argument form renders every *toolkit* package that declared
entries, not only the four wireStack ones — which is what a layout wants anyway, and
the whole argument for the aggregate.

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
| render | `PackageAssets` (toolkit) | the `<script>`, its attributes, the CSP nonce, the aggregate |
| fall back | `Bundle::servedByRoute()` (core) → `hasAssetFallback()` | the package's own asset route when nothing is published |

Nothing in wire-core registers, renders or copies. What it still owns is the
declaration's shape (`Bundle`) and the routes behind the fallback.

### How this got here

The split above was drawn against toolkit **2.3**, which had a mirror and no
renderer — "the toolkit cannot render a tag" was the whole reason wire-core carried
an `AssetManager`, a `Js` value object and a registry of its own. **2.4 added a
renderer**, which retired that reason and left three capabilities as the argument for
keeping ours (`fcb2c28`). Toolkit **2.4.2** closed all three:

| Kept ours for | Closed by |
|---|---|
| rendering every installed package from one line | `@packageAssets` with no argument |
| falling back when nothing is published | `hasAssetFallback()` |
| a remote/CDN URL as an entry | withdrawn — nothing here ships from a CDN, and we do not intend to |

So the stack migrated. `AssetManager`, `Js`, `Contracts/Asset` and
`AssetRegistrationException` are gone; `Bundle` replaced them at about a tenth of the
size, and `FloatingAssets` stayed as the facade a dozen partials already ask by name.

**Two things were given up, deliberately.**

- **The stale-publish warning.** `AssetManager` used to `console.warn` when
  `public/vendor` held a copy older than the shipped one — the case where a page
  loads last release's JavaScript against this release's markup. It cannot be rebuilt
  on this side: it needs each entry's absolute path, which the toolkit does not
  expose, so the only ways back are a parallel registry (the thing this removed) or
  core learning which packages exist downstream (forbidden outright). It belongs in
  the toolkit's renderer, next to `PublishedAssets::isStale()`, which already knows
  the answer and is not asked. **Worth raising as a toolkit issue.**
- **`loadedOnRequest()`.** It had exactly one user, `wire-core-chart.js`, filed as
  the heavy optional class. It is 671 bytes of Alpine registrar around the app's own
  `window.Chart`, and delivering a registrar late is the one thing ADR 0024 forbids —
  so it now ships with the rest, and the concept has no remaining caller. TipTap, the
  genuinely heavy case, was never in the registry and still is not: the field
  delivers it.

One thing to know if you touch `Bundle`: **declaring `entries:` while the per-surface
partials still emit their own tags means two tags per bundle.** Harmless only because
the `registered` guard makes the second execution a no-op — which is a reason the
guard is load-bearing, not a reason to rely on it.

`hasViteAssets()` remains the one toolkit feature with no counterpart here and a real
consumer benefit: an app on Tailwind currently has to point `@source` at this repo's
Blade markup by hand or watch half the classes get purged. Not adopted.

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

- **Lazy delivery is for heavy optional bodies, never for a registrator.**
  Per-component lazy delivery of an interaction controller is the practice that
  produced the original defect. There is no longer a builder for it: TipTap, the one
  genuinely heavy case, simply is not an entry, and the field that needs it delivers
  it. `wire-core-chart.js` used to be filed here and was not heavy — 671 bytes of
  registrar — so it ships with the rest.
- **A bundle split moves the factory; it does not move the registration idiom.**
  When `wireFillHandle` left `wire-core-dropdown.js` (ADR 0025 § step 10), its
  registration had been one line inside that bundle's registrar — which already
  had the `window.Alpine` branch above. The new entry got the line and not the
  branch, so it registered on `alpine:init` alone and every
  `x-data="wireFillHandle()"` on a `wire:navigate` hop evaluated against an empty
  registry, killing Alpine for the whole data region. Nothing server-side sees
  it: the tag is delivered, the bundle contains `alpine:init`, and the asset
  test that names the factory passes. `verify-spa-navigate.mjs` is the only gate
  that catches it, so **run it whenever an entry point is added or split**, and
  give the new entry a test on the idiom's shape, not just on its contents.
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
- **Octane.** `PublishedAssets::flush()` runs on `RequestTerminated` — one memo now,
  not two. Without them a worker alive across a deploy keeps emitting
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
| ship a new bundle | source + build script + `Bundle::make('file.js')` in the provider's `hasAssets(entries: [...])` |
| new package joining the stack | `hasAssets('dist', entries: [...])` with `Bundle::make()`, `hasAssetFallback(Bundle::servedByRoute(...))`, and `Bundle::serve($package, self::ASSETS_PATH)` from `bootedPackage()` — never a hand-written route |
| a surface needs a tag | `@packageScripts('wire-table', 'wire-table-live.js')` — never hand-write the `<script>`, which drops the attributes and the nonce |
| make a bundle lazy | leave it out of `entries:` and have the surface deliver it, as the TipTap field does. Bodies only, never registrators |
| change delivery/mirroring | the toolkit (`PublishedAssets`, `MirrorsPackageAssets`), not wire-core |

---

## Tests To Run

```bash
composer test:core        # PublishedAssetsTest, WireStackScriptsTest, DropdownAssetTest,
                          # ChartAssetTest, CopyAssetTest, FloatingAssetsTest,
                          # OctaneRequestTerminatedTest
composer test:table       # SelectionAssetTest, RecordActionAssetTest, LazyTableAssetsTest,
                          # FillHandleAssetTest
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
