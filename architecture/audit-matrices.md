# Audit Matrices

Snapshot date: **2026-07-02** (derived from code after the fixes of that day; branch `1.7.0`).

How to use: see `audit.md` → "The Four Matrix Checks". Cells are a claim about the code **at the snapshot date**. A full audit re-derives every row/column, works all `OPEN`/`UNVERIFIED` cells, and bumps the date. When you add a capability, host, sibling class, or tree consumer, add its row/column here in the same PR.

Legend:

- `OK` — render + dispatch (or behavior) verified by an existing test.
- `OK*` — works via shared code, but no test pins this specific cell.
- `GAP` — confirmed hole; reference the finding.
- `INTENT` — deliberate asymmetry; reason recorded so it is not re-litigated.
- `OPEN` — not verified either way; work this cell in the next full audit.

---

## Matrix A — Capability × Host

Hosts:

- **WF** — standalone `WithForms` component (composes `DispatchesStateUpdates`, `InteractsWithFieldActions`, `InteractsWithRepeaters`, `InteractsWithSelectCreation`).
- **TM** — table action modal form (`WithTable`: `DispatchesStateUpdates`, `InteractsWithFieldActions`, `InteractsWithRepeaters`, `InteractsWithSelectCreation` — since 2026-07-02).
- **WZ** — wizard-step form inside a table action modal (`HasModal::steps()`).
- **RM** — `RelationManager` (inherits everything from `WithTable`; same cells as TM unless noted).
- **RI** — field inside a repeater item (sub-context of WF/TM). Flattening treats `Repeater` as a leaf (`FormRuntime::flattenComponents`); since 2026-07-02 the canonical lookup `Form::findComponentByStatePath()` resolves item paths through the per-item schema (nested repeaters included), so reactive dispatch reaches item fields.

| Capability | WF | TM | WZ | RM | RI |
|---|---|---|---|---|---|
| Field render + `wire:model` binding (flat) | OK | OK | OK | OK* | OK |
| Field render + binding, layout-wrapped (`Grid`/`Section`) | OK | OK | OK | OK* | OK (fixed 2026-07-02: deep clone + `prepareChildren` in `getItemSchema`) |
| `afterStateUpdated()` | OK | OK | OK* | OK* | OK (2026-07-02, `RepeaterReactivityTest`) |
| Live validation (`validateLive()` / `validateOnBlur()`) | OK | OK | OPEN | OK* | OK (2026-07-02, `RepeaterReactivityTest`) |
| Field actions (`suffixAction`/`prefixAction`/`hintAction`, `Button`) | OK | OK (`FieldActionsTableTest`) | OPEN | OK* | OK (2026-07-02, `RepeaterReactivityTest`) |
| `Select` remote search (`getSearchResultsUsing`) | OK | OK* (same trait; no table-modal test) | OPEN | OK* | OK (2026-07-02, `RepeaterReactivityTest`) |
| `Select::createOptionForm()` / `editOptionForm()` | OK | OK (2026-07-02: `WithTable` composes `InteractsWithSelectCreation`; `selectCreatedOption()` writes via `StateContainer::writeInto()` — `SelectCreateOptionTableTest`) | OK* | OK* | OPEN |
| `BelongsToSelect` create-option (action-based, `mountAction`) | OK | OPEN — mountAction exists on `WithTable`, cell untested | OPEN | OPEN | OPEN |
| `BelongsToSelect` remote search | OK (2026-07-02: relationship-driven remote mode — `searchable()` w/o `preload()` searches via `searchOptions()`, blade passes `remoteSearch` + seeds selected labels via keyed lookup; `BelongsToSelectTest`) | OK* | OPEN | OK* | OPEN |
| Repeater add/remove/reorder | OK | OK (`WithTableRepeaterTest`) | OPEN | OK* | OK (nested: OPEN) |
| Wizard (modal steps, `HasModal::steps`) | — | OK | — | OK* | — |
| Wizard (standalone `Schema\Wizard` layout) | OK | OK* | — | OK* | OPEN |
| `Tabs` layout | OK | OK* | OPEN | OK* | OPEN |
| Layout-level `$get`/`$set` closures (`visible(fn ($get) => …)`) | OK | OK | OK* | OK* | OPEN — layout inside item gets `prepareChildren($itemPath)` since 2026-07-02, closures unverified |
| Modal mobile variants (`slideOverOnMobile()` / `fullScreenOnMobile()`) | OPEN — core modal blade supports it; no WithForms-side action-modal path | OK (2026-07-02: `ActionModalMobileVariantTest` + visual QA 375/768/1440) | OPEN | OK* | — |
| Infolist (`ViewAction->infolist()`) | — | OK | — | OK* | — |
| Import/Export (`importTable`/`exportTable`) | — | OK | — | OPEN — BelongsToMany-scoped import/export untested | — |

