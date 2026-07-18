# Render optimization audit — 6-thread deep pass (2026-07-17)

Follow-up to [`render-engine-htmlable-first.md`](render-engine-htmlable-first.md)
(§1–§6 landed). This is a **line-by-line audit across the whole render surface**
(core / forms / table / sortable) run as six parallel threads, looking for what the
first initiative did **not** cover. Every finding carries file:line, frequency, and
an honest severity. Where a fact was found by more than one thread independently it
is marked **[cross-validated]** — those are the highest-confidence items.

## Empirical frame (measured, not estimated)

A realistic table — 4 columns + 2 row actions + selection, rendered across the
desktop **and** mobile layouts — costs, via the §1 wildcard-composer fuse:

- **~12 `view()->render()` per row** (≈240 for a 20-row page). This is the N×View
  ceiling; the dominant absolute cost is the per-cell Blade view render itself.
- **0 queries per row** — flat at 1 regardless of row count for plain columns. The
  data layer is clean; there is **no new framework N+1** in the ordinary display
  path (thread D confirmed relation *display* columns are `with()`-eager-loaded).

So the headroom is almost entirely on the **render** side, and it splits into: the
big deferred lever (§7 per-column cell skeleton), plus a long tail of
**column-static work recomputed per cell** and **uncached component/partial renders**
that the audit maps below.

## Confirmed clean — do NOT chase these

- **Query path**: single memoized planner pass (`cachedQuery`/`cachedRecords`),
  `SummaryBatch` ≤2 queries, sub-row eager-load, memoized selection/group partitions,
  pagination count strips subqueries. No new N+1. (thread D)
- **Sortable**: drag handle rendered once, injected as JS, cloned client-side — zero
  per-row view render. (thread F)
- **Charts** (line/pie/bar Chart.js): data → JSON, `@once` script, one `<canvas>`.
  Optimal. (thread F)
- **Per-cell partials** (`text.blade.php`, `button.blade.php`, `dropdown-item`):
  contain zero `config`/`route`/`__`/`app` — they consume PHP-prepared data.
  Htmlable-first is working as intended. (thread E)
- **`__()` row-invariant lookups**: explicitly declined in §5 (translator = keyed
  array lookup; hoisting is churn for no measured gain). Re-confirmed. (thread E)

---

## Tier 1 — icon PHP pipeline + hot-path icon migration (thread A) + one real bug

**SUPERSEDED (2026-07-18): the `@icon` Blade directive was the wrong shape.** A
directive is a template-compiler construct that still puts presentation (size/class)
in the view — against Rule 1 (PHP is the source of truth). It was replaced by a plain
global **`icon()` helper** (`packages/core/src/helpers.php`, composer `files` autoload)
that returns `IconManager::render(...)` — `{!! icon('outline:chevron-right', 'w-3 h-3') !!}`.
The directive is removed; all its call sites now use `{!! icon(...) !!}`; the
`IconManager::render()` / `ResolvedIcon::toSvg()` gained an `array $attributes` argument
so Alpine-bound icons (`x-show`/`::class`) also come from PHP (no `<x-wire::icon>`).
`<x-wire::icon>` remains the **external, consumer-facing** API only. Pinned by
`IconHelperTest` (was `IconDirectiveTest`). See memory `icon_helper_not_directive_2026_07_18`.

