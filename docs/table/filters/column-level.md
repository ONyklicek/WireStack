---
order: 33
summary: A filter in a column's own header — a row of open filters or a funnel per column — with operators, counts, value lists, date presets and a right-click "Only this value".
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

A column header filter is a **placement** of the same canonical `Filter` object used by the dedicated [filter panel](index.md) — not a separate engine. The `filterAs*()` helpers are thin factories over `TextFilter`, `SelectFilter`, `DateFilter`, `NumberRangeFilter` and `TernaryFilter`; the column owns *where* the control renders (the header cell) and *which attribute* it targets, while the `Filter` owns *how* it applies, renders, and persists. Because of that, every column filter is planned through the same `QueryPlanner` as a panel filter (joins + qualification handled once), and inherits authorization (`->can()` / `->visible()`) for free.

You can also pass a ready-made filter with `->filter()` when you need full control:

```php
use NyonCode\WireTable\Filters\SelectFilter;

TextColumn::make('status')->filter(
    SelectFilter::make('status')
        ->options(Status::class)
        ->searchable()
);
```

Filters whose SQL the planner cannot express as one clause — date (`whereDate`), boolean (`= false OR IS NULL`), and the operators that keep `NULL` rows (*does not contain*, *is none of*) — transparently fall back to the shared `Filter::apply()`, exactly as they do in the panel.

