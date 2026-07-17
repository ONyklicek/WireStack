# Column Filter → Canonical Filter Unification

Status: in progress (2026-07-11)
Owner: table package
Related: `AI_BLUEPRINT.md`, `architecture/table.md`, `packages/table/src/Filters/*`

## Problem

The table has **two independent filtering engines**:

1. **Canonical** — `packages/table/src/Filters/*`: a polymorphic `Filter`
   hierarchy (`Filter`, `SelectFilter`, `DateFilter`, `NumberRangeFilter`,
   `TernaryFilter`). Each filter is a value object that:
   - applies via `apply(Builder, $value)`,
   - renders through wire-forms components (`getFormFields()`),
   - exposes `getIndicatorValueLabel()` (chips), `getQueryStringFields()` (URL
     persistence), `bypassesPlanner()` (QueryPlanner integration),
     `HasAuthorization`, `subRows()`.

2. **Column header filter** — baked into `packages/table/src/Columns/Column.php`:
   a `$filterType` string discriminator + **two parallel `match` maps**
   (`applyFilterCondition()` @ Column.php:1636, `renderFilter()` @ Column.php:1750),
   ~14 ad-hoc properties, ~20 fluent methods, bespoke `filter-*.blade.php`
   partials, **duplicated** apply logic (`applyBooleanFilter` /
   `applyDateRangeFilter` / `applyNumberRangeFilter` re-implement
   `TernaryFilter` / `DateFilter` / `NumberRangeFilter`), a separate style owner
   (`FilterControl`), and it **bypasses the QueryPlanner** entirely
   (`TableQueryService.php:278-292` post-planner pass). It also lacks
   authorization, indicator chips and query-string persistence.

This violates the `CLAUDE.md` invariants: *one canonical owner per behavior* and
*avoid duplicating match maps / parallel mini-APIs for the same concept*.
Filtering currently has two owners.

## Target design

**The column header filter is a *placement* of a canonical `Filter`, not a
second engine.** The column owns *where* (header cell) and *which attribute*;
the `Filter` object owns *how* to apply / render / indicate / persist.

```php
// Column stores ONE filter instead of 14 properties:
protected ?Filter $filter = null;

public function filter(Filter $filter): static;      // canonical entry
public function isFilterable(): bool { return $this->filter !== null; }
public function applyFilter($query, $value) {         // delegates, no match
    return $this->resolveFilter()->apply($query, $value);
}
```

`filterAsSelect()`, `filterAsMultiSelect()`, `filterAsDate()`,
`filterAsDateRange()`, `filterAsNumberRange()`, `filterAsBoolean()`,
`filterOperator()`, `filterSearchable()`, `filterPlaceholder()`, `filterUsing()`
remain as **thin, 100%-BC factories** over the canonical subclasses.

### New / extended canonical pieces (upgrade, not duplicate)

- **`TextFilter extends Filter`** — operator-aware (`like`, `starts_with`,
  `ends_with`, `=`, `>`, `>=`, `<`, `<=`, `!=`). Owns what `applyTextFilter()`
  does today. `filterOperator()` delegates here.
- **`SelectFilter->multiple()`** already exists via `Filter::multiple()` — used
  for `filterAsMultiSelect()`.
- **`TernaryFilter`** reused for `filterAsBoolean()` (already `bypassesPlanner`).
- **`DateFilter`** reused for `filterAsDate()` / `filterAsDateRange()`.
- **`NumberRangeFilter`** reused for `filterAsNumberRange()`.
- **State-path prefix** on `Filter` — the render layer must bind to either
  `tableState.filters.{name}` (panel) or `tableState.columnFilters.{name}`
  (header). Add `Filter::statePathPrefix(string)` (default
  `tableState.filters`) consumed by the form-field views instead of the
  hard-coded `tableState.filters.` prefix.
- **Inline/compact render variant** — a header cell is denser than a panel row.
  Add a render context flag (`Filter::inline(bool)` or a `variant` arg to
  `render()`) so the header reuses the forms-based field markup in a compact
  size, retiring the bespoke `filter-*.blade.php` partials. `FilterControl`
  either folds into the forms field "compact" size or becomes the one inline
  theme owner.