**Originally done (2026-07-17).** Built the core `@icon($name, $size, $class, $label)`
Blade directive (`WireCoreServiceProvider::bootFoundation`) — compiled to
`app(IconManager::class)->render(...)`, a memoised string, **zero view renders**;
byte-identical to `IconManager::render()` and order-equivalent to `<x-wire::icon>`. Migrated the 6 hot + 3 warm table icons
(`index.blade.php:764,1032,1060`, `sub-row-toggle:12`, `copyable:18,21`,
`sub-rows:85,87,91`) to `@icon`. **Fixed the `sub-rows.blade.php:85/87` unterminated-
tag bug** as part of the migration (verified: the subrows preview — which has an
active default sub-sort — renders 0 literal `<x-wire::icon` and clean `<svg>`s).
Fuse `TableIconRenderTest` asserts the per-row icon-component render count is **0**
(was 3×R: row-select + copyable clipboard/check) and does not grow with rows. Full
core+table suites green. `<x-wire::icon>` stays the consumer API; the 8 attribute-
forwarding exceptions were left as components (a directive can't emit Alpine bindings).
Remaining: forms toolbar icons (rich-editor 10×) — deferred to a forms PR.

---

### (original audit)

The user's principle: **`<x-wire::icon>` is a consumer API only; core must render
icons via `IconManager` directly.** It is also a perf item — each `<x-wire::icon>`
is a full Blade class-component **view render** (`Foundation/View/Icon.php:31` →
`view('wire-core::foundation.icon')`); IconManager's cache memoizes the SVG *body*,
**not** that view render. So each one in a loop is one uncached render the fuse counts.

Inventory: **75** `<x-wire::icon>` in core render paths. Of the table hot path,
**6 hot + 3 warm are cleanly convertible** (none forward Alpine attrs onto the
`<svg>`):

| file:line | freq | icon |
| --- | --- | --- |
| `index.blade.php:764` | desktop per-row | row-select check |
| `index.blade.php:1032,1060` | mobile per-row | sub-row chevrons |
| `sub-row-toggle.blade.php:12` | desktop per-row (`@include`) | chevron |
| `copyable.blade.php:18,21` | **per copyable cell — O(rows×cols)** | clipboard + check |
| `sub-rows.blade.php:85,87,91` | per expanded sub-header | sort chevrons |

**The clean canonical fix — a core `@icon($name, $size, $class, $label)` Blade
directive** delegating to `IconManager->render()` (cached string, 0 view renders).
Covers ~67/75, including all 6 hot + 3 warm; accepts dynamic PHP args so
`:name="$x->getIcon()"` converts too. It also tidies the §2/§3 inline
`app(IconManager)->render()` calls (filter-chevron, etc.).

**8 attribute-forwarding exceptions** (Alpine `x-bind`/`::class`/`x-show` merged onto
the `<svg>`) stay as `<x-wire::icon>` (routes through `IconManager::resolved()`) —
all one-shot chrome (`infolists/entries/text.blade.php:62,63`, `schema/section:59`,
`searchable-select:227,320`, `forms/layouts/section:38`, `forms/repeater:69`,
`forms/tags:187`). A directive can't emit those; leaving them costs nothing on the
row fuse.

**Bug (fix regardless of perf): `sub-rows.blade.php:85,87` have unterminated
`<x-wire::icon ...` tags** (no `/>`) spanning `@else`/`@endif`. When a sub-column is
actively sorted the chevron markup is malformed. Latent — needs expanded + sortable +
actively-sorted sub-rows, and it's visual, so server tests never caught it. Migrating
this partial to `@icon` fixes it incidentally; otherwise close the tags.

Forms toolbars are the other icon-heavy spot: `rich-editor.blade.php` renders **10**
`<x-wire::icon>` per instance every roundtrip; convert static toolbar icons to
pre-resolved strings (thread F, F3).

---

## Tier 2 — Column-static work recomputed per cell (threads B, C, E)

The `$columnMeta` precompute (`index.blade.php:85-100`) is the blessed pattern; it
**stopped short** of several column-static values that `renderCell` still rebuilds
per cell. All are **invalidation-free** (no per-record input) → memoize per column or
fold into `$columnMeta`. BC-safe, internal.

| # | Finding | file:line | freq | threads |
| --- | --- | --- | --- | --- |
| 2a | **`resolveView()` runs `view()->exists()` per cell** — resolved name is column-static | `HasView.php:58,70` | **every subclass × every row** | C, D |
| 2b | **`getTextClasses()` rebuilt per cell** (match + implode; static inputs) | `Column.php:619,784-814` | V×R | **B, C, E [cross-validated ×3]** |
| 2c | **ButtonColumn: full class string + icon rebuilt per row, `isDisabled` evaluated twice** | `ButtonColumn.php:320,371-397,402-413` | O(button rows); closure disabled runs 2× | C |
| 2d | Per-subclass static size/shape `match` (Badge/Icon/Toggle/Image/Stacked/Poll) | `BadgeColumn:39, IconColumn:102, ToggleColumn:129-130, ImageColumn:185-187, StackedColumn:255, PollColumn:414,559` | per cell of that type | C |
| 2e | `renderIcon()` color-`match` + `app(IconManager)` per cell for a static icon → memoize `iconHtml` | `Column.php:819-824` | V×R (icon cols) | C, E |
| 2f | `PollColumn` double-renders a view per row (`parent::renderCell` inside poll view) | `PollColumn.php:378,438` | 2× per poll cell | C |
| 2g | `TextInputColumn` static input classes/attrs rebuilt per row; `method_exists` per row | `TextInputColumn.php:738,763,609` | per editable cell | C |

**2a and 2b are the headline** — 2a hits *every* column subclass on *every* row (one
`??=` cache kills it for rows 2..N); 2b is triple-confirmed and folds straight into
`$columnMeta`. **2c** wants an `Action::staticRender`-style cache (ButtonColumn is the
one common cell type with no static cache and a double closure-eval).

---

## Tier 3 — Un-migrated §3/§5 patterns in forms & infolists (thread F)

The same anti-patterns §3/§5 removed from the table row path **survive** elsewhere:

- **3a — spinner double-hop → cached `Primitives::spinner()`. DONE (2026-07-17).**
  `component-action.blade.php:23` and `affix-action.blade.php:20` now echo
  `app(Primitives::class)->spinner('h-4 w-4', '…')` instead of `@include`. Byte-identical
  (same partial, same data — now cached once per request); core (1604) + forms (882)
  suites green. Saves the repeated spinner render, multiplied by `rows×actions` in
  RepeatableEntry / repeaters.
- **3b — `entry-actions` call-site guard. DONE (2026-07-17).** All six entry blades
  (`text/icon/image/key-value/list/color`) now wrap the include in
  `@if($field->hasActions())`, so an action-less entry renders **zero** `entry-actions`
  views (was one empty render each). Byte-identical (the partial's own guard rendered
  nothing anyway); fuse `EntryActionsGuardTest` (0 renders action-less, 1 with actions).
- **3c — field-wrapper double-`@include`. EVALUATED, NOT REDUCED — byte-identity blocks
  it (2026-07-17).** The wrapper is a start/end *sandwich* (input between), so it cannot
  drop below its two renders **byte-identically**: a single combined partial differs by
  Blade's view-edge whitespace trimming (the standalone `end` render trims its leading
  whitespace to `</div>\n`; the same content mid-file does not), and a PHP-built wrapper
  needs whitespace-porting. Rendering start+end separately from PHP is still two renders
  (no win). So the standard-mandated byte-identity guard **caught that no clean
  reduction exists** — same fragility class as the inline-edit skeleton. Per the
  standard's discipline (decline fragile), left as-is; the win here would require
  accepting HTML-normalised (not byte) equivalence + a 24-blade/`toHtml` refactor with
  repeater/nested render-path risk, disproportionate to forms' non-hot-loop ROI.