Note on the RI column: item-path resolution was implemented 2026-07-02 (`FormRuntime::findComponentByStatePath`, consumed by `DispatchesStateUpdates` and `InteractsWithFieldActions`). Remaining RI OPEN cells (create-option, wizard/tabs inside items) are still unverified.

---

## Matrix B — Propagation Axes × Tree Consumers

Axes: `statePath`, `live`, `livewire` binding, `disabled`, visibility, validation rules, save/state extraction, lookup-by-state-path. Every consumer of `LayoutComponent::getSchema()` must handle its axes recursively.

| Consumer | Recurses into layouts? | Axes covered | Notes |
|---|---|---|---|
| `FormRuntime::prepare` → `LayoutComponent::prepareChildren` | yes | statePath, live, livewire, disabled | `disabled` added 2026-06-30; layout binds livewire to **itself** too (2026-07-01) |
| `FormRuntime::flattenComponents` (→ `getFlatComponents`) | yes; **`Repeater` = leaf by design** | validation, save | lookup no longer relies on flattening alone — `findComponentByStatePath()` (2026-07-02) adds per-item resolution over the leaf rule |
| `Repeater::getItemSchema` | yes (2026-07-02: `LayoutComponent::__clone` deep-clones schema; layout clones re-prepared via `prepareChildren($itemPath)`) | statePath (+ inherited live/livewire/disabled on clones) | was the shallow-clone / stale-`resolvedStatePath` bug |
| `Repeater::collectItemValidationRules` | yes (2026-06-30) | validation | |
| `SaveHandler::collectRelationshipRepeaterNames` | yes | save | |
| `RelationshipSaveHandler::findRepeaters` | yes | save | |
| `Wizard::getSteps` / `Tabs::getTabs` | one level (filters own children) | visibility | re-indexes so hidden steps/tabs keep indicator aligned |
| `Infolist` schema + `RepeatableEntry` | yes for nesting | record binding | `RepeatableEntry::__clone` deep-clones its schema (2026-07-02), so nested repeatable rows are per-clone; `EntriesTest` pins it |
| Layout blades (`grid/section/fieldset/tab/step`) | render children directly via `getSchema()` | — | they do **not** re-propagate anything; correctness depends on a prior `prepareChildren` — keep it that way and never render an unprepared tree |

Standing rules:

1. When one consumer becomes recursive, re-check every other row.
2. Anything that clones a tree node must deep-clone (`LayoutComponent::__clone` and `RepeatableEntry::__clone` enforce this; watch new clone sites).

---

## Matrix C — API Parity of Sibling Classes

### C1. Action family (`packages/core/src/Actions/`)

Members: `BaseAction`/`Action`, `HeaderAction`, `BulkAction`, `ModalFooterAction`, `ModalStep`, `ActionGroup` (+ presets Delete/Edit/View/Restore…).

| Capability | Action/Header/Bulk | ModalFooterAction | Verdict |
|---|---|---|---|
| `label/icon/color/outlined/position` | yes | yes | OK |
| confirmation | `requiresConfirmation()` + modal heading/description/icon | `requiresConfirmation()`/`confirm()` → `wire:confirm` only (added 2026-07-02) | INTENT — footer actions live inside a modal; a browser confirm avoids modal-in-modal. Revisit only on demand |
| `visible()` / authorization | yes (`HasVisibility`, `authorize`) | **no** | OPEN — decide intent; a conditionally-hidden footer button is a plausible ask |
| `tooltip()`, `size()`, `extraAttributes()` | yes | no | OPEN — decide intent |
| dynamic Closure props on record-less actions | fixed 2026-07-01 (`shouldInvokeDynamicCallback`) | n/a (no closures except action) | OK |

