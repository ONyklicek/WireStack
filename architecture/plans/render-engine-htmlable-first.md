# Render engine — Htmlable-first hot path

Preparation plan, not a spec. Every count here is a **static render count** read
straight off the code paths — deterministic for a table of `R` rows, `V` visible
columns and an action group of `N` executable actions — not a wall-clock
benchmark. There is no wall-clock number in this document on purpose: the fuse in
§1 has to land before any speed claim can be made. Re-read the anchors before
acting; they were verified on 1.11.0 across five parallel passes on 2026-07-17.

Yardstick — the central principle of the WireStack render engine:

> **Static once, dynamic per row.** Build cell/action markup once per column as an
> `Htmlable` skeleton; per record splice only the state that differs row to row.
> `view()->render()` per cell/action/row is the anti-pattern — it is the primary
> source of "N×View".

This sits under [`AI_CODING_STANDARD.md`](../../AI_CODING_STANDARD.md)'s
Htmlable-first rule ("reusable markup owns its PHP via `getXHtml()` htmlable
methods; Blade only consumes"). The performance fix is therefore **not** a licence
to hardcode SVG or bypass `IconManager` — see the trap below.

## The trap this plan exists to avoid

Two traps, both of which turn a green suite into a false "done":

1. **Optimising without a fuse.** The whole engine's cost is `view()->render()`
   count, and **nothing tests it**. A future PR that drops one `@include` back into
   the row loop passes every existing test and only shows up on a customer's
   Debugbar. `SummaryBatchTest`, `WithTablePerformanceTest`,
   `WithTableSubRowsTest` count *queries* for specific features; none counts view
   renders or asserts render/query invariance as `R` grows. **Build the render
   counter first (§1). Everything else is measured through it.**

2. **Buying speed by breaking Htmlable-first.** The fast way to kill per-row icon
   resolution is *not* to inline `<svg>` into Blade — that is already an
   anti-pattern present twice (`actions/action.blade.php:53`,
   `columns/partials/filter-chevron.blade.php:6`) and it breaks re-themability. The
   speed comes from **caching the `IconManager` output as `Htmlable` once per
   column**, not from going around the manager. Same for primitives: resolve once,
   reuse — do not hand-roll the markup.

**The premise is already proven in the codebase.** `index.blade.php:85-94` builds
`$columnMeta` once per render to stop the `<td>`-class getters being re-called per
cell — the exact "static once" shape, but stopping at *metadata*. This plan
extends that same shape from metadata to **markup skeleton**. It is not a new idea
imported from outside; it is the existing `$columnMeta` pattern finished.

**Already landed, do not re-solve:**

- `Action::render()` / `renderForDropdown()` short-circuit to `''` when
  `!canExecute($record)` (`core/src/Actions/Action.php:156-170,184`), and
  `ActionGroup::render()` returns `''` for an empty visible set
  (`ActionGroup.php:479-490`). Non-executable actions cost no view render. ✅
- `renderCell()` returns `''` when `!canView() || !isVisibleForRecord()`
  (`Column.php:603-605`). ✅
- Table-level `Table::lazy()` returns `collect()` until ready
  (`WithTable.php:654-657`), so the placeholder render costs zero rows. ✅
- `$columnMeta` memoises static per-column `<td>` metadata
  (`index.blade.php:85-94`). ✅

---

## The measured hot path

For a table of `R` rows and `V` visible columns, one page render costs:

| Source | `view()->render()` calls | Anchor |
| --- | --- | --- |
| Cells (baseline) | **V × R** (+ V×R `view()->exists()`) | `HasView.php:70-73`, `index.blade.php:786-802` |
| — responsive column | ×3 that column | `Column.php:570-591` |
| — `SplitColumn` (k children) | ×(1+k) that column | `SplitColumn.php:190-197` |
| — `PollColumn` | ×2–3 that column | `PollColumn.php:435,340,575` |
| — expandable sub-rows | + (subRows × subCols) × R | `index.blade.php:1024-1031` |
| Action group per row | **R × (4 + N)** | `ActionGroup.php:466-490`, `group.blade.php:47-96` |
| Primitives per editable/button cell | 5 `@include` resolutions / row | see §3 |

`route()` is called `2R + 3` times for floating-assets (§4). Icons are re-resolved
`≥1` time per cell/action per row (§2). None of it is cached beyond `$columnMeta`.

Three corrections to the originating best-practice note, so the plan does not chase
a phantom:

- **`@filemtime()` is not "dozens of disk-stats".** It is called `2R+3` times, but
  PHP's per-request stat cache collapses repeated `filemtime()` on the *same path*
  to **one real syscall**. The redundancy is the `route()` call + view-include
  overhead, not the syscall. (§4)
- **`route()`/`asset()` are not in the cell loop.** Only `__()` / `Trans::get()`
  are (§5). The `route()` cost is entirely floating-assets (§4).
- **The action-group fix is a conscious API change, not a silent one.** Deferring
  menu markup changes first-paint DOM; ship it opt-in, never as a new default (§6).

---

## §1. The regression fuse — do this first

**Done (2026-07-17) — `packages/table/tests/Unit/Concerns/TableRenderCountTest.php`.**
Three assertions, `6` in the suite.

The instrument turned out **better than a `HasView::renderView()` counter**, which
this plan first proposed. `renderView()` only funnels *column cells* — it misses
action renders and, crucially, `@include`s, which is the exact regression the fuse
exists to catch. Instead the test registers a **wildcard view composer** (`View::composer('*', …)`):
`View::renderContents()` calls `callComposer()` on every view instance
(`vendor/laravel/framework/src/Illuminate/View/View.php:189`), and `@include`
compiles to `make()->render()`, so the composer sees *every* render — cells,
actions, partials, `<x-…>` components. One seam, whole engine.

It asserts, on a table of `V` plain columns, no actions:

- **linearity** — `renders(12 rows) - renders(4 rows)` divides evenly by the row
  delta (no per-row constant hiding), and the per-row quotient is **exactly `V`**
  (one `tables.columns.text` render per cell). This is the pinned tripwire.
- **column scaling** — `+2` columns × `10` rows = `+20` renders, proving cost is
  attributed to the cell loop, not fixed chrome.
- **per-row sub-render detection** — a `copyable` cell adds a sub-view per cell; the
  test measures the copyable↔plain *gap* at 5 and 10 rows and asserts it **doubles**,
  i.e. the extra work is `N×view`, not a one-off.

**Sabotage-verified**, as the standard demands (`SummaryBatchTest` was checked the
same way): injecting one `@include` into `tables/columns/text.blade.php` flipped the
linearity and column-scaling assertions red (per-row cost `V → 3V` via the spinner
double-hop), then was reverted. Note the third assertion is a *delta* between two
renders, so it is deliberately immune to a global scale change and stays green under
that sabotage — it targets a different axis on purpose.

Two things worth knowing before extending it: Testbench refreshes the application
between tests, so the wildcard composer does not leak across cases; and the counter
includes `<x-…>` components, so a "plain" cell must avoid icons/urls/copy to hold the
one-render-per-cell invariant (the fixture uses `displayUsing(fn () => 'v')`).

This landed before §2–§6 so each optimisation is proven, not asserted.

---

## §2. Icons — already owned by the IconManager cache (measured 2026-07-17)

**No per-column cache written — the fuse turned this item on its head.** This plan
first said to "cache the resolved `Htmlable` on the column/action." Measured against
the code, that cache **already exists at the canonical owner**, and adding a second
one on each column would duplicate it — a direct `CLAUDE.md` canonical-owner breach.

The facts:

- `IconManager` is a **singleton** (`WireCoreServiceProvider.php:77`), so its state
  lives for the whole request.
- `IconManager::render()` holds a **per-request render cache** keyed by
  `name\0size\0class\0label` (`IconManager.php:197-204`). The expensive work —
  `resolve()` + `toSvg()` — runs once per distinct icon appearance; every later row
  with the same icon+size+color is an array-lookup, not a re-render.
- `Column::renderIcon()` (`Column.php:817-822`) returns a **string**, appended into
  `text.blade.php`'s output — it is **not** a `view()->render()`, so an icon column
  costs the same one view render per cell as a plain one. The §1 fuse never sees it.

So the per-row icon cost the plan feared is already collapsed to a keyed lookup. A
state-varying column (`BadgeColumn` `icons()` map, `iconUsing`) still benefits — rows
sharing a state share a cache key; distinct states are distinct entries, never a
collision. **Nothing to add.**

What landed instead is a **lock on the behaviour §2 now depends on**:
`packages/core/tests/Unit/Foundation/Icons/IconManagerTest.php` gained three tests —
the render cache memoises a repeat render (reflection-checked on `renderCache`),
flushes when the icon vocabulary changes, and the container binds the manager as a
singleton. If any of those regress, the reason not to add per-column caches is gone,
and CI says so.

**The two Htmlable-first `<svg>` breaches move to §3, with a measured reason.** The
hardcoded spinner in `actions/action.blade.php:53` is in the **per-row** path
(every action button renders it). Routing it through `@include('wire-core::partials.spinner')`
now would *add a view render per action per row* — a fuse regression — because §3's
resolve-once primitive does not exist yet. The chevron in
`columns/partials/filter-chevron.blade.php:6` is header-only (once per filterable
column, not per row), so it is cost-neutral but shares the same fix. Both are done in
§3 where the resolved-once primitive makes the cleanup free instead of a regression.

---

## §3. Primitives — resolve once, kill the double-hop

**Done (2026-07-17).** Measured before and after with a per-row fuse
(`TablePrimitiveRenderTest`): a button cell went **3 → 1** view renders per row, an
editable cell **5 → 1**. The removed renders were pure N×View — byte-identical
markup rebuilt per row.

The waste, as measured: `columns/partials/spinner.blade.php` was a pass-through that
`@include`d `wire-core::partials.spinner` — **two renders per occurrence** — landing
in the row hot path at `button.blade.php:62` and `text-input-editable.blade.php:70`,
plus `check-icon` at `:75` (which itself rendered an `<x-wire::icon>`, another
render). For an editable + button table that was 8 per-row renders (5 + 3) of
record-invariant chrome.

What landed:

- **`Foundation/View/Primitives`** — a container **singleton** (mirroring
  `IconManager`) that owns record-invariant primitive markup. `spinner($class,
  $wireTarget)` renders `wire-core::partials.spinner` **once per distinct parameter
  set** and memoises the string; `successCheck()` delegates to `IconManager` (already
  memoised). The Blade partial stays the single markup source and vendor override
  point — it is rendered at most once per request, not per row.
- **Consumers rewired to a cached string**: `ButtonColumn` and `TextInputColumn` pass
  `spinnerHtml`/`checkHtml` in their view data (the same `iconHtml`-as-data pattern
  `text.blade.php` already uses); the blades echo `{!! $spinnerHtml !!}`. `PollColumn`
  calls `Primitives::spinner()` directly. The button/editable spinners were already
  target-less (an ancestor `wire:loading` gates them), so the string is truly
  invariant.
- **Double hop deleted**: `columns/partials/spinner.blade.php` and
  `columns/partials/check-icon.blade.php` are removed (no remaining references).

**The two §2 `<svg>` breaches, closed here as planned:**

- The action button spinner became a **wrapping `<span wire:loading wire:target="…">`**
  carrying the per-record target, with the canonical cached spinner string inside.
  This is what keeps the swap from adding a per-row render: the target moved to the
  span, so the spinner itself stays cacheable. `Action` supplies `spinnerHtml` +
  `loadingTarget` in its button render data.
  *(Note 2026-07-17: a concurrent refactor then consolidated the action views into
  core — the old `wire-table::tables.actions.action` is gone and the rendered path is
  now `wire-core::actions.button`, `Action::getRenderData()` → `toButtonRenderArray()`.
  That refactor **kept this design**: `button.blade.php` echoes `{!! $data['spinnerHtml'] !!}`
  from `app(Primitives::class)->spinner('w-4 h-4')`. The §1/§3 fuse stayed green across
  it — which is exactly what the fuse is for.)*
- `columns/partials/filter-chevron.blade.php` — the hardcoded chevron became
  `IconManager->render('chevron-down', …)`. Header-only, so cost-neutral; it removes
  the breach and makes the chevron themeable.

**Tests**: `TablePrimitiveRenderTest` pins the per-row counts *and* asserts the
markup still reaches the DOM (fewer renders must not mean an empty cell), including
the row-action spinner path; `PrimitivesTest` pins the memoisation and the singleton.

**Not touched, deliberately**: `divider` and `sheet-grabber` primitives live in the
action-group dropdown / sheet paths, not the cell row loop — they belong with §6
(`->lazyMenu()`), not here.

---

## §4. Floating-assets — memoise the URL, emit once

**Done (2026-07-17).** Measured with a targeted composer that counts renders of the
floating-assets partial specifically (`TableFloatingAssetsRenderTest`): per-row
floating-assets renders went from **O(rows) → 0 per row**. A table's dropdown
scaffolding no longer re-emits as rows grow.

The waste, as it was: `floating-assets.blade.php` computed `route()` + `@filemtime()`
inline with no `@once`, and was `@include`d per row from two per-row sources — the
action-group dropdown (`group.blade.php:18`, reached in both the desktop and mobile
layouts) and the row context menu (`index.blade.php:710`) — plus the O(1) header
sites (filters toggle, column toggle, action-modal).

What landed:

- **`Foundation/View/FloatingAssets`** — a container **singleton** owning the
  dropdown asset URL. `url()` resolves the route + cache-busting mtime **once per
  request** and memoises the string. The blade logic (a `@php` block doing
  `route()`/`@filemtime()`) moved out of the partial into testable PHP — the same
  "logic in PHP, blade consumes" move as §3.
- **`@once` at the two per-row call sites** (`group.blade.php:18`,
  `index.blade.php:710`) — the scaffolding is identical for every row, so it now
  emits once per request instead of once per row per layout. The O(1) header sites
  were left as-is; with the URL memoised they cost a cached lookup.

**The `filemtime` syscall was correctly *not* "fixed"** — PHP's stat cache already
collapses repeated calls on the identical path to one real syscall (the originating
note overstated it). The real win was the per-row `route()` + view-include overhead,
now `O(1)`.

**Tests**: `TableFloatingAssetsRenderTest` asserts zero floating-assets renders per
row for both an action-group dropdown and a row context menu (and that the
scaffolding still emits at least once, so the asset keeps loading);
`FloatingAssetsTest` pins the URL memoisation and the singleton. Note
`Livewire::test(...)->html()` does **not** contain `@assets` output (Livewire
extracts it), so the emit is verified by render count, not by string-matching the
`<script>`.

---

## §5. Per-request lookups in the cell path

**Done (2026-07-17).** `Column::renderCell()` built the `copyMessage` fallback
`Trans::get('wire-table::messages.copied')` **on every row, unconditionally**
(`Column.php:628`) — an array-literal value that evaluated even for non-copyable
columns, which never use it. It is now guarded by `$this->copyable`, so a
non-copyable column resolves it **zero** times; a copyable column already had
`$copyMessage` resolved by `copyable()`, so the fallback is a belt-and-braces guard.

**Measured** with a pass-through translator decorator that counts resolutions of the
`…copied` key (`ColumnCopyMessageTest`): a 5-row non-copyable column went from 5
resolutions to 0. Sabotage-checked — reverting the guard flips it back to 5, red.

**The `__()` calls in the row markup were left alone**, as the plan said to: they hit
Laravel's translator over a loaded catalogue (a keyed array lookup), the §1 fuse
counts view renders (so it does not flag them), and hoisting them into the `@php`
preamble is churn on a hot file for no measured gain. **`route()`/`asset()` are not in
this path** — confirmed, nothing to do there.

---

## §6. Action-group menu — opt-in `->lazyMenu()`

`ActionGroup::render()` builds the **entire dropdown** — scaffolding **plus every
menu item's full markup** (icon SVG, label, `wire:click`, shortcuts, `data-testid`)
— server-side per row, teleported to `body` and hidden only by
`x-show`/`x-cloak`/`style="display:none"` (`group.blade.php:47-96`,
`getDropdownItemsHtml()` at `ActionGroup.php:466-477` → `dropdown-item` view per
item at `Action.php:178-192`). The menu markup is **never gated behind open
state**. Cost: **`4 + N` Blade view renders per group per row** (action-group +
core group + floating-assets + sheet-grabber + N items; +1 badge), i.e. `R×(4+N)` —
the largest per-row multiplier after cells, all for menus closed by default.

**Done (2026-07-17), opt-in.** `ActionGroup::lazyMenu()` renders only the trigger
plus a serialized item spec; the menu is built client-side and the per-row
`dropdown-item` Blade render count drops to **0** (measured by `TableLazyMenuRenderTest`:
lazy = 0, eager > 0). Default is unchanged (eager).

How it works:

- **`ActionGroup::getDropdownItemSpecs($record, $click)`** serializes each item to
  data — label, resolved icon SVG, menu-item classes, and, for a button, the click
  **method + args split out** of the resolver's expression (`parseClickExpression`),
  so the client never evaluates a string (CSP-safe). Dividers, links, disabled items
  and nested groups (shipped as a pre-rendered fragment) are all represented.
- **`group.blade.php`** passes the spec into `wireDropdown(config, items)` and, in
  the lazy branch, renders the menu with an Alpine `<template x-for>` instead of
  `getDropdownItemsHtml()`.
- **`dropdown.js`** captures `this.$wire` in `init()` — while the component is in its
  live DOM position, *before* the panel teleports to `<body>` — and `runAction(item)`
  calls `this._wire[item.method](...item.args)`. This is the crux: it **dodges the
  teleport `$wire` gotcha**, where `$wire` may not resolve from a teleported node.

**CDP-verified** (`workbench/scripts/verify-lazy-menu.mjs`, preview variant
`table-actions-group-lazy`), 8/8: no menu items visible before open, items built
client-side on open, item lives in the teleported panel with its label + icon, and —
the load-bearing check — clicking a lazily-built **Delete** fires `$wire.openActionModal`
from the teleported button and the confirm modal opens. Screenshot confirms the lazy
menu is visually identical to an eager one.

Known limitations (documented, acceptable for an opt-in): keyboard shortcuts on lazy
items and `wire:click` modifiers (e.g. `.debounce`) are not wired client-side; nested
groups inside a lazy menu are not themselves lazy.

**It stayed an opt-in API decision, never the default** — it changes first-paint DOM
and the click mechanism (a captured `$wire` call instead of a server `wire:click`).

---

## §7. The big one — per-column Htmlable skeleton (P0, do last)

`HasView::renderView()` (`HasView.php:70-73`) is `view($this->resolveView(...),
$data)->render()`, and `resolveView()` (`:51-63`) adds a `view()->exists()` probe —
**both per cell per row**, no cache. Every column subclass renders its own partial
per row (`Column.php:618`, `BadgeColumn:37`, `IconColumn:100`, `BooleanColumn:73`,
`ToggleColumn:124`, `SelectColumn:102`, `ImageColumn:181`, `ButtonColumn:322`,
`StackedColumn:253`, `TextInputColumn:665/688`). Baseline **V×R**, worse on the
paths in the table above.

Extend `$columnMeta` from metadata to a **compiled per-column skeleton**: build the
static chrome (wrapper classes, resolved icon from §2, copy scaffolding,
prefix/suffix) once per column as an `Htmlable` with a placeholder for state; in the
row loop splice only `content` (and per-record bits: `url`, `description`,
`visibleForRecord`). `V + R×(cheap splice)` instead of `V×R` renders.

**Highest impact, highest risk** — it touches every column subclass and the
Htmlable convention, and the responsive/split/poll/sub-row paths each need their own
skeleton shape. It goes **last**, behind the fuse (§1) and after §2–§3 have removed
the per-row icon/primitive work the skeleton would otherwise bake in. Stage it one
column family at a time; the fuse proves each family.

### Proof-of-concept — `Column::renderCellFast()` (2026-07-17)

Landed as an additive PoC (not wired into production) with a proof + measurement test
(`TextColumnSkeletonTest`). It renders `tables.columns.text` **once** into a skeleton
with a content token, then splices `e($state)` per row; it falls back to `renderCell()`
when a per-record structural bit is present (url / copy / description-closure).

Measured (2000 cells, `TextColumn`):

| | wall-clock | view renders / 100 rows |
| --- | --- | --- |
| `renderCell` (view per cell) | ~262 ms | 100 |
| `renderCellFast` (skeleton splice) | **~55 ms (≈5×)** | **1** |

**Byte-identical** to `renderCell()` across 7 configs (plain, sized/weighted/coloured,
icon, html, static tooltip, static description, compound) × 7 content shapes (escaping,
edge whitespace, unicode, empty, html) — 49 cases — plus the fall-back configs. So for
display columns the answer to *"what would disprove rule 2"* is settled: **the skeleton
reproduces `renderCell` byte-for-byte and is ~5× cheaper, one render per column instead
of V×R.** Rule 2 holds; per-cell `view()->render()` is just a slow implementation of it.

**The rule-2 boundary is the interactive cell.** `TextInputColumn` (inline edit) is a
per-cell view render (measured: 101 renders / 100 cells) and is **not** skeletonable by
content-splice: its per-record variation is *structural* — the input's value, `wire:key`,
and the per-record `wireEditableCell` commit config (value / version / statePath) — not a
single spliceable token. It needs either multiple per-record splice points into that
Alpine state, or stays a per-cell render, or moves to a client-side template. Same for
the state-branching display types (Responsive / Split / Poll). Those are where §7 must be
evaluated per column-family, not assumed.

**Wired to production for TextColumn (2026-07-17).** The row loop
(`index.blade.php:811/814/968/1009/1050`, `sub-rows.blade.php:111`) now calls
`renderCellFast`; it skeletonises base-`Column`/`TextColumn` cells and falls back to
`renderCell` for subclasses (via `supportsCellSkeleton()` — reflection-cached per
class) and non-skeletonable columns. The §1 render fuse was rebaselined to the new
invariant: skeletonable text cells add **zero** view renders per row, column count is a
one-time O(columns) cost (not O(rows×cols)), and non-skeletonable (copyable) cells still
render per row (the fuse still trips). Verified in a live render (0 token leak, cells
correct); full core (1604) + table (1271) suites green; byte-identity guarded by
`TextColumnSkeletonTest`.

### Two §7 mechanisms (2026-07-17)

Column families split by *what varies per row*, and each gets the matching mechanism:

1. **Content columns → skeleton-splice** (`TextColumn`, above). Structure fixed, only
   the content string varies → one skeleton + splice. ~5×, done + in production.
2. **State-driven columns → data-payload render memo** (`renderViewCached`, HasView).
   The markup is a function of a low-cardinality state (value + colour + icon), so the
   **view render is memoised by its data payload** — rows sharing a state reuse one
   render. Keying on the actual `$data` (not a "pure function" assumption) keeps it
   byte-identical. **`BadgeColumn` wired (2026-07-17)**: measured **3 view renders for
   300 rows / 3 states** (was 300) via `BadgeColumnMemoTest`; Columns suite green
   (byte-identity holds). It activates in the row loop through `renderCellFast`'s
   subclass fall-back, so no loop change was needed.

   Honest bound: the memo eliminates the *view render* (the N×View target), but the
   per-cell data-building (state → colour/icon, `iconHtml` via IconManager) still runs
   per cell, so the wall-clock is ~2× on Badge (a cheap view). Pushing further is a
   Tier-2 job — memoise the resolved `iconHtml`/colour per state, which the render memo
   is orthogonal to. `renderViewCached` is the reusable owner for the state-driven
   families: Icon/Boolean/Toggle/Select adopt it the same way.

**Icon + Boolean adopted (2026-07-17).** `IconColumn` and `BooleanColumn` render their
markup purely from a low-cardinality state (no per-record identity in the data), so both
switched `renderView` → `renderViewCached`. Measured: **Icon 3 renders / 300 rows**
(3 states), **Boolean 2 renders / 300 rows** (`BadgeColumnMemoTest`); Columns suite green.

**Toggle + Select are the boundary, NOT memoised** — measured and skipped on purpose:
- `ToggleColumn` passes `recordKey` into its view (inline-edit commit target) → the data
  is unique per row → the memo would never hit and only add hash overhead (a net loss).
- `SelectColumn` — the display branch already returns a plain `e($value)` string (no view
  render at all, nothing to memo); the editable branch passes the whole `$record` into
  the view (inline-edit) → per-record, same boundary as `TextInputColumn`.

So of the four, two (Icon/Boolean) win from the data memo and two (Toggle/Select) are the
inline-edit boundary — the discriminator is **"is the render a function of a low-
cardinality state, or does it carry per-record identity?"** State-driven columns memoise;
interactive/per-record ones stay a per-cell render.

Remaining boundary families: Responsive / Split / Poll and the inline-edit trio
(TextInput / Toggle / Select) — evaluate each individually, never blanket.

### Inline-edit multi-token skeleton — evaluated (2026-07-17)

Prototype `TextInputColumn::renderEditableCellFast()` + guard/measurement test
(`TextInputColumnSkeletonTest`). It is **not** finite-state (value/key/version are
per-record, unbounded) but its **structure is fixed** — exactly three per-record
values — so a multi-token skeleton splice applies.

**Verdict: viable, byte-identical, ~2.7× — but fragile.** Measured **byte-identical to
`renderCell` across a hostile matrix** (quotes, `& < >`, apostrophe, backslash, unicode,
tab/newline, JSON, `<script>`, empty) *and* a special-char UUID key; **one view render
per column** for 100 rows; **540 ms → 200 ms** for 2000 cells.

The fragility is real and is the finding:

- **The value appears in two encodings from one variable** — `e(json_encode($value))`
  in the Alpine config and `e($value)` in `data-server-value`. This forces distinct
  **control-char sentinels** per position and position-aware re-encoding at splice.
- **The record key is int-cast by the model**, so a string sentinel silently becomes
  `0` — caught only by the byte-identity test (a numeric sentinel is required). This is
  exactly why the measurement/guard is mandatory, not optional.
- It relies on `e()` being **context-free** (char-by-char) so `e($key)` spliced into
  `"tic-<sentinel>-<col>"` equals `e("tic-<key>-<col>")`.
- The skeleton is **tightly coupled to the partial's exact structure** — any new
  per-record datum or changed encoding in `text-input-editable.blade.php` silently
  breaks byte-identity, so it MUST stay behind the strict guard test.

Recommendation: **skeleton not wired** pending a call on the trade-off — it is a
genuine ~2.7× for editable-heavy tables, but far more brittle than the display-column
mechanisms. Toggle/Select would each need the same bespoke sentinel analysis.
Responsive/Split/Poll remain unevaluated.

**Tier-2g taken instead (2026-07-17, the low-risk path).** `buildInputClasses` /
`buildInputAttributes` are now memoised per column (pure functions of config; the
instance is rebuilt each Livewire render, so no staleness). Byte-identical — Columns
suite green (memo does not change output). **Honest magnitude: it saves ~8 µs/cell**
(measured 200k×: 252 ms memoised vs 1843 ms recomputed) — the column-static
data-building — which is **~3 % of the ~274 µs/cell** editable render. The remaining
~97 % is the per-cell `view()->render()` itself, which **only the (fragile) skeleton
eliminates**. So for editable cells the safe optimisations are marginal by nature: the
cost *is* the view render, and removing it means taking on the skeleton's brittleness.
That is the real boundary finding — display cells get a clean 5×/N×View win, editable
cells do not without the escaping gymnastics.

---

---

## §8. The client half — DOM nodes, not view renders (2026-08-07)

Everything above counts `view()->render()`, which is the **server** cost. Measuring
the same tables in a browser found the other half, and it is not smaller:

| preview | DOM nodes | morph | round-trip |
| --- | --- | --- | --- |
| `table-overview` (5 rows) | 1 073 | 20 ms | 42 ms |
| `table-selection-gestures-paged` (20) | 5 130 | 65 ms | 73 ms |
| `table-selection-gestures` (40) | 9 555 | **102 ms** | 87 ms |

Morph time is linear in node count (~10 µs/node) and at 40 rows it already **exceeds
the round-trip that carried the HTML**. Of those 9 555 nodes, 4 374 were
whitespace-only text nodes and 3 146 were Livewire's morph markers — **79 % holding
no content**. Server-side the same picture: for 50×10, cell content was 4.8 KB of a
521.7 KB payload (0.9 %) and 7.9 ms of a 51.8 ms mount; the rest is scaffolding.

Two mechanics matter when acting on this, and both are counter-intuitive:

1. **A run of whitespace between tags is ONE text node**, however long. So shortening
   indentation saves bytes but *not* nodes — only removing the run removes the node.
2. **Every `@if`/`@foreach` costs two comment nodes.** A conditional in the row loop
   is 2×rows nodes the morph walks.

### §8a. Payload fuse — **Done (2026-08-07)**

`TablePayloadFuseTest` — the client-side counterpart to §1. Counts bytes, whitespace
runs and comments as a **slope per row**, so fixed chrome drops out. Baseline for
three plain text cells: **4 214 B, 21 whitespace nodes, 24 marker nodes per row**.

### §8b. Copyable delegated — **Done (2026-08-07)**

The copy affordance was an Alpine component per cell: `x-data`, an inline multi-line
`x-on:click`, two `<template x-if>` icons and a feedback span with six transition
attributes. Measured **2 042 B and 11 whitespace nodes per cell** — a 500-cell table
shipped ~1 MB of it.

Now a plain `<button data-copy>` plus one document listener (`record-copy.js`) and
one feedback pill per page. **2 042 → 943 B (−54 %), 11 → 2 nodes (−82 %)**, pinned
by the fuse and verified in a browser by `verify-copy-cell.mjs` (16/16, including
that the clipboard really receives the value and that delegation survives a morph).

What is left is mostly the inline clipboard SVG — **~690 B of the 943**. An icon
sprite (`<symbol>` once + `<use>` per cell) would take the cell to ~250 B, and it
would apply to every icon in the table, not just this one. Deliberately **not** taken
here: it is a change to icon delivery with a much wider blast radius than one
partial, and it belongs with §2's `IconManager` ownership rather than smuggled into
a copy-button change.

### §8c. The skeleton made canonical, and multi-slot — **Done (2026-08-08)**

The skeleton was a `private const CELL_TOKEN` and a `str_replace` inside `Column`.
It is now `Foundation\View\Skeleton` (core, `Htmlable`), compiled once and filled per
row through **one `strtr()` pass** — which is also the correctness half: `strtr`
does not re-examine what it just substituted, so a value that happens to contain
another slot's sentinel is left alone where sequential `str_replace` calls would
substitute it twice.

One hole became several, and that is the whole point: `actionUrl()`, `copyable()`,
`description(Closure)` and `icon(Closure)` no longer drop the column onto the
per-cell render. Measured A/B in one session, 50 rows × 10 columns:

| per cell | before | after | |
| --- | --- | --- | --- |
| bare `TextColumn` | 0.006 ms | 0.007 ms | +1 µs — the shape checks |
| `->actionUrl(fn)` | 0.146 ms | 0.010 ms | **14.6×** |
| `->copyable()` | 0.218 ms | 0.010 ms | **21.8×** |
| `->description(fn)` | 0.113 ms | 0.010 ms | **11.3×** |

| whole-table mount | before | after |
| --- | --- | --- |
| 0/10 columns with `actionUrl()` | 22.8 ms | 24.3 ms |
| 2/10 | 38.4 ms | 26.4 ms |
| 5/10 | 58.9 ms | 24.8 ms |
| 10/10 | 78.8 ms | 28.2 ms |

So the **3.5× cliff a single everyday feature used to cause is gone** (78.8/22.8 →
28.2/24.3), paid for with ~1 µs per cell on the plain path — about 0.5 ms on a
500-cell table. That trade is deliberate and is not worth a second code path.

**Why this subset is safe where the inline-edit skeleton was not.** The fragility
found there was *one variable under two encodings* (`e(json_encode($v))` and
`e($v)`) plus a key the model int-cast. Here each slot is **one position with one
encoding** — url inside `href="…"`, copy value inside `data-copy="…"`, description
inside a `<p>`, icon as raw markup. Delegating the copy button (§8b) is what removed
the last `@js()` and made copy a clean slot; doing §8c first would have needed the
same escaping gymnastics.

**Structure vs value.** A slot substitutes a value, never a shape. A url present on
one row and absent on the next is two shapes, so skeletons are cached per shape —
O(shapes) renders, proven by `TextColumnSkeletonTest` ("one skeleton per shape when
a record turns a part off" → exactly 2 renders for 100 rows).

Guards: byte-identity across 7 configs × 7 contents × 7 hostile ids (quotes,
ampersands, `<x>`, unicode), a shared-column pass proving one column serving many
records never leaks the previous row's values, a no-sentinel-leak assertion, and
`SkeletonTest` in core for the primitive's own contract. `TableRenderCountTest` also
gained back a real negative control — the fuse now trips on `TextInputColumn`,
because copyable stopped being a fallback and could no longer prove it.

### §8d. The row — cells and the `<tr>` assembled, not laid out — **Done (2026-08-08)**

Two changes in `index.blade.php`, both moving record-invariant markup out of the row
loop and into a resolve-once:

1. **The `<td>` opening tag is built once per column** into `$columnMeta[…]['open']`.
   Every attribute on it (padding, wrap, border, alignment, responsive classes,
   `data-testid`, `data-column`, author attributes) is column-static — only what sits
   *between* the tags varies by record — so a 50×10 page was re-emitting the same ten
   strings five hundred times through Blade interpolation. The cells are now
   concatenated in PHP, which also removes the per-cell `@foreach`/`@if` and the
   whitespace they laid out between the tags.
2. **The `<tr>` opening tag is compiled once per table** into a `Skeleton`. Its four
   conditionals — keyboard nav, ARIA role, selection, the row-class binding — are
   properties of the *table*, not of a record, so the row has exactly one shape and
   those `@if`s were re-deciding a settled question once per row. The record key
   arrives through **two slots**, because it appears under two encodings: `e()` in
   `data-row-key` / `wire:key`, and `Js::from()` inside the Alpine expressions. One
   slot, one position, one encoding — the same rule as §8c.

Measured on a 6-row × 4-column fixture (a Badge column, an `actionUrl` column, a
`copyable`+`description(Closure)` column) across five table configurations:

| variant | bytes | whitespace runs | markers |
| --- | --- | --- | --- |
| plain | 67 081 → **49 854** (−26 %) | 348 → 243 | 256 → 198 |
| selectable | 86 832 → **69 593** (−20 %) | 444 → 339 | 260 → 202 |
| actions | 86 054 → **68 827** (−20 %) | 500 → 395 | 364 → 306 |
| recordUrl | 72 917 → **51 730** (−29 %) | 396 → 243 | 256 → 198 |
| full | 111 731 → **90 532** (−19 %) | 644 → 491 | 368 → 310 |

Those totals carry the table's fixed chrome, which did not change. The honest per-row
number is the fuse's slope: **4 214 → 1 823 B/row (−57 %), 21 → 11 whitespace nodes,
24 → 16 markers** for three plain text cells.

**How it was proven.** There is no slow path to compare a row against, so the guard
was a golden master: the full rendered HTML of all five configurations captured
before the change and compared after, masking only Livewire's per-render component id
and insignificant whitespace. All five came back **identical in structure and in
every attribute value** — the diff is exactly the whitespace that was the point. The
2 009-test table suite (much of which asserts markup) is green, and the interaction
drivers were re-run over the rebuilt rows: `copy-cell` 16/16, `column-surfaces`
20/20, `fill-handle` 26/26, `record-actions-dual` 5/5, `selection-gestures` 75/77 and
`gesture-lab` 22/28 — the last two at exactly their pre-change scores (verified by
stashing the view and re-running; their failures are older and unrelated).

**Marker count barely moved, and that is expected**: Livewire does not inject morph
markers into `@if`s that sit *inside* an HTML tag, so the four conditionals on the
`<tr>` never had any. The 24 → 16 drop is the per-cell `@foreach`/`@if` alone.

### §8e. The selection cell — a partial rendered once, spliced per row — **Done (2026-08-08)**

The most expensive thing left in the row, and it was not a column: a selectable table's
`<td>` cost **2 251 B and 10 whitespace text nodes per row** — *more than the entire rest
of a three-column row* (1 823 B / 11 nodes). Four nested tags over eleven lines of Blade,
with the record key interpolated four times.

It is now compiled once per table by `Table::getSelectionCellSkeleton()` and filled with
the record key per row. Measured: **2 251 → 1 172 B (−48 %) and 10 → 0 whitespace nodes**.
On the five-configuration golden master (6 rows) that is **−6 474 B** on every selectable
variant — −9.9 % of the whole `selectable` page, −12.1 % of `summaries`.

**The template did not move — only the render did.** The markup lives in
`tables/partials/selection-cell.blade.php`, rendered once with `Skeleton::slot('keyJs')`
standing in for the key. A first attempt assembled the same HTML as a PHP string in the
view preamble; it was byte-identical and green, and it was still wrong — it destroys the    
`vendor:publish` override point and hides markup from every Blade tool. That is now a
binding rule in `AI_CODING_STANDARD.md` § Rendering: **always `Htmlable`, always Blade.**
Whitespace is the template's job: the tags that must touch are written touching, while
whitespace *between attributes* stays laid out and costs nothing.

One slot, because the key appears four times under one encoding (`Js::from()`, inside
four Alpine expressions) — unlike the `<tr>`, which needed two.

Two ownership moves came with it, so the partial cannot drift from the view that hosts
it: `Table::getCellPadding()` / `getHeaderPadding()` own the density map (the view was
re-deriving it from `isCompact()`), and `Table::getSelectionCheckIcon()` owns the tick for
both the row cell and the card view's select-all.

**How it was proven.** Golden master over five configurations (plain, selectable, ranges,
summaries, full) — all five **structurally identical**, masking only Livewire's per-render
ids and insignificant whitespace, with `plain` as the untouched control. Two fuses gained
an assertion: the payload fuse budgets the cell at <1 250 B and **exactly zero**
whitespace nodes, and the render fuse pins the new partial at **O(1) renders — the
selectable-vs-plain slope is 0 per row**, which is the trade the partial has to earn (it
costs a view render where inline markup cost none). Suites green: table 2 011, core 2 036,
sortable 87, integration 44; PHPStan clean. Drivers over the rebuilt cell: `copy-cell`
16/16, `record-actions` 14/14 + `record-actions-dual` 5/5, `column-reorder` 12/12,
`gestures-off` 25/25, `fill-selection` 30/30, `mobile-selection` 13/13. `selection-gestures`
and `gesture-lab` were re-run against the pre-change view in the same session and came back
with **identical failures** — both are flaky here for older, unrelated reasons.

**A trap found on the way, worth knowing:** a published copy of the package views under
`vendor/orchestra/testbench-core/laravel/resources/views/vendor/wire-*/` shadows
`packages/*/resources/views/` in every Pest run *and* in the workbench preview server. It
had been published from an identical working tree, so it was invisible — until a view was
edited, at which point the edit silently did nothing. Delete it if a Blade change appears
to have no effect.

### §8f. The context-menu panel — and the marker that turned out to be load-bearing — **Done (2026-08-08)**

The teleported right-click panel was laid out by Blade on every row: **1 659 B and 14
whitespace text nodes per row for a one-item menu**, nearly all of it identical from row
to row, because the panel holds no per-row Alpine state (one `wireRecordActions`
controller on the `<tbody>` drives every row's menu by record key).

`tables/partials/record-context-menu.blade.php` is now compiled once by
`Table::getRowContextMenuSkeleton()` with two slots — the key, and the item markup that
`getRowContextMenuHtml()` still renders per record, as it must (an action can be hidden
for one row and visible for the next). `Table::getRowContextMenuPanel()` owns the "this
row has no visible action" case. Measured: **1 659 → 982 B (−41 %) and 14 → 6 whitespace
nodes**; on the golden master, **−4 062 B over six rows** on each menu variant (−6.7 % of
the page), with the no-menu variants byte-identical.

**The finding worth more than the bytes.** The obvious next step was to delete the
`@if` around the panel — the string is already empty when there is no menu, so the
conditional looked like two morph markers per row bought for nothing. Doing it saved
55 B/row, kept the golden master structurally identical, and **broke the column
reorder**: the header reordered and the body did not. Both fuses stayed green;
`verify-gesture-lab.mjs` caught it (28/28 → 27/28, reproduced three times and bisected
against the pre-change view). The markers are the block boundary morphdom needs when a
row's children can change between renders, which is exactly what a reorder does.

So the `@if` stays, with the reason written at the call site. This also answers the
"stripping morph markers from a compiled skeleton" idea in Still open below: it is not
free, and the row loop is where it is least free.

**Verification.** Golden master over six configurations (plain, selectable, menu,
menu-group, menu-conditional, full) — all six structurally identical, marker counts
unchanged, `plain` and `selectable` byte-identical as controls; `menu-conditional`
(action visible on half the rows) keeps 3 panels of 6 either way, so the emptiness
decision is preserved. The payload fuse budgets the panel at <1 050 B and ≤7 whitespace
nodes; the render fuse pins the scaffolding at **zero** extra renders per row (the slope
is exactly the 1 dropdown-item per row, which is the per-record part). Suites: table
2 013, core 2 036, sortable 87, integration 44; Pint and PHPStan clean. Drivers:
`gesture-lab` 28/28, `record-actions` 14/14 (including the three context-menu checks) +
`record-actions-dual` 5/5, `gestures-off` 25/25.

**Second trap found and fixed.** `tests/Integration/AssetPublishingEndToEndTest.php` runs
`{package}:install`, whose `publishViews()` writes into the testbench skeleton's
`resources/views/vendor/wire-*/`. Nothing removed them, and Laravel resolves a published
view first — so **one Integration run poisoned every later test run and preview in that
working copy**, rendering a frozen snapshot instead of the package views. A Blade edit
then does nothing, silently. The test now cleans up after itself.

### §8g. The sub-row expander cell, and what the actions cell is worth — **Done (2026-08-08)**

The expander cell was the row loop's last `@include` — the N×View shape §1's fuse exists
to catch — at **1 044 B, 8 whitespace text nodes and a view render per row**, for a button
whose only per-record part is the key in one Alpine expression.

It has exactly **three shapes** (no toggle, toggle collapsed, toggle expanded), so
`tables/partials/sub-row-cell.blade.php` is compiled once per shape by
`Table::getSubRowCell()` and each row splices its key. Measured: **1 044 → 760 B (−27 %),
8 → 1 whitespace nodes, zero view renders per row**. `sub-row-toggle.blade.php` now takes
`$keyJs` (pre-encoded for the Alpine expression) instead of `$recordKey`, the same
convention the selection cell uses; its own inter-tag whitespace was closed too, because
a partial compiled into a skeleton emits its layout on *every* row.

**The `@if` is inside the partial on purpose.** Compiling it into each shape bakes the
morph-marker pair into the skeleton, so every row still emits them — marker counts are
unchanged across all eight golden-master variants. That is the §8f lesson applied rather
than re-learned.

**The actions cell was evaluated and deliberately left as Blade.** Its `<td>`/`<div>`
layout was closed up (**1 470 → 1 155 B, 16 → 10 whitespace nodes per row for one
action**), but the `@foreach` stays: an action can be non-executable for one record and
not the next, so the button list genuinely changes between renders — exactly what §8f
showed the markers are for. What remains is the button markup itself
(`wire-core::actions.button`), which belongs to the action-render work, not to this loop.

**Verification.** Golden master over eight configurations (plain, subrows, subrows-mixed
with children on half the rows, subrows-expanded, subrows-flat, actions with a
per-record-visible action, actions-start, full) — all eight **structurally identical**,
**marker counts unchanged everywhere**, `plain` byte-identical as the control. Fuses: the
render fuse pins the expander at zero renders per row; the payload fuse budgets both cells
including their *marker counts*, so a future "cleanup" that drops a conditional trips a
test instead of a customer's reorder. Suites: table 2 016, core 2 036, sortable 87,
integration 44; Pint and PHPStan clean. Drivers: `subrows-expansion` 17/17,
`subrow-filter` 10/10, `gesture-lab` 28/28, `record-actions` 14/14 + dual 5/5,
`column-reorder` 12/12, `column-surfaces` 20/20, `copy-cell` 16/16.

### §8h. The sibling rows, and a marker fuse that should have existed all along — **Done (2026-08-08)**

Three rows can sit *beside* a body row, and none of them is bounded: group by a date and
there is one header per row; expand by default and every row carries a sub-rows panel.
Measured as a per-row slope against a same-columns baseline:

| sibling | before | after |
| --- | --- | --- |
| group header | 318 B, 6 nodes, 1 view render/group | **222 B, 0 nodes, 1 render/table** |
| group subtotal (with header) | 1 674 B, 28 nodes | **1 222 B, 4 nodes** |
| expanded sub-rows panel | 3 019 B, 37 nodes | **2 113 B, 8 nodes** |

The group header became a one-slot skeleton (`Table::getGroupHeaderRow()`). The other two
are the row loop's biggest *partials*, and their per-record content is real — a nested
table of children, per-group summary values — so what was taken out of them is the
indentation: **whitespace between two tags is a DOM text node**, and these partials are
emitted once per group / per expanded row. On the golden master a table with expanded
sub-rows is **−10.7 %**, a richer one (sortable + filterable sub-rows, a limit, sub-row
summaries) **−20.4 %**.

**The trap, and the fuse it produced.** Closing up the whitespace silently cost six
morph markers per panel. Livewire injects `[if BLOCK]` / `[if ENDBLOCK]` around each
conditional by matching directives with a regex, and it **skips ones it cannot classify**
— gluing a directive straight onto a Blade comment (`--}}@if(…)`) loses the *opening*
marker while the closing one is still emitted. The result is unbalanced block boundaries:
the exact condition §8f showed breaks a morph, arrived at by a change that looked like
pure whitespace, with the golden master structurally identical and every existing fuse
green.

So `TablePayloadFuseTest` gained **`emits balanced morph markers in every table shape`**,
which asserts `[if BLOCK]` count === `[if ENDBLOCK]` count across nine shapes. It is
sabotage-verified (re-gluing one directive turns it red, naming the shape) and it asserts
the *invariant* rather than the authoring rule, so it catches the next way to lose a
marker too. Every marker count on the golden master is now identical before and after.

**Verification.** Eight golden-master configurations (plain, grouped, grouped with a
hostile label, grouped+summaries, grouped+everything, sub-rows, rich sub-rows,
sub-rows-empty) — all structurally identical, **marker counts identical**, `plain`
byte-for-byte unchanged. Suites: table 2 019, core 2 036, sortable 87, integration 44;
Pint and PHPStan clean. Drivers: `subrows-expansion` 17/17, `subrow-filter` 10/10,
`record-actions` 14/14 + dual 5/5, `column-reorder` 12/12, `copy-cell` 16/16.

**`gesture-lab`'s reorder check was failing, and it was the driver, not the engine** —
verified first by stashing the entire working tree and running at `HEAD`, where it failed
identically. `verify-gesture-lab.mjs` simulated the drag with
`document.querySelectorAll('thead th')`, which spans **both** header rows on this table
(it has a column-filter row), so "move after the last `<th>`" dropped the dragged column
*into the filter row* — something no real drag can do. `initColumnSortable` binds
SortableJS to `thead tr`, the first row, and its `onEnd` reads the new order off that same
row, which no longer held the dragged column: the controller sent a short list, and the
body never matched the header. Scoped to the first header row, the check passes.

The driver's remaining failures were a second, unrelated fault: **headless Chrome
backgrounds a window it never shows and throttles the renderer**, so a driver sitting in a
dead `sleep()` watches a page that is barely running. It needs **two** fixes, and each
covers a failure the other does not:

- **Polling instead of sleeping** (`waitFor`) — a CDP evaluation wakes the JS thread, so
  the wait keeps the page moving. This is what anything *awaited* needs: the modal those
  checks are about opens ~700 ms after the click on a settled page, yet `sleep(1600)`
  reported it missing on every run of unmodified code.
- **The Chrome flags** (`--disable-background-timer-throttling` and the two beside it) —
  because polling does **not** wake `requestAnimationFrame`. Without them an Alpine leave
  transition never finishes: `verify-selection-gestures`' "Escape closes the shortcut
  help" failed with the modal reporting `show: false` and `display: block` at the same
  time. This is what anything *animated* needs, so it lives in `scripts/lib/cdp.mjs` for
  the whole harness.

Applying only one of the two is worse than useless — flags alone flipped
`verify-gestures-off` red, polling alone left the Escape check failing. With both:
**`gesture-lab` 28/28** (was 22/28) and **`selection-gestures` 77/77 in 75 s** (was 75/77
in ~150 s against a 180 s cap it regularly hit, which is why it kept being killed).

**The flags went to all 64 drivers** (55 spawn Chrome themselves, 9 share
`scripts/lib/cdp.mjs`) because the same fault was hiding in seven more: every `*-sheet`
driver and `select-floating` failed "opens on desktop" — a teleported panel whose *enter*
transition never finished, the mirror image of the Escape case — and `record-active-row`
failed five modal/keyboard checks. A full sweep went from **56/64 to 63/64**.

The last one, `spa-navigate`, was two driver bugs of its own: the same both-header-rows
drag as `gesture-lab`, and a `headerCols()` that read `data-sortable-column` — an
attribute the sortable controller adds and every morph wipes, so straight after a reorder
it legitimately reads empty. Reading the server-rendered `data-column` instead fixed it.
**The sweep is 64/64.**

**Third publishing trap, fixed.** The same Integration test that was leaving published
*views* behind (§8f) was also leaving published **migrations and config**: `{package}:install`
timestamps its migrations, so every run added another copy — **75 files had accumulated**
— and from the second run on, `migrate:fresh` dies on "table already exists", leaving the
workbench database half-migrated and unseeded. Every CDP driver then runs against an empty
preview. The test now snapshots `config/`, `database/migrations/` and
`resources/views/vendor/` before the installs and removes anything new afterwards.

### §8i. The stacked mobile card — the second rendering of every record — **Done (2026-08-09)**

`stackedOnMobile()` renders every record **twice**: the desktop `<tr>`s and the cards are
both in the document and CSS decides which is seen. So the card's layout is emitted per
row on every commit, at any width, and it was the single biggest per-row cost anywhere in
this plan — **4 391 B, 36 whitespace nodes and 22 morph markers per row, 225 % on top of
the row itself.** A `stackedOnMobile()` table shipped 3.25× the payload of the same table
without it.

Closing up the card body (the part inside the `@forelse`; the select-all bar and the
summary footer are O(1) and were left alone) takes it to **2 830 B and 10 whitespace
nodes — −36 % and −72 %**, markers unchanged. On a five-row golden master that is
**−10.5 % of the whole page**, up to −12.8 % on the plain stacked variant.

It stays inline rather than becoming a partial: the card is per-record, so a partial would
add a view render per row — the very thing §1's fuse exists to catch.

**Verification.** Nine configurations (not-stacked, plain, selectable, actions,
collapsed-actions, record-url, subrows, summaries, full) — all **structurally identical**,
**marker counts unchanged in every one**, and `not-stacked` byte-for-byte as the control.
The payload fuse pins the card at <2 950 B, ≤10 nodes and exactly 22 markers. Table suite
2 020, Pint and PHPStan clean; drivers `mobile-selection` 13/13, `gestures-off` 25/25,
`collapse-mobile-actions` 7/7, `phase2-mobile` 5/5, `swipe` and `modal-mobile` clean.

**What is NOT done, and needs a decision rather than a measurement:** the card is still a
second full server-side rendering of the whole page of records. Even at 2 830 B/row that
is more than the row it duplicates (1 950 B), and every byte of it is invisible at
whichever width the reader happens to be at. Making it conditional — one layout server-
side, swapped on a breakpoint change — is an API/UX change of the same kind as §6's
`->lazyMenu()`: it trades a no-JS, no-latency, always-present DOM for half the payload,
and it changes what a consumer gets by default. That is a call for the maintainer, not a
refactor to slip in.

### §8j. The action button — the last N×View — **Done (2026-08-09)**

An action button was **two `view()->render()` calls per action per row** (the button view
and the content partial it includes), measured at 2.06 renders per action per row. Three
actions over twelve rows was 72 view renders, and it was the last N×View left in the
engine.

`Action::render()` now compiles **one skeleton per shape** and splices. What makes it a
one-slot case is that the click expression is the *only* per-record value that reaches the
markup — `recordKey` is in the render array but no view echoes it — and it lands in
`wire:click` and up to three `wire:target`s, every one a Blade `{{ }}` inside an
attribute. One slot, one position kind, one encoding.

**Correctness does not depend on guessing which actions are "simple".** The shape key is
the whole render array minus the two spliced fields, plus the three methods the view calls
on the action directly (`isHidden`, `getLabel`, `getName`). An action whose label, colour,
icon, tooltip, url, disabled state or extra attributes vary by record simply lands on a
different skeleton and is rendered for itself — the split is measured, not assumed
(`ActionButtonSkeletonTest`: a `disabled(fn)` action over twenty rows compiles exactly
two).

Measured: **2.06 → 0 renders per action per row**; the table's render fuse now pins a
three-action table to the same per-row slope as a table with none.

**One deliberate output change**: a compiled skeleton is trimmed, so a button no longer
carries the view file's own leading and trailing newline — one DOM text node per button,
gone. Everything else is byte-identical, and that is asserted rather than asserted-of:
21 shapes × 10 record keys chosen to break naive escaping (quotes, `&`, `<x>`, unicode,
backslash, `'0'`), plus the no-record and default-resolver paths, each compared against
the view rendered directly.

Nothing else in the button changed, so the actions cell stays at 1 155 B and 10 whitespace
nodes per row — that is the button's own markup, which is the action-render plan's
territory, not this one's.

### §8k. The `<td>` and `<tr>` chrome moved into Blade — **Done (2026-08-09)**

§8d built both as PHP strings in the view preamble, which the rule §8e added — *always
`Htmlable`, always Blade* — then forbade. They are partials now:

- **`tables/partials/body-cell.blade.php`** — the whole `<td>`, balanced, with a slot for
  the record's content. Compiled once per column, filled per cell. **Byte-identical**
  across all eight golden-master configurations.
- **`tables/partials/body-row-open.blade.php`** — the row's opening tag. Deliberately not
  a whole row: the row's children each sit behind a conditional whose morph markers are
  load-bearing (§8f), and wrapping them in a slot would swallow those conditionals.

Two things the `<tr>` partial had to be taught, both measured rather than guessed:

- **It is all on one line.** Laid out over eight lines it cost **+50 B per row** — half of
  what §8d won — because whitespace between attributes costs no DOM node but does cost
  bytes, and this tag is emitted once per row. The explanation lives in the comment block
  above, where Blade strips it.
- **A literal space separates each `@if` from the one before it.** `@endif@if(` is
  invisible to Blade's compiler — a directive preceded by a word character never matches —
  and comes out as literal text in the page. Same family as the `--}}@if` trap in §8h.

The cost of that separator is a stray space inside the tag when a condition is false:
**+4 B per row**, no DOM node, no semantics. Everything else is byte-identical, verified by
masking whitespace *inside the `<tr>` tag only* and comparing the eight configurations —
identical every one.

### Still open

- **The stacked card's second rendering** — see the decision above. This is the largest
  single item left, worth roughly another 2 830 B and 10 nodes per row on tables that use
  it.
- **The rest of the row loop.** §8d took the `<td>` and the `<tr>`, §8e the selection
  cell, §8f the context-menu panel, §8g the expander, §8h the sibling rows, §8i the card,
  §8j the action buttons. What still renders per row is the sub-rows panel — one view per
  expanded parent, for a genuinely per-record nested table — and nothing else in the row
  loop.
- **Most CDP drivers still sleep at their waits.** `verify-gesture-lab`,
  `verify-selection-gestures` and one check in `verify-gestures-off` poll; the rest keep
  fixed sleeps. They no longer *have* to change — the anti-throttling flags now cover the
  animated half everywhere — but a check that waits on a Livewire round-trip is still one
  slow response away from a false failure. Convert one when it misbehaves, measuring
  before and after.
- **Stripping morph markers from a compiled skeleton.** ~~Not attempted.~~ **Attempted
  in §8f, on one conditional, and it broke.** The theory was that within one shape the
  block boundaries carry no information the morph can use; in practice a row's children
  *do* change between renders — a column reorder rewrites the cell list — and morphdom
  needs the boundary to pair them. `wire:key` on the `<tr>` was not enough. Any further
  attempt needs a CDP driver that reorders, filters and paginates, not just a fuse.
- **Two copy implementations.** `Infolists/entries/{text,color}.blade.php` carry their
  own copy affordance, unrelated to the table partial. A canonical owner (core
  `Foundation/View`) would let both shrink the same way — see the ownership rule in
  CLAUDE.md.

---

## Suggested order

1. **§1 render-count fuse** — the pin every later step is measured against, with a
   negative check that it trips on an `@include` in the row loop. **Done (2026-07-17).**
2. **§2 icons** — **Done (2026-07-17), by measurement, not code.** The canonical
   `IconManager` singleton + render cache already collapse per-row icon cost; the
   behaviour is now pinned by tests. The two hardcoded SVGs moved to §3.
3. **§3 primitives resolved once + spinner double-hop removed + the two §2 SVGs.**
   **Done (2026-07-17)** — button cell 3→1, editable cell 5→1 view renders/row.
4. **§4 floating-assets `once()` + `@once`.** **Done (2026-07-17)** — per-row
   floating-assets renders O(rows) → 0.
5. **§6 `->lazyMenu()` opt-in** — needs the API/UX sign-off first. **Done
   (2026-07-17)** — lazy dropdown ships 0 `dropdown-item` renders/row; CDP-verified.
6. **§5 lazy `copyMessage`** — mechanical. **Done (2026-07-17)** — non-copyable
   column resolves the default 0×/row (was 1×/row).
7. **§8a payload fuse** — the client-side pin, the same role §1 plays for renders.
   **Done (2026-08-07).**
8. **§8b copyable delegated** — the worst per-cell offender, and the one that made
   copy a clean single-slot value for §7. **Done (2026-08-07).**
9. **§8c canonical multi-slot skeleton** — the display-column subset
   (`actionUrl`, `copyable`, `description(Closure)`, `icon(Closure)`), each one
   position with one encoding, unlike the inline-edit case declined above.
   **Done (2026-08-08)** — 11–22× per cell, and the 3.5× whole-table cliff gone.
10. **§8d row: `<td>` chrome per column + `<tr>` skeleton per table** — last, behind
    both fuses and a golden master. **Done (2026-08-08)** — 4 214 → 1 823 B/row.
11. **§8e selection cell** — the same treatment for the one remaining per-row `<td>`,
    with the markup kept in its partial. **Done (2026-08-08)** — 2 251 → 1 172 B/row
    and 10 → 0 whitespace nodes.
12. **§8f context-menu panel** — same again, plus the negative result on morph markers.
    **Done (2026-08-08)** — 1 659 → 982 B/row and 14 → 6 whitespace nodes; the `@if`
    stays, because removing it breaks the column reorder.
13. **§8g sub-row expander cell** (three shapes) **+ the actions cell evaluated**.
    **Done (2026-08-08)** — expander 1 044 → 760 B/row, 8 → 1 whitespace nodes and the
    row loop's last per-row `@include` gone; actions cell 1 470 → 1 155 B/row with its
    `@foreach` deliberately kept.
14. **§8h sibling rows** (group header skeleton; subtotal and sub-rows panels reflowed)
    **+ the morph-marker balance fuse**. **Done (2026-08-08)** — expanded sub-rows
    −10.7 % (rich −20.4 %), and losing a marker is now a failing test rather than a
    broken reorder.
15. **§8i the stacked mobile card** — the biggest per-row cost in the plan, because it is
    a second rendering of every record. **Done (2026-08-09)** — 4 391 → 2 830 B/row and
    36 → 10 whitespace nodes, −10.5 % of a whole stacked page. Whether that second
    rendering should happen at all is left as a decision, not a refactor.
16. **§8j the action button** — the last N×View. **Done (2026-08-09)** — 2.06 → 0 view
    renders per action per row, one skeleton per shape, byte-identical bar the view's
    own trailing newline.
17. **§8k the `<td>` and `<tr>` chrome into partials** — closing §8d's debt against the
    rule §8e set. **Done (2026-08-09)** — cell byte-identical, row +4 B/row of
    insignificant intra-tag whitespace.

Steps 1–4 are internal and BC-safe. §6 adds one opt-in method. §7 is internal but
high-blast-radius — do not attempt it before the fuse exists.
