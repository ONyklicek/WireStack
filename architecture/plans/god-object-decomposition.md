# God-object decomposition — Column, Table, WithTable

Preparation plan, not a spec. Every number here was measured on 1.10.4, not
estimated; re-measure before acting on a stale figure.

Yardstick: [`AI_CODING_STANDARD.md`](../../AI_CODING_STANDARD.md). The two rules
that shape every proposal below:

- **A trait carries no business logic.** It exposes a clean API and delegates.
- **Classes stay under 300 lines, one responsibility.**

The reference shape already exists in the repo, built twice on 2026-07-15/16:
`Foundation/Support/StateColorResolver` + `Concerns/InteractsWithStateColor`, and
`Foundation/Support/IconResolver` + `Concerns/InteractsWithStateIcon`. A `final`
dependency-free class owns the logic; a thin concern holds configuration and
exposes named hooks. Copy that shape, not just the file layout.

## The trap this plan exists to avoid

Splitting a god object into ten traits is not progress by itself. `HasSummary` is
the proof: it is **already** a per-capability trait, and it is **616 lines of SQL
and statistics** (`computeQuerySummary:352`, `wrapAggregateQuery:379`,
`computeMedian:491`, `computeVariance:510`, `formatNumeric:570`) mixed into
`Column`, so all 13 column subclasses carry median and variance maths. Renaming
it `CanBeSummarized` changes nothing.

**Every capability below is therefore two artefacts, not one:** a thin concern
(fluent config + accessors) and — where there is real logic — a `final` service
that owns it. If a capability is pure configuration, the concern stands alone.

---

## 1. `Column` — 1,639 lines

Measured: 131 public methods, 51 own properties, 8 traits already composed
(`HasAuthorization`, `HasColor`, `HasFontWeight`, `HasIcon`, `HasResponsive`,
`HasSize`, `HasSummary`, `HasView`).

### 1a. Adopt what already exists — DONE for three of six

`Column` hand-rolls capabilities that core Foundation already owns, which is a
direct breach of `CLAUDE.md`'s canonical-owner invariant ("extend the existing
canonical abstraction instead of creating a local variant").

**Landed** (2026-07-16) — all three were *widenings*: the canonical setters take
`string|Closure|null` and evaluate, where Column's copies took plain strings.

| Adopted | Note |
| --- | --- |
| `HasDefault` | identical bar the missing `evaluate()` |
| `HasTooltip` | identical bar the missing `evaluate()` |
| `HasPlaceholder` | needed a semantic split — see below |

`getPlaceholder()` hard-coded a `?? '-'` fallback, so it could never answer null.
That silently merged two concepts: "empty cell shows a dash" and "the hint an
input shows". `TextInputColumn` handed the dash to its input. The dash now has
one explicit owner, `Column::getEmptyCellText()`; `placeholder()` keeps the
canonical input-hint meaning.

**Blocked, with reasons — do not retry these as written:**

- **`HasExtraAttributes` is type-incompatible.** Column stores a raw `?string`
  attribute blob; the concern stores `array|Closure` and returns `array`. That is
  an API change for anyone writing `extraAttributes('class="x"')`, not a hook.
- **`HasPrefixAndSuffix` brings far too much.** 167 lines: `prefixIcon()`,
  `suffixIcon()`, `prefixAction()`, `suffixAction()`, `hintAction()`,
  `hasAffix()`, plus a dependency on `Action` and a `getFieldAction()` that
  collides with `HasActions`'s. A column wants two strings. **Split the concern
  first** (e.g. `HasAffixText` + the action half) — that is the real task.
- **`HasLabel` was the wrong target.** Column does not re-implement the concern;
  it overrides its *parent*, `Core/Components/DataComponent`, which owns its own
  `$label` + `getLabel()` including the relation-path fallback. The duplication is
  `DataComponent` vs `HasLabel` — both in core — and **they disagree on output**:

  ```
  company_name  →  DataComponent: "Company name"   HasLabel/Column: "Company Name"
  ```

  Column's override exists precisely to get `Str::headline`. Unifying means
  changing `DataComponent`, which changes label casing for its five other
  subclasses (`RelationComponent`, `DateComponent`, `BooleanComponent`,
  `SelectComponent`, `TextComponent`). That is its own decision with visible
  output, not a line item here.