### C2. Select family

Members: forms `Select`, `BelongsToSelect`, `MorphToSelect`; table `SelectFilter`, `TernaryFilter`, `SelectColumn`.

| Capability | Select | BelongsToSelect | MorphToSelect | SelectFilter | TernaryFilter | SelectColumn |
|---|---|---|---|---|---|---|
| shared combobox (non-native) | OK (default) | OK (default) | INTENT — two-stage native selects, combobox styles matched only | OK (default since 2026-07-14) | OK (default since 2026-07-14; "all" = placeholder) | INTENT — inline cell keeps native `<select>` |
| `native()` opt-out | OK | OK | — (never offered) | OK | OK | **absent by design** (2026-07-14) — the cell has no combobox binding, so a toggle could only be a no-op |
| `searchable()` | OK | OK | — | OK (auto opts out of native, 2026-07-01) | — (2 fixed options) | — |
| remote search | OK | OK (2026-07-02: relationship-driven default + explicit callback wins) | — | INTENT — filters preload options | — | — |
| create option | OK (`InteractsWithSelectCreation`, hosts: WF + TM) | OK (separate `mountAction` mechanism) | — | — | — | — |
| edit option | OK | **GAP** — inherited, no UI affordance | — | — | — | — |
| enum options (`options(Enum::class)`) | OK | OK* | — | OK | — (boolean) | OK |

Canonical owner of the native/custom choice: `Foundation\Concerns\HasNativeControl` (core) — also used by `DateTimePicker`, hence "Control" not "Select". Two extension points: `defaultNative()` (different default) and an aliasable `isNative()` (force native in a mode, as `DateTimePicker` does for `month`).

Standing issue: **two parallel create-option mechanisms** (`Select` → dedicated trait/modal vs `BelongsToSelect` → action system). Canonical-owner rule says consolidate; record a decision before adding a third.

Standing issue: `MorphToSelect` is the last select-like surface still rendering raw `<select>`s (INTENT above). If the unified look matters more than its two-stage wiring, it is the remaining gap.

### C3. Export vs Import

| Aspect | Export | Import | Verdict |
|---|---|---|---|
| action preset | `ExportAction::makeExport()` (translated label) | `ImportAction::makeImport()` (translated label) | OK |
| host seam | `exportTable()` (filtered query, visible columns) | `importTable()` (invalidates caches) | OK |
| formats | csv/xlsx/… (`ExportFormat`) | CSV only | INTENT (like Filament) |
| unmapped-key safety | n/a | fail-fast guard (fixed 2026-07-02) | OK |
| transaction | n/a (read) | **none** — DB error mid-file leaves a partial import | OPEN — decide intent or wrap |
| empty file + `requiredMapping()` | n/a | silent success (mapping resolves on first data row) | OPEN (LOW) |
| duplicate headers in file | n/a | `array_combine` — later column wins silently | OPEN (LOW) |

### C4. Chart widgets

`LineChartWidget` / `PieChartWidget` / `DoughnutChartWidget` are thin presets over `ChartWidget` (+ `options()` merge). Parity OK. No `BarChartWidget` preset — INTENT or one-liner, decide when asked.

### C5. Columns vs Infolist Entries

Both format state via shared `FormatsState`. Not yet diffed method-by-method — OPEN row for the next full audit (candidate check: does every column formatter have an entry counterpart and vice versa?).

---

## Matrix D — Adversarial Inputs on Persistence & Query Surfaces