Header filters always apply live. `Table::deferFilters()` holds back the
[filter panel](index.md#the-filter-panel) only.

## Indicator chips & shareable URLs

Because header filters are canonical `Filter` objects, they share the panel's active-filter UX:

- **Indicator chips** — an active column filter shows a removable chip in the toolbar alongside panel-filter chips. The chip label comes from `Filter::getIndicator()` (option labels, range bounds, formatted dates); the × button clears just that column filter, and the "reset all" link clears every active filter.
- **Query-string persistence** — with `Table::queryString()`, column filters are written to the URL under a `col_<column>` parameter (ranges use `col_<column>_min` / `_max`, etc.), so a shared or reloaded URL reproduces the same view. Relation (dotted) column names are skipped, as with panel filters.

## Two Layouts

Where the header filters are drawn is the table's choice, resolved in this order:
`Table::columnFilterLayout()`, then `wire-table.defaults.column_filter_layout`,
then `'row'`.

- **`ColumnFilterLayout::Row`** (default) — a second header row with every
  filter open at once, each in a cell as wide as its column. Quick on a wide
  screen with a few filters; cramped on a narrow column, and a phone scrolls the
  filters sideways with the data.
- **`ColumnFilterLayout::Popover`** — the header stays one row high. A
  filterable column carries a funnel beside its label, **filled while the column
  is filtered**, so a narrowed table says *where* it is narrowed. The funnel opens
  that one filter — operator picker, value, *Clear*, *Done* — in a panel sized for
  the filter rather than the column; on a phone it is a bottom sheet with a
  grabber.

```php
use NyonCode\WireTable\Enums\ColumnFilterLayout;

$table->columnFilterLayout(ColumnFilterLayout::Popover)   // or 'popover'
```

```php
// config/wire-table.php
'defaults' => [
    'column_filter_layout' => env('WIRE_TABLE_COLUMN_FILTER_LAYOUT', 'row'), // 'row' | 'popover'
],
```

Both layouts draw the same control bound to the same `columnFilters.<column>`
state, so chips, URLs and saved views do not know which one drew it.

## Operators On A Column

`->filterOperators()` hands the column's filter the same operator picker a panel
filter gets ([Filters → Operators](index.md#operators)). Call it **after** the
`filterAs*()` that chooses the filter — on a column with no filter yet it sets up
a text filter:

```php
use NyonCode\WireTable\Enums\FilterOperator;

TextColumn::make('email')
    ->filterable()
    ->filterOperators(['contains', 'not_contains', 'ends_with', 'is_empty', 'is_not_empty'])

BadgeColumn::make('role')
    ->filterAsMultiSelect(['admin' => 'Administrator', 'editor' => 'Editor'])
    ->filterOperators([FilterOperator::In, FilterOperator::NotIn])
```

With a picker the column's state becomes `{value, operator}` and the URL carries
the operator as `col_<column>_op`.

## Counts And Value Lists

```php
BadgeColumn::make('role')
    ->filterAsMultiSelect(['admin' => 'Administrator', 'editor' => 'Editor'])
    ->filterWithCounts()                   // Administrator (4), Editor (11)

TextColumn::make('city')
    ->filterAsValueList(limit: 200)        // the column's own values, counted, searchable
```

`->filterWithCounts()` turns on [`SelectFilter::withCounts()`](select.md#how-it-works)
for the column's select filter (call it after `filterAsSelect()` /
`filterAsMultiSelect()`; on any other filter it does nothing).
`->filterAsValueList()` is a spreadsheet's *set filter*: a searchable
multi-select whose options are the column's distinct values, most common first,
capped at `$limit`, each with its count unless `withCounts: false`.

Counts are taken under every other filter and the search, never under the
column's own value. **A column that hides its cell on some records with
`visibleForRecord()` is never counted and never lists its values** — the list
would print what those cells keep out of sight. Relation (dotted) columns and
custom data sources are not counted either.

## Date Presets

```php
TextColumn::make('created_at')
    ->date()
    ->filterPresets()                      // a date range filter with every preset
```

`->filterPresets()` makes the column's filter a date range if it is not one
already, then offers [`DateFilter::presets()`](date.md#presets) — `true` for all,
or a list of `DatePreset` cases or values. The preset is stored by name, so a
bookmarked *Last 7 days* stays relative; the URL carries `col_<column>_preset`.

## Filter From A Cell

`Table::cellFilterMenu()` lets a right-click on a cell set that column's header
filter from the value in it:

- **Only this value** — keep the records holding exactly this value;
- **Exclude this value** — drop them. Offered only when the filter has the
  negative operator on its picker (*is not* for a single value, *is none of* for
  a multi-select).

```php
$table->cellFilterMenu()
```

What each filter writes:

| Column filter | Only this value | Exclude this value |
|---|---|---|
| text, no picker | the value (the filter's own comparison, *contains* by default) | — |
| text with *is* / *is not* on the picker | *is* value | *is not* value |
| multi-select (with *is any of* / *is none of*) | *is any of* [value] | *is none of* [value] |
| date | that day (a range from it to itself) | — |
| number range | min = max = the value | — |
| boolean | yes / no | — |

**The value is read from the record on the server** (`filterByCellValue()`),
never from the page: the cell shows a formatted value (*12. 3. 2026*,
*Administrator*), and the filter compares what is stored. A cell that
`visibleForRecord()` hides is ignored, and a row that has its own
[row context menu](../advanced.md#row-context-menu) keeps that menu instead.

A select answers with *is any of* / *is none of* — the exact match it offers.
A filter whose picker has no exact match at all — a text filter offered only
*contains* and *starts with* — cannot say "only this value", so its column gets
no cell menu.

## Extended Example

The users list from the workbench preview: funnels instead of a filter row,
operators on the text columns, counts on the role, presets on the join date, and
the cell menu.

```php
<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Component;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\BooleanColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Enums\ColumnFilterLayout;
use NyonCode\WireTable\Table;

class ListUsers extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(User::class)
            ->columnFilterLayout(ColumnFilterLayout::Popover) // [tl! focus:start]
            ->cellFilterMenu() // [tl! focus:end]
            ->columns([
                TextColumn::make('name')->sortable()
                    ->filterable()
                    ->filterOperators(), // [tl! focus]
                TextColumn::make('email')
                    ->filterable()
                    ->filterOperators(['contains', 'not_contains', 'ends_with', 'is_empty', 'is_not_empty']), // [tl! focus]
                BadgeColumn::make('role')
                    ->filterAsMultiSelect([
                        'admin' => 'Administrator',
                        'manager' => 'Manager',
                        'editor' => 'Editor',
                        'viewer' => 'Viewer',
                    ], 'Any role')
                    ->filterOperators() // [tl! focus:start]
                    ->filterWithCounts(), // [tl! focus:end]
                BooleanColumn::make('is_active')
                    ->filterAsBoolean('Active', 'Inactive'),
                TextColumn::make('created_at')->label('Joined')
                    ->date()
                    ->filterPresets(), // [tl! focus]
            ])
            ->queryString();
    }

    public function render()
    {
        return view('livewire.list-users');
    }
}
```

Right-click *Editor* in the role column and pick *Exclude this value*: the role
funnel fills, the chip reads `Role: is none of Editor`, the URL gains
`col_role_op=not_in` beside the value, and the counts beside the other roles
stay as they were.

## Column And Table Methods

```php
// On a column (CanBeFiltered)
->filterOperators(array|bool $operators = true)   // true = every operator the filter type supports, or list<FilterOperator|string>
->filterWithCounts(bool $condition = true)         // record count beside each option of the column's select filter
->filterAsValueList(int $limit = 100, bool $withCounts = true) // searchable multi-select over the column's distinct values
->filterPresets(array|bool $presets = true)        // date range filter with named presets: true = all, or list<DatePreset|string>

// On the table
->columnFilterLayout(ColumnFilterLayout|string $layout) // 'row' (default, config wire-table.defaults.column_filter_layout) | 'popover'
->cellFilterMenu(bool $enabled = true)             // right-click a cell: "Only this value" / "Exclude this value" — default false
```

## Related

- [Filters](index.md) — operators, the filter panel and the shared filter API
- [SelectFilter](select.md) — counts and options read from the data
- [DateFilter](date.md) — presets and date operators
- [Columns — Column-Level Filtering](../columns/editing.md#column-level-filtering) — the `filterAs*()` helpers