**`HasVisibility` — landed, and it required splitting the concern three ways.**
It bundled three capabilities, and a column wants exactly one of them:

| Concern | What it owns | Composed by |
| --- | --- | --- |
| `HasVisibility` | `visible()` / `hidden()` / `isVisible()` / `isHidden()`, + `HasAuthorization` | everything, incl. `Column` |
| `CanBeDisabled` | `disabled()` / `isDisabled()` | fields, layouts, widgets — **not** columns |
| `InteractsWithStateConditions` | `visibleWhen()` / `hiddenWhen()` / `disabledWhen()` | fields, layouts, widgets — **not** columns |

The split was not tidiness. Two things forced it:

- **A column can never be disabled**, so bundling handed it meaningless API.
- **`visibleWhen()` reads a *sibling field's* live state** via the `$get` accessor
  that only exists inside a form. On a column `$get` is never injected, so it
  would answer "visible" whatever it was told — API that cannot work. It also
  collided head-on: `ButtonColumn::visibleWhen(Closure)` is a **documented BC
  alias for `visibleForRecord()`**, a different concept with the same name, and
  PHP rejects the incompatible signature outright.

The shared `stateCondition()` factory moved to `StateMatcher::condition()` — the
canonical comparison owner, whose docblock already named `visibleWhen()` /
`hiddenWhen()` / `disabledWhen()` as its callers.

**`FormatsState` was a false positive** — the same mistake as `HasLabel`. `Column`
does not duplicate it: `TextColumn` already composes it (as does core's
`TextEntry`). Column's `formatStateUsing()` / `displayUsing()` are a different
concept — a user-supplied formatter callback, not the money/numeric/date presets
`FormatsState` owns. **Nothing to do.**

**One trait per PR, full table suite each time.** These are the most-used methods
in the package; a silent behaviour change here is worse than the duplication.

### 1b. Extract the remaining capabilities

`Column`'s own 131 methods cluster cleanly. Counts are of public methods.