## QueryPlanner routing (chosen direction)

wire-core's `ApplyFilters` pipe (`packages/core/src/Core/Query/Pipes/ApplyFilters.php`)
already supports `=`, `!=`, `>`, `<`, `>=`, `<=`, `LIKE`, `IN`, `NOT IN`,
`BETWEEN` (with one-sided degradation), `IS NULL`, `IS NOT NULL`, and raw
`sqlExpression`. So most column-filter types map cleanly to a
`FilterDefinition(column, operator, value)` and go **through the planner**:

| Column filter | Planner mapping |
|---|---|
| text `like`        | `LIKE`, value `%v%` |
| text `starts_with` | `LIKE`, value `v%` |
| text `ends_with`   | `LIKE`, value `%v` |
| text `=`/`>`/`<`/… | direct operator |
| select             | `=` |
| multi_select       | `IN` |
| number_range       | `BETWEEN [min, max]` (pipe degrades to one-sided) |

Two types cannot be expressed as a single planner clause and use the existing
`bypassesPlanner()` escape hatch — **still through the shared `Filter::apply()`**,
never a Column match map (exactly how `NumberRangeFilter`/`DateFilter`/`TernaryFilter`
already behave in the panel):

- **date / date_range** — need `whereDate` (date truncation).
- **boolean** — needs `(col = false OR col IS NULL)`.

To route through the planner, each canonical `Filter` must expose the
`(operator, value)` it wants as a `FilterDefinition`. Add
`Filter::toPlannerDefinitions(mixed $value): array<FilterDefinition>` returning
`[]` when it bypasses the planner. `TableQueryService::buildPlannerFilters()`
then folds in filterable columns alongside panel filters, and the post-planner
column pass (`TableQueryService.php:278-292`) is removed for planner-expressible
types.

## Phases

- **P0** — Add `TextFilter`; add `Filter::statePathPrefix()` + inline render
  variant + `toPlannerDefinitions()`; wire the form-field views to the prefix.
- **P1** — Add `Column::filter(Filter)` + `resolveFilter()` (injects
  name/relation/column into the filter); `isFilterable() = filter !== null`.
  Additive only.
- **P2** — Rewrite `filterAs*`/`filterOperator`/`filterSearchable`/`filterPlaceholder`/
  `filterUsing` as factories; delete the property bag, `applyFilterCondition` +
  `applyText/Boolean/DateRange/NumberRange`, and the `renderFilter()` match map
  from Column.php; delete bespoke `filter-*.blade.php` partials.
- **P3** — Fold column filters into `buildPlannerFilters()` via
  `toPlannerDefinitions()`; `bypassesPlanner()` ones go through `apply()`;
  remove the standalone post-planner column pass.
- **P4** — Header filters inherit indicator chips + query-string persistence +
  authorization for free; wire them in.
- **P5** — Migrate the 7 test files (`ColumnFilterCoverageTest`,
  `WithTableMultiSelectFilterTest`, `TableQueryServiceTest`, `ColumnTest`,
  `WithTableInteractionsTest`, `JoinedQueryQualificationTest`,
  `TableTestHooksTest`); add tests for newly-inherited capabilities. Docs EN+CZ,
  preview verification in a real browser.

## BC guarantees

- Every public `filterAs*` / `filterOperator` / `filterUsing` /
  `filterSearchable` / `filterPlaceholder` signature is preserved with identical
  behavior.
- Header-filter state remains stored under `tableState.columnFilters.{name}`
  (scalar / keyed-array shapes unchanged); multi-field shapes (`{from,to}`,
  `{min,max}`) already match `DateFilter` / `NumberRangeFilter`.

## Main risks

- **State-path binding** — the header must keep `columnFilters.{name}`; handled
  by `Filter::statePathPrefix()`.
- **Planner SQL/joins** — routing header filters through the planner changes the
  emitted SQL (qualified columns, joins for relation columns). Verify against
  `JoinedQueryQualificationTest` + relation-column filters on the DB matrix.
- **Hot files** — `Column.php`, `TableQueryService.php`, `WithTable.php`. Work
  strictly incrementally, one filter type at a time, test after each.
