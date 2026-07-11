---
order: 33
nav: false
---

# Column-Level Filters

In addition to dedicated filter components, any column can have an inline filter directly in its header cell. See [Columns — Column-Level Filtering](../columns/editing.md#column-level-filtering).

```php
TextColumn::make('status')
    ->filterable()
    ->filterAsSelect(['active' => 'Active', 'inactive' => 'Inactive'])

BadgeColumn::make('role')
    ->filterAsMultiSelect(['admin' => 'Admin', 'editor' => 'Editor']) // pick several → whereIn

TextColumn::make('price')
    ->filterable()
    ->filterAsNumberRange(0, 10000)

TextColumn::make('created_at')
    ->filterable()
    ->filterAsDateRange()
```

Column filters use the `$columnFilters` Livewire property (separate from `$tableFilters`).

## One filter engine

A column header filter is a **placement** of the same canonical `Filter` object used by the dedicated [filter panel](./index.md) — not a separate engine. The `filterAs*()` helpers are thin factories over `TextFilter`, `SelectFilter`, `DateFilter`, `NumberRangeFilter` and `TernaryFilter`; the column owns *where* the control renders (the header cell) and *which attribute* it targets, while the `Filter` owns *how* it applies, renders, and persists. Because of that, every column filter is planned through the same `QueryPlanner` as a panel filter (joins + qualification handled once), and inherits authorization (`->can()` / `->visible()`) for free.

You can also pass a ready-made filter with `->filter()` when you need full control:

```php
use NyonCode\WireTable\Filters\SelectFilter;

TextColumn::make('status')->filter(
    SelectFilter::make('status')
        ->options(Status::class)
        ->searchable()
);
```

Filters whose SQL the planner cannot express as one clause — date (`whereDate`) and boolean (`= false OR IS NULL`) — transparently fall back to the shared `Filter::apply()`, exactly as they do in the panel.

## Indicator chips & shareable URLs

Because header filters are canonical `Filter` objects, they share the panel's active-filter UX:

- **Indicator chips** — an active column filter shows a removable chip in the toolbar alongside panel-filter chips. The chip label comes from `Filter::getIndicator()` (option labels, range bounds, formatted dates); the × button clears just that column filter, and the "reset all" link clears every active filter.
- **Query-string persistence** — with `Table::queryString()`, column filters are written to the URL under a `col_<column>` parameter (ranges use `col_<column>_min` / `_max`, etc.), so a shared or reloaded URL reproduces the same view. Relation (dotted) column names are skipped, as with panel filters.