- **3d — DateTimePicker closed-calendar chrome. DONE via eager-cheap (2026-07-17).**
  The audit proposed the §6 analog — gate the closed calendar behind `<template x-if>`.
  Applied the **standard's rule instead** (`eager-cheap > lazy`): the **8 chevrons moved
  to `@icon`** (cached IconManager strings — 0 view renders vs 8, order-equivalent), and
  `floating-assets` is `@once`. So the per-field cost dropped from ~10 view renders to
  ~2, byte-identically (forms suite 882 green; fuse `DateTimePickerIconTest` = 0
  icon-component renders, chevrons still in the DOM). **The `x-if` gate itself was NOT
  applied**: now that the render is cheap, gating only defers the small closed-calendar
  DOM — a *lazy* move that the standard reserves for proven payload/DOM-size-bound cases
  (a repeater of many date fields), and it carries the §6 `x-if`+teleport fragility +
  open latency + JS dependency. It stays available as an opt-in if a real DOM-weight
  bottleneck shows up, per the standard.

Only **Repeater** and **RepeatableEntry** truly inherit the N×View ceiling; both
already handle the hard parts (deep clone, eager-load), so their remaining cost is
dominated by 3a/3b/3c above — which is why fixing those pays off most there.

---

## Tier 4 — `group.blade` config/MobileSheet cascade (thread E)