| Capability | Methods | Concern (thin) | Service (the logic) |
| --- | --- | --- | --- |
| **Filtering** | **19** | `CanBeFiltered` | **`ColumnFilterFactory`** |
| Responsive | 13 | *(`HasResponsive` exists — Column bypasses it)* | — |
| Inline editing | 11 | `CanBeEdited` | `InlineEditPolicy` (the ability/authorize half) |
| State + formatting | ~14 | *(`FormatsState` exists)* | — |
| Aggregates | 9 | `HasAggregate` | — (pure config; the SQL is `TableQueryService`'s) |
| Visibility + toggling | 9 | `CanBeHidden` + `CanBeToggled` | — |
| Alignment / width / wrap / limit | 12 | `HasAlignment`, `HasWidth` | — |
| Searching | 5 | `CanBeSearchable` | — |
| Sorting | 4 | `CanBeSorted` | — |
| Text styling | 7 | *(`HasColor`/`HasSize`/`HasFontWeight` exist — Column adds `text*` variants)* | — |
| Copying | 4 | `CanBeCopied` | — |
| URL / action | 3 | `CanCallAction` | — |
| Relation / pivot | 4 | `HasRelation` | — |
| Description | 2 | `HasDescription` | — |

**`CanBeFiltered` — done (2026-07-16).** 19 methods and the imports of all five
concrete filter types moved to `Concerns/CanBeFiltered` + `Services/ColumnFilterFactory`.

**This entry used to say `Column` "drags the base class into knowing every one of
its own subclasses". That was wrong** — `Column extends DataComponent`, `Filter`
is its own root, and the two are unrelated hierarchies, so there was never any
sibling coupling to remove. The real problem was plainer: a factory living inside
a 1,577-line component, with the legacy `filterable(type: …)` map and the fluent
`filterAs*()` helpers each spelling the vocabulary out separately. They now share
the factory's one map.

Public API is unchanged — `filterAs*()` is documented (`table/filters/column-level.md`),
so all 19 methods stay on `Column` through the concern, only thinner.

**`HasSummary` → `CanBeSummarized` + `SummaryCalculator` + `SummaryFormatter` —
done (2026-07-16).** 616 lines → a 301-line concern holding only configuration and
the fluent API, two `final` services, and two value objects (`SummaryFormat`,
`SummaryTarget`) carrying what the services need to know about a column. Public
API unchanged, so `WithTable`, `TableExport` and `SummaryBatch` were untouched.

Two things worth knowing before touching it again:

- **The formatter is the calculator's constructor dependency**, not a peer:
  `SummaryType::Range` renders `"min – max"` rather than returning a number, which
  is the one place computing and formatting genuinely meet. A range is never
  decorated with prefix/suffix — the original called `formatNumeric()` twice and
  never `decorateNumeric()`.
- **A pluck asymmetry is deliberate.** Four of the trait's five pluck sites ended
  in `->values()`; the closure branch for a real column at `query` scope did not,
  handing the user's callback a key-preserving collection. A shared helper would
  have silently reindexed it.

**`SummaryBatch` — done (2026-07-16), but not the way this plan first said.** It
proposed folding it *into* `SummaryCalculator`. That was wrong: the calculator
computes one value and holds no column; the batcher compiles many summaries across
many columns into one aggregate query and needs the columns. Merging them makes one
class with two responsibilities. They stayed two services.

What actually needed fixing was smaller and real: it was a `final class` of
**statics** compiling and running SQL (the standard's "never hide business logic
behind a static call"), sitting in `Columns/`, and its `runRollup()` rebuilt the
`fromSub` wrapping that `SummaryCalculator::wrap()` already owned. It is now an
instance service in `Services/`, injected, reusing `wrap()`; `WithTable`'s two
call sites resolve it from the container.

**Its whole reason to exist had no test.** 85 of its 88 statements were covered —
*indirectly*, through the `WithTable` suites — so falling back to the per-summary
path would have kept every test green and only made the footer slower.
`SummaryBatchTest` now counts queries (six summaries → one query; plain + rollup →
two, not four) and was checked to fail when the batching is sabotaged.

**Watch the test coverage here.** `HasSummaryTest` is entirely in-memory
(`collect($rows)`, `page` scope) — it does not touch the SQL paths at all. Those
are covered by `WithTableSubRowGrandTotalsTest` (real `Schema::create`),
`WithTableSubRows*` and `ExportSummariesTest`. A green `HasSummaryTest` says
nothing about aggregation.

### Naming

Pick the prefix by **semantics**, not by whether the thing is a trait or an
interface — which is what the repo already does (45 `Has*`, 12 `InteractsWith*`,
8 `With*`, 3 `CanBe*`, 3 `Can*`, with `CanBeLive` and `InteractsWithState` living
next to `HasColor` today):

- `HasX` — it *has* a thing: `HasColor`, `HasLabel`, `HasRecord`, `HasWidth`
- `CanBeX` — it *can be* acted on: `CanBeHidden`, `CanBeSearchable`, `CanBeSummarized`
- `CanX` — it *can do*: `CanCallAction`
- `InteractsWithX` — two-way with a subsystem: `InteractsWithTableQuery`, `InteractsWithState`

Under this rule `HasColor` is correctly named and **stays** — there is no
blanket-rename stage, and no BC break. Only the genuinely mis-prefixed move:
`HasVisibility` → `CanBeHidden` (+ split out `CanBeDisabled`; it bundles two
capabilities today), `HasSummary` → `CanBeSummarized`, `HasAuthorization` →
`CanBeAuthorized`.

`WithTable` / `WithActions` / `WithForms` / `WithSortable` are semantically
`InteractsWith*`, **but their names are frozen** — docs put them in users' `use`
statements and `packages/boost/resources/boost/guidelines/wire-forms.blade.php`
ships them as guidance. Refactor the bodies, keep the names.

---

## 2. `Table` — 1,829 lines, 149 methods (145 public)

A god object by **breadth**, not depth — it is the fluent configuration surface,
and no single method is long. Lowest severity per line in this document, and the
highest public-API exposure (145 methods users type). **Do it last.**

Do not split the class. Split the *state* it holds into composed configuration
objects (pagination, polling, caching, chunking, notifications) that `Table`
delegates to, keeping all 145 signatures. Anything else is a BC event for the
package's front door.

---

## 3. `WithTable` — 3,520 lines

The largest file in the repo, and it is a **trait** — the standard's "traits must
not become mini-frameworks" taken to its limit. 98 public methods, injected
wholesale into users' Livewire components, which makes every one of them de facto
public API. Consumed by 3 classes; `table/Table` also makes it `Macroable`.

Verified anchors: `updateTableCell` is a **single 213-line method** (3006–3218);
explicit section markers exist at Sub-Rows (1015), Summaries (1499), Column
Visibility (1846), Record Selection (1994).

**Stage it. Do not attempt this in one pass.**

**Slice 1 — record selection → `Concerns/CanSelectRecords` (done 2026-07-16).**
3,479 → 3,322 lines. It stays a *host concern*, not a service: these are endpoints
a consumer's Livewire component calls. Two things it turned up:

- `getSelectedRecords()` rebuilt the `withCount`/`withSum` map inline — its own
  comment admitted it copied `buildTableQuery()`. Now `Services/AggregateSubqueries`,
  used by both. A missing subquery does not error; the rollup attribute is just
  absent and the summary renders **0**.
- **The copies had drifted, and the difference is deliberately preserved:**
  `TableQueryService` passes a sub-row constraint, the selection copy never did.
  So with sub-row scoped filters active, a selection-scope rollup counts all
  children while query-scope counts only filtered ones. Whether selection should
  follow a sub-row filter is a **product question** (selection is explicitly a set
  of keys, unaffected by filters), so it was written down rather than "fixed" mid-refactor.
- **Four of nine selection methods have no internal caller** — the UI binds
  `tableState.selection.records` via Alpine `$wire.entangle`, so
  `toggleRecordSelection()`, `deselectAllRecords()`, `isRecordSelected()` and
  `areSomeVisibleSelected()` are consumer API held up by tests alone. A browser
  check of the selection preview proves nothing about them.

**Slice 2 — sub-rows → `Concerns/CanExpandSubRows` + `Services/SubRowFilters`
(done 2026-07-16).** The biggest section (~480 lines). 3,322 → **2,839**.

- The filter **rules** became a service — they only needed the `Table` and the raw
  state arrays, never the host. Two different things narrow children and are easy
  to conflate: a **scoped main filter** (`Filter::subRows()`) constrains children
  as it constrained their parents; the **interactive bar** is per-column filtering
  under an expanded row. `hasActiveInteractive()` is load-bearing — it disables the
  eager-load and in-memory fast paths, which would otherwise return unfiltered
  children.
- The expansion endpoints stayed a host concern (chevrons, filter bar, "show all"
  call them). `getSubRows()`'s fast path is untouched: eager-load per page, but only
  when no sub-row filter is active *and* the set is complete — a limited eager load
  ships a `*_count` so a later "show all" can detect a partial set.
- **Unlike `SummaryBatch`, the N+1 property was already guarded** —
  `WithTableSubRowsTest` and `WithTablePerformanceTest` count queries.
- **Still duplicated:** `TableQueryService` states the "which filters are scoped to
  sub-rows" rule a second time, inside a larger classification loop. The two were
  compared and agree (`appliesToSubRows()` + `canView()` + non-empty raw + non-empty
  extracted). One rule, written twice — consolidating means restructuring the query
  seam, which is its own change.

**Slice 3 — action engine seams → `Concerns/InteractsWithTableActions`
(done 2026-07-16).** ~350 lines. 2,839 → **2,480**. A host concern: they read and
write the component's state container.

**The lesson of this slice, and a trap for every one after it.** Four of the moved
methods — `haltModalFormStatePath()`, `afterActionExecuted()`, `resolveActionRecordIds()`,
`sendActionNotification()` — were **silently overriding concrete defaults** in
`InteractsWithActionForms` / `InteractsWithActions`. PHP lets a method defined in
the *using* trait beat an imported trait's with no `insteadof` and no diagnostic.
Moving them into a trait of their own turned all four into hard collisions, which is
the improvement: they now say out loud that they replace the engine's answers.

Two mechanics worth knowing before the next slice:

- **PHP fatals on the first collision and stops.** Fixing one reveals the next; do
  not assume a single error means a single problem. Compute the overlap up front
  (`grep` the method names of the extracted trait against the composed ones).
- **A redeclared property is silent too.** `WithTable` declared four properties its
  composed traits already provide; PHP only rejects an *incompatible* redeclaration,
  so identical copies pass unnoticed. They were removed.

Note for the next slice: a trait composed only by another trait trips PHPStan's
`trait.unused`; the repo's convention is the ignore list in `phpstan.neon`, where
`WithTableQueryString` already sits. Also: `TableBenchmarkTest` is timing-sensitive
and flakes under load — re-run before believing it.

The next extraction is self-contained and pays twice:

**`updateTableCell` — done (2026-07-16), and the "one shared action" idea was
wrong.** This plan proposed a single core `CommitEditableValue` that both
`WithTable::updateTableCell()` and `WithEditablePanel::updatePanelEntry()` would
delegate to, calling them "structurally near-identical". Checked against the code,
they share a **skeleton, not bodies**:

| | table | panel |
| --- | --- | --- |
| save strategies | 5 (callback, legacy callback, pivot, relation, direct) | 2 |
| events | `CellUpdating` / `CellUpdated` | none |
| validation | pipeline + record-aware `$column->validate()` | plain validator |
| dehydration | `DehydratesState`, twice (record-less, then record-aware) | `formatForSave()` once |
| record source | client key → table query | host's bound record + key match |
| conflict | + optional notification, `HydratesState` for currentValue | inline only |
| namespace | `wire-table::` | `wire-core::` |

One action covering both needs ~7 injection points — a template method wearing a
service's name, worse than the duplication. **They stayed separate.**

What was genuinely shared: the **optimistic-locking convention** →
`Foundation/Support/RecordVersion`. Three rules that had to agree across two
hand-written copies (version = `updated_at` as a string; `'0'` is the client's
"never had one" sentinel; an untimestamped record is unguarded), plus eight
hand-rolled copies of the stamp. It exposed a real bug: both copies read
`->updated_at` literally, so a model renaming `UPDATED_AT` had **no version and
was therefore never locked**.

The table's five save strategies → `Services/CellValueWriter`, where the order is
the contract. Extracting them also surfaced that the direct-write branch — the
default path — never returned `oldValue`, so `CellUpdated` fired with null for the
most ordinary edit.

**Still true, and worth fixing: the table's inline editing has no preview.**
`/previews/panels-editable` drives the panel host only, so `updateTableCell` has
never been exercised in a browser — the one thing the standing "green tests are
not proof" rule asks for. The panel side is verified (7/7).

**Not done, deliberately:** the error-shape returns (`['success' => false, …]`)
and the `catch` that flattens an exception to its message. The array *is* the wire
contract — `dropdown.js` reads `r.conflict`, `r.version`, `r.message` — so this is
a Livewire endpoint's response shape, not a domain class leaking an error. Modelling
it as a `CommitResult` value object with `toArray()` would be the honest fix, and it
is its own step.

---

## 4. Structural prerequisite

`packages/table/src` and `packages/forms/src` have **no `Actions/`, `Services/`
or `Managers/` directory at all**. That is not a cosmetic gap — it is the reason
the logic ended up in traits: there was nowhere else to put it. `TableQueryService`
is a `final class` sitting in `Concerns/`, a directory the standard reserves for
traits, which is the same story in miniature.

**Create the directories before extracting anything**, or each extraction will
re-litigate where its service belongs.

---

## Suggested order

1. **Create `Actions/`, `Services/`, `Support/`** in table and forms; move
   `TableQueryService` out of `Concerns/` and register it (2 `new` sites).
2. **`Column` adopts the six existing core concerns**, one per PR, each with its
   hook. Deletes code, closes the canonical-owner breach.
3. **`HasSummary` → `CanBeSummarized` + `SummaryCalculator`**, collapsing
   `SummaryBatch` into it.
4. **`updateTableCell` → core `CommitEditableValue`**, with `WithEditablePanel`
   delegating to the same action.
5. **`CanBeFiltered` + `ColumnFilterFactory`** — needs a design decision first.
6. **The remaining `Column` concerns** (search, sort, copy, alignment, …) —
   mechanical once 1–2 land.
7. **`Table` config objects** — last, highest BC exposure.

Stages 1–4 are internal. Nothing here requires a rename, and nothing here is a
documented BC break.
