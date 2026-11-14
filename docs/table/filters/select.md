---
order: 31
summary: A dropdown over predefined options or the column's own values, with record counts and "is none of" when you ask — the filter most tables reach for first.
---

# SelectFilter

Dropdown filter for predefined options. The most common filter type.

Renders through the same combobox as the forms `Select` field, so an open filter
looks identical to an open form select. Opt into a browser-native `<select>` with
[`->native()`](#native-html-select).

```php
use NyonCode\WireTable\Filters\SelectFilter;
```

## How It Works

**Where the options come from**, in order:

1. `->options()` — an array, a closure (evaluated on render) or an enum class.
2. When there are none and `->optionsFromColumn()` is on, the column's own
   distinct values, read from the data — most common first, capped at `$limit`,
   `NULL` left out, each labelled by itself (or by its enum's label).
3. For a column cast to an enum, the enum's cases.

**How it matches.** Without an operator picker a single value is `=` and a
`->multiple()` value is `whereIn`, through `whereHas` for a dotted (relation)
name. With `->operators()` the filter speaks membership: *is any of* (`IN`, one
planner clause), *is none of* (`IS NULL OR NOT IN` — rows with no value are kept),
*is empty* and *is not empty* (`IS NULL` / `IS NOT NULL`). Across a relation *is
none of* is `whereDoesntHave` with `IN`: a post tagged both `php` and `go` is not
"none of php". The value is treated as a list once the picker is on, even
without `->multiple()`.

**Counts.** `->withCounts()` prints beside each option how many records picking
it would leave — `Admin (3)`. A count is taken under **every other filter and the
search, but not this filter's own value**: counting *Role* under "Role is Admin"
would say every other role has nothing, and talk the user out of changing their
mind. Each counting filter is one grouped query (`GROUP BY column`, ordered by
count), built by the same query service as the rows, so a count never disagrees
with what picking it shows. An option the data does not hold shows `(0)`. The
chip keeps the plain label — `Role: Admin`, not `Role: Admin (3)`.

**Kept between requests.** The grouped query is the most expensive thing a
render does, and ticking a row or opening a modal does not change its answer, so
the counts are cached — keyed by the query itself (another filter or search term
is another entry) and by the table's write generation, so an inline edit or an
action through the table retires them at once. What the table cannot see — a
write made somewhere else — shows within `wire-table.defaults.facet_cache_ttl`
seconds (60 by default; `0` counts on every request). A table with
[`cacheQuery()`](../advanced.md) keeps them as long as its rows. The cache
belongs to **one viewer's component on one database** and is never shared:
equal SQL is not equal data when tenants live on their own database or schema,
and an option list read from the data would otherwise show one tenant another's
values.

**Not counted** — the options still work, just without numbers and without a
list read from the data:

- a dotted (relation) name — there is no single column to group by;
- a table on a custom [data source](../data-sources.md) — only Eloquent is counted;
- a column header filter whose column hides its cell on some records with
  `visibleForRecord()` — a value list would print the values those cells keep
  out of sight.

## Basic Usage

```php
SelectFilter::make('status')
    ->options([
        'active' => 'Active',
        'inactive' => 'Inactive',
        'banned' => 'Banned',
    ])
```

## With Placeholder

The first item is always an empty "All" option. To customize:

```php
SelectFilter::make('role')
    ->options([
        '' => 'All Roles',           // explicit placeholder
        'admin' => 'Admin',
        'editor' => 'Editor',
        'viewer' => 'Viewer',
    ])
```

## Multiple Selection

```php
SelectFilter::make('tags')
    ->options(Tag::pluck('name', 'id')->toArray())
    ->multiple()
    ->label('Tags')
```

When `multiple()`, applies `whereIn()` instead of `where()`.

## Searchable Dropdown

```php
SelectFilter::make('country')
    ->options(Country::pluck('name', 'code')->toArray())
    ->searchable()
    ->label('Country')
```

Adds a search input to the dropdown. The surface is the same combobox as a
non-searchable filter — only the search input is added.

## Native HTML Select

```php
SelectFilter::make('type')
    ->options([...])
    ->native()                       // browser-native <select> element (faster render)
```

Opts out of the shared combobox, so the filter no longer matches the form select.
Prefer it only where render cost matters more than a consistent look.

`->nativeOnMobile()` keeps the combobox from the filter's mobile breakpoint up
and renders the browser's `<select>` below it, where a phone opens its own
picker instead of a sheet:

```php
SelectFilter::make('type')
    ->options([...])
    ->nativeOnMobile()               // phone: native <select>; desktop: the combobox
```

The choice holds in the filter panel and in the column header row alike — both
render the same control, a `multiple()` filter included. `->touchOnMobile()`
opens the [touch list](../../forms/fields/select.md#touch-list-on-phones) instead,
in both places. `->native()` wins over it; the app-wide default is
`wire-core.mobile.native` (see [mobile](../../start/configuration.md#mobile)).

## From Database

```php
SelectFilter::make('department')
    ->options(fn () => Department::orderBy('name')->pluck('name', 'id')->toArray())
```

Options can be a Closure — evaluated lazily on render.

## From an Enum

Pass a PHP enum class instead of an array — its cases expand to `value => label` options.
Labels come from `getLabel()` when the enum implements `Foundation\Contracts\Enum\HasLabel`,
otherwise the case name is headlined.

```php
SelectFilter::make('status')->options(OrderStatus::class)
```

> When a column's model attribute is **cast** to an enum, a `SelectFilter` on that column
> auto-populates its options from the enum even without calling `->options()`. This shorthand
> is for the cases where you set the filter up explicitly.

## Record Counts

```php
SelectFilter::make('status')
    ->options(['paid' => 'Paid', 'pending' => 'Pending', 'overdue' => 'Overdue'])
    ->multiple()
    ->withCounts()                   // Paid (12), Pending (3), Overdue (0)
```

## Options From The Data

When the values are open-ended — a country, a tag, a supplier — let the column
supply its own list:

```php
SelectFilter::make('country')
    ->optionsFromColumn(limit: 50)   // the 50 most common values, read from the rows
    ->withCounts()
    ->searchable()
    ->multiple()
```

Only values that occur under the other filters are listed, so the list narrows as
the user narrows the table. A value beyond the limit cannot be picked; raise the
limit, or give `->options()` for a closed set.

## Is None Of

```php
use NyonCode\WireTable\Enums\FilterOperator;

SelectFilter::make('role')
    ->options(['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'])
    ->multiple()
    ->operators()                                    // is any of, is none of, is empty, is not empty
    ->defaultOperator(FilterOperator::NotIn)
```

The operators a select can offer are *is any of*, *is none of*, *is empty* and
*is not empty*; any other in the list is dropped. The chip says the comparison —
`Role: is none of Admin, Editor`.

## Custom Query

```php
SelectFilter::make('has_avatar')
    ->options([
        'yes' => 'With Avatar',
        'no' => 'Without Avatar',
    ])
    ->query(fn (Builder $query, string $value) => match($value) {
        'yes' => $query->whereNotNull('avatar_url'),
        'no' => $query->whereNull('avatar_url'),
    })
```

## Extended Example

An orders list where the status filter shows what each choice would leave, and
the customer's country is picked from the countries that actually occur.

```php
<?php

namespace App\Livewire;

use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Component;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Table;

class ListOrders extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Order::class)
            ->columns([
                TextColumn::make('number')->sortable(),
                BadgeColumn::make('status'),
                TextColumn::make('country'),
            ])
            ->filters([
                SelectFilter::make('status') // [tl! focus:start]
                    ->options(OrderStatus::class)
                    ->multiple()
                    ->operators()
                    ->withCounts(),
                SelectFilter::make('country')
                    ->optionsFromColumn(limit: 50)
                    ->withCounts()
                    ->searchable()
                    ->multiple(), // [tl! focus:end]
            ]);
    }

    public function render()
    {
        return view('livewire.list-orders');
    }
}
```

Picking *Country is any of CZ* re-counts *Status* under it; the *Country* counts
themselves ignore the country picked, so the other countries keep their numbers.

## SelectFilter API

```php
->options(array|string|Closure $options) // ['value' => 'Label', ...] or an enum class
->multiple(bool $multiple = true)    // multi-select mode
->searchable(bool $searchable = true) // add a search input to the dropdown
->native(bool $native = true)        // opt into a browser-native <select> (default: false)
->nativeOnMobile(bool $condition = true) // native <select> below the mobile breakpoint only (default: config wire-core.mobile.native)
->touchOnMobile(bool $condition = true)  // full-height touch list below the mobile breakpoint (default: config wire-core.mobile.touch)
->withCounts(bool $condition = true) // print each option's record count, under the other filters — default false
->optionsFromColumn(bool $condition = true, int $limit = 100) // options = the column's distinct values, most common first, capped at $limit
->operators(array|bool $operators = true) // inherited — 'in'|'not_in'|'is_empty'|'is_not_empty'; true = all four
->defaultOperator(FilterOperator|string $operator) // inherited — default 'in'
->getOptions(): array                // value => label, without counts
->getDisplayOptions(): array         // value => label as drawn, with " (n)" when counting
```

## Related

- [Filters](index.md#operators) — the operator vocabulary every filter shares
- [Column-Level Filters](column-level.md#counts-and-value-lists) — `->filterWithCounts()` and `->filterAsValueList()` on a column
- [QueryBuilderFilter](query-builder.md) — a select as one condition among several