`group.blade.php` renders ~2–3× per row (actions column + mobile group + row context
menu). Each render re-derives **table-static** sheet state: ~4 `config()` reads
(`usesSheetOnMobile`/`getMobileBreakpoint`, computed at `:51` **and again** inside
`getDropdownConfig()` at `:27`) + ~7 `MobileSheet::px/panel/motion/backdropHide`
`match`/`in_array` (`:66,79,81,85,90`). Same value every row.

- Memoize the resolved breakpoint string + sheet bool on the `HasSheetOnMobile`
  instance (nulled by its setters) → `R×config` collapses to 1 per group.
- Static per-token cache in `MobileSheet` (the `IconManager::$renderCache` shape).
- `getDropdownConfig()` can reuse what the `:51` `@php` already computed.

MED — biggest frequency multiplier among the pure-lookup findings.

---

## Tier 5 — Micro-hoists into the `index.blade.php` preamble (threads B, D)

Cheap, BC-safe, `$columnMeta`-style hoists. Individually trivial; free to bank.

- `$table->getPrimaryKey()` dispatched ~3R (`:81,681,933`) → hoist once. (B4)
- `getGroupValue()` computed ~3× per record (`:685,688,689`) → precompute a
  `$groupValues` array once. [cross-validated B5 / D4]
- `$component->isRowExpanded($recordKey)` called 2× per desktop row (`:777,828`) →
  compute once per row. (B6)
- `getRowContextMenuHtml` allocates `new TableActionClickResolver` per row
  (`Table.php:1506`) → reuse the preamble `$actionClick`. (B7)
- Inline the `sub-row-toggle` partial (`:775`) — near-static, only `recordKey`/
  `isExpanded` vary → removes one `@include` render per row. (B3)

---

## Tier 6 — Query cleanups (thread D)

- **6a — closure-only relations lazy-load per row (real-world N+1).** A relation
  touched only inside a `displayUsing`/`url`/`color` closure (`fn ($s,$r) =>
  $r->company->name`) has no relation path, so it is never eager-loaded → lazy
  `$r->company` per row. Not a framework bug (closures aren't introspectable; Filament
  has the same limit), but the **largest remaining N+1 vector in practice**. **DONE
  (2026-07-17).** Added `Column::loadRelations(string|array)` — the hint is collected in
  `TableQueryService::buildQuery()` after the plan and applied as `$query->with(...)`
  (additive; Laravel de-dupes against planned loads). Measured
  (`ColumnLoadRelationsTest`, belongsTo over 30 rows): with the hint the query count is
  **flat (≤3) regardless of row count**; without it, ~30 extra lazy loads. Documented in
  the boost `wire-table` Performance guideline (hint or eager-load on the base query).
  Full table suite (1283) green; phpstan/pint clean. **Highest real-world impact of the
  whole audit.**
- **6b — non-batchable summaries re-read the same column per summary.** Median/
  variance/stddev, `when()`-restricted, and dotted summaries fall out of
  `SummaryBatch` and each runs a full `pluck($query,$column)`
  (`SummaryCalculator.php:266`). Memoize the plucked collection per `(column, when)`
  → J reads collapse to 1 per column. MED, bounded to uncommon summary types.
- **6c — `clampPageToBounds` double-paginates on last-page overflow**
  (`WithTable.php:687-718`). Only in the delete-last-row-on-page case; rare. Note only.

---

## Deferred / structural (design decision, not a hoist)

- **Mobile dual-layout renders every cell + action a second time server-side**
  (`index.blade.php:931-1077`), only when `->stackedOnMobile()` is on. The viewport
  shows one layout; the hidden one is wasted V×R + R×(4+N). Eliminating it means moving
  one layout to a client swap (behavior change). The single largest raw cost **when
  stacking is enabled**. (thread B1)
- **§7 per-column Htmlable cell skeleton** — the dominant absolute cost (the per-cell
  `view()->render()` itself). Already the render-engine plan's pending §7; Tier 2's
  `resolveView`/`getTextClasses` memos are cheap standalone down-payments that do
  **not** require the full skeleton.

---

## Suggested order (fuse-guarded, ranked by impact × safety × reach)

1. **Tier 1** — `@icon` directive + migrate the 6 hot + 3 warm table icons + fix the
   `sub-rows.blade.php:85/87` bug. **Done (2026-07-17)** — 0 per-row icon-component
   renders (was 3×R); bug fixed; `IconDirectiveTest` + `TableIconRenderTest` guard it.
