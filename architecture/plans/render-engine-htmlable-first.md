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
7. **§7 per-column skeleton** — last, one column family per PR, each proven by §1.

Steps 1–4 are internal and BC-safe. §6 adds one opt-in method. §7 is internal but
high-blast-radius — do not attempt it before the fuse exists.