| Surface | Probes | Status |
|---|---|---|
| `TableImport` | unmapped `updateExisting` key; match attribute w/o import column; empty file; dup headers; row with fewer/more cells than header | guard fixed + tested 2026-07-02; empty-file + dup-headers OPEN (LOW, see C3) |
| `CsvImporter` | BOM; blank lines; short/long rows | OK (tested) |
| `RelationManager` | BelongsToMany pivot with own `id`/colliding `name`; sort/filter on a colliding column name over the join | hydration fixed + tested 2026-07-02; sort/search were already qualified by the planner (`tableAlias: $baseTable`); the unqualified path was `Column::applyFilter` — qualified + tested 2026-07-02 (`JoinedQueryQualificationTest`) |
| `TableQueryService` + planner + `ApplyFilters` | single-/no-bound BETWEEN; hostile sort direction/NULLS; dotted filter names; LIKE wildcards in search terms | BETWEEN + sort normalization fixed 2026-06; **dotted filter names via UI broken** (known latent, 2026-06-11); LIKE wildcard escaping deliberately reverted (`ESCAPE '\'` broke MariaDB — do not re-add without the DB matrix) |
| `StateContainer` writes | nested write through host with StateContainer bags via plain `data_set` | `InteractsWithState::set` uses `writeInto` OK; **`InteractsWithSelectCreation::selectCreatedOption` uses `data_set`** — latent until the trait lands on a StateContainer host (couple with Matrix A GAP fix) |
| `SaveHandler` / `RelationshipSaveHandler` | repeater rows added+removed in one save; dotted field names; missing relationship | OPEN — no adversarial probes recorded yet |
| `EnumResolver` | non-enum class-string; enum without contract; mixed scalar/enum arrays | OK (tested 2026-06-21/23) |
| `exportTable` | export with hidden columns; export under active sub-row filters | OK (tested); summaries-in-export tested 2026-06-11 |

### Viewport axis (mobile / tablet / desktop)

Every UI surface must be exercised at 375/768/1440 px (workbench previews + headless-Chrome capture; see `docs-site/scripts/capture-previews.mjs` and the `table-modal-*`, `table-paginated`, `table-selection` previews). Verified 2026-07-02: table toolbar/pagination/sub-rows/selection bar (bulk buttons wrap), modal variants, forms + repeater, sortable, widgets (stats/dashboard grids responsive — **never** emit inline `grid-template-columns`), infolists. Known rules:

- Grids owned by packages must collapse to 1 column on mobile (`grid-cols-1` + `sm:`/`lg:` growth) — the schema/infolist pattern is canonical.
- Wide content (tables) scrolls inside an `overflow-x-auto` wrapper, never the document.
- Floating panels rely on `$float`'s `shift({padding: 8})` + `flip()` to stay in the viewport.
- Fixed pixel widths in views are suspect; workbench preview frames use `w-full max-w-[…]`.
- `<button>` menu/dropdown items need `text-left` — the UA default centers button text while `<a>` rows align left (bit the action-group dropdown).
- A scroll container (`overflow-x-auto`) inside a flex item needs `min-w-0` on that item, or its min-content stretches the page (bit the tabs bar in the form preview frame).
- Interactive states count: open every dropdown/panel/modal and drive one Livewire roundtrip per host in the sweep — preview components must be registered with Livewire (`WorkbenchServiceProvider`) or updates 500.

### Environment axis (Laravel version × DB driver)

The support matrix (Laravel 10–13 × SQLite/MySQL/MariaDB/Postgres) is itself an adversarial-input surface. Known gotchas to check when touching these areas:

- **`casts()` method is Laravel 11+** — packages supporting 10.x must use the `$casts` property (bit `AuditEntry` 2026-07-02: arrays hit PDO raw on L10).
- **`LIKE … ESCAPE '\'` breaks MariaDB** — deliberately not used (passed on SQLite, reverted 2026-06-21).
- **MySQL/MariaDB identifier limit is 64 chars** — name long composite indexes explicitly (bit `reorderable_column_orders` 1.7.0).
- **FK references in package migrations** (e.g. `audit_logs.user_id → users.id`) are ignored by SQLite but enforced by MySQL/Postgres — DB-backed tests must satisfy them.
- New-API sweep: before using a framework API in shared code, check it exists in the lowest supported Laravel (`grep` the 10.x upgrade guide or Reflection-guard it).

---

## Maintenance

- Bump the snapshot date whenever cells change.
- New feature PRs add their rows/columns **in the same PR** (cheap while context is loaded).
- A full audit that finds all cells accurate should still bump the date — that is the signal the matrix was actually re-derived, not skipped.