2. **Tier 2a + 2b** — `resolveView` per-column cache + `getTextClasses`→`$columnMeta`.
   Highest-reach, triple-confirmed, invalidation-free. Prove via the §1 fuse (per-row
   cost unchanged — these are PHP, not view renders) + a micro-bench.
3. **Tier 3a + 3b** — spinner double-hop → `Primitives`, `entry-actions` call-site
   guard. Proven §3/§5 patterns, low risk, real forms/infolist wins.
4. **Tier 2c** — ButtonColumn `staticRenderCache`.
5. **Tier 4** — `group.blade`/`MobileSheet` memo.
6. **Tier 5** — micro-hoists.
7. **Tier 6a** — closure-relation `->loadRelations()` hint + docs (biggest real-world),
   then **6b** summary pluck memo.
8. **Tier 3c/3d** (forms field-wrapper, DateTimePicker) — larger, own PRs.
9. **Deferred** — mobile dual-layout decision; §7 cell skeleton.

Everything in Tiers 1–6 is internal and BC-safe. Nothing here is a documented BC break.

---

## Completeness audit + bod 1 (2026-07-18)

A 6-thread line-by-line audit checked whether each initiative optimisation was
applied **everywhere it should be**. Verdict: the hot table surface is complete,
but adjacent surfaces (infolists, forms) kept the same anti-patterns. The real
data-scaling gaps were the **infolist entries** — no render memo existed there at
all, and they still emitted `<x-wire::icon>` per row.

**Bod 1 shipped (2026-07-18):**

- **`icon()` helper replaces the `@icon` directive** (see the Tier 1 supersede note).
  Global helper in `packages/core/src/helpers.php`; `IconManager::render()` /
  `ResolvedIcon::toSvg()` gained `array $attributes` for Alpine-bound icons; the
  directive and `IconDirectiveTest` are gone (`IconHelperTest` replaces it). All
  data-scaling `<x-wire::icon>` in infolist entries (`icon`/`text`/`list`), the audit
  trail (per event), and the stats/bar-chart widgets (per item) migrated to
  `{!! icon(...) !!}`; the copyable clipboard/check icons use the `$attributes` path.
- **Infolist render memo** — new canonical core concern
  `Foundation\Concerns\HasViewRenderCache` (mirrors table `HasView::renderViewCached`,
  but request-scoped/static so `RepeatableEntry`'s per-row clones collapse). Wired via
  `Component::toHtml()`; `IconEntry`/`BooleanEntry` and badge-mode `TextEntry`/`BadgeEntry`
  declare a `renderCacheSignature()`; plain/copyable text and any entry with actions
  return `null` (content-driven / per-record identity). Guards: render-count fuse
  (60 rows × 3 states → 3 renders) + byte-identity, in `InfolistEntryRenderCacheTest`.

**One-shot `<x-wire::icon>` sweep — done (2026-07-18).** All remaining
`<x-wire::icon>` across core/forms/table render paths (83 sites: modals, pagination,
toasts, tabs/sections/wizard, toolbar, empty states, forms fields, rich-editor toolbar,
etc.) migrated to `{!! icon(...) !!}` via a transformer that maps component props to
`icon()` args byte-identically (size defaults to `w-4 h-4` exactly as the prop did). The
7 genuine attribute-forwarding tags (Alpine `:class`/`x-show`/`x-cloak`/`x-bind:class` on
the `<svg>`: `searchable-select`, `repeater`, `tags`, `schema/section`, `forms/layouts/section`,
`file-upload`) were hand-converted using the `$attributes` argument. **Zero `<x-wire::icon>`
now remains in any render path** — only the component definition (`foundation/icon.blade.php`),
which is the external consumer API. Core+table+forms suites green (1613 / 1283 / 884).

**Remaining (optional, lower value):** the two MED spinner `@include` twins
(`forms/button.blade`, `file-upload.blade`); floating-assets `@once` convention on 6
per-instance includes (not a bug — `@assets` already de-dupes); per-row action-policy N+1
(framework-triggered, query lives in user policy — document only); 6b summary pluck memo.
