---
order: 55
---

# Summaries

Summaries aggregate a column into a footer value — a sum, an average, a count,
and more. They work on the main table, on sub-row tables, and on rollup columns
that pull values from a relationship. This page covers every option.

## Quick Start

Call `->summarize()` (or a shortcut like `->summarizeSum()`) on any column. A
footer row appears automatically with the result:

```php
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

public function table(Table $table): Table
{
    return $table
        ->model(Invoice::class)
        ->columns([
            TextColumn::make('number')->label('Invoice'),
            TextColumn::make('total')
                ->money()
                ->summarizeSum(),        // footer: Σ of every filtered invoice
        ]);
}
```

```text
┌────────────┬──────────────┐
│ Invoice    │       Total  │
├────────────┼──────────────┤
│ INV-1001   │    9 350 Kč  │
│ INV-1002   │   18 100 Kč  │
│ INV-1003   │    8 450 Kč  │
├────────────┼──────────────┤
│ Sum:       │   35 900 Kč  │
└────────────┴──────────────┘
```

## Aggregate Types

The first argument to `summarize()` is the aggregate type. Built-in types:

| Type            | Result                                   | Example output |
| --------------- | ---------------------------------------- | -------------- |
| `sum`           | Total of all values                      | `35 900`       |
| `avg`           | Mean (rounded to 2 decimals)             | `11 966.67`    |
| `count`         | Number of non-null values                | `30`           |
| `distinctCount` | Number of distinct values                | `7`            |
| `min`           | Smallest value                           | `10`           |
| `max`           | Largest value                            | `90`           |
| `range`         | `"min – max"` string                     | `10 – 90`      |
| `median`        | Middle value (avg of two when even)      | `40.0`         |
| `variance`      | Sample variance (n − 1)                  | `4.57`         |
| `stddev`        | Sample standard deviation                | `2.14`         |
| `first`         | First value in the set                   | `Alice`        |
| `last`          | Last value in the set                    | `Zoe`          |
| `Closure`       | Custom — `fn ($values, $query) => …`     | anything       |

```php
TextColumn::make('score')
    ->summarize('median')
    ->summarize('stddev');
```

### Shortcut Methods

Each common type has a fluent shortcut that also sets a sensible default label:

| Shortcut                | Equivalent                       |
| ----------------------- | -------------------------------- |
| `->summarizeSum()`      | `->summarize('sum')`             |
| `->summarizeAvg()`      | `->summarize('avg')`             |
| `->summarizeCount()`    | `->summarize('count')`           |
| `->summarizeDistinct()` | `->summarize('distinctCount')`   |
| `->summarizeMin()`      | `->summarize('min')`             |
| `->summarizeMax()`      | `->summarize('max')`             |
| `->summarizeRange()`    | `->summarize('range')`           |
| `->summarizeMedian()`   | `->summarize('median')`          |
| `->summarizeStddev()`   | `->summarize('stddev')`          |

Each shortcut accepts an optional label and scope:

```php
->summarizeSum('Grand total', scope: 'query')
```

## Scopes

`scope:` decides **which records** are aggregated.

| Scope         | Aggregates over                       | Computed how                       |
| ------------- | ------------------------------------- | ---------------------------------- |
| `query`       | all records matching current filters  | SQL `SUM()/AVG()/…` (efficient)    |
| `page`        | only the current page                 | in-memory from the loaded page     |
| `selection`   | only the checked rows                 | in-memory from the selected models |
| `subRows`     | the children of one parent row        | in-memory from the relationship    |

```php
TextColumn::make('price')->summarize('sum', scope: 'page');
```

`query` is the default. It runs a real database aggregate, so it stays fast even
across millions of rows — it never loads them into memory.

### The Scope Toggle

When more than one scope is available, the footer renders a compact toggle so the
user can switch what the totals reflect without you reconfiguring anything:

```text
                              Showing: [ All ] This page
┌────────────┬──────────────┐
│ Invoice    │       Total  │
│   …        │      …       │
├────────────┼──────────────┤
│ Grand total:   35 900 Kč  │
└────────────┴──────────────┘
```

`All` maps to `query`, `This page` to `page`, and `Selection` only appears while
rows are checked. The active choice is stored in Livewire table state.

## Number Formatting

Numeric summaries are formatted with the column's prefix/suffix and, when set,
`->summaryDecimals()`:

```php
TextColumn::make('total')
    ->suffix(' Kč')
    ->summaryDecimals(2)        // decimals, comma decimal sep, space thousands sep
    ->summarizeSum();           // 1234.5 → "1 234,50 Kč"
```

`summaryDecimals()` takes optional separators:

```php
->summaryDecimals(2, decimalSeparator: '.', thousandsSeparator: ',')  // 1,234.50
```

| Configuration                              | Raw      | Rendered      |
| ------------------------------------------ | -------- | ------------- |
| *(none)*                                   | `1234.5` | `1234.5`      |
| `->summaryDecimals(2)`                     | `1234.5` | `1 234,50`    |
| `->summaryDecimals(2, '.', ',')`           | `1234.5` | `1,234.50`    |
| `->prefix('$')->summaryDecimals(2,'.',',')`| `1500`   | `$1,500.00`   |
| `->suffix(' Kč')->summaryDecimals(2)`      | `1234.5` | `1 234,50 Kč` |

`count` and `distinctCount` are never reformatted as decimals — they stay whole
numbers. `range` is already a formatted `"min – max"` string.

### Custom Formatter

For full control, pass a `format` closure. It receives the computed value and
wins over the default formatting:

```php
->summarize('sum', format: fn ($value) => '€'.number_format($value, 2));
```

## Conditional Aggregation

Restrict which records are aggregated with `when:`. The predicate differs by scope:

```php
// DB scope (query): receives the query builder
->summarize('sum', when: fn ($query) => $query->where('paid', true))

// In-memory (page / selection / subRows): receives (value, record)
->summarize('sum', scope: 'page', when: fn ($value, $row) => $row->paid)
```

Only rows where `when()` returns true are included — for example, summing only
paid invoices while still listing every invoice.

## Rollup Columns

A column can pull an aggregate **from a relationship** and show it per row. These
are computed as efficient `withCount` / `withSum` subqueries:

| Method                              | Cell shows            |
| ----------------------------------- | --------------------- |
| `->counts('items')`                 | count of children     |
| `->sums('items', 'price')`          | `SUM(price)` of children |
| `->averages('reviews', 'rating')`   | `AVG(rating)` of children |
| `->mins('items', 'price')`          | `MIN(price)` of children |
| `->maxes('items', 'price')`         | `MAX(price)` of children |

```php
TextColumn::make('items_total')
    ->sums('items', 'line_total')   // per-row: this invoice's item total
    ->money();
```

### Grand Totals Across All Children

Add a summary to a rollup column and the footer shows the **grand total of every
child across all parents** — the sum of the per-row rollups:

```php
TextColumn::make('items_total')
    ->sums('items', 'line_total')   // per-row rollup in the cell
    ->summaryDecimals(0)
    ->suffix(' Kč')
    ->summarizeSum('Grand total');  // footer: every line item, every invoice
```

```text
┌────────────┬──────────────┐
│ Invoice    │  Items total │
├────────────┼──────────────┤
│ INV-1001   │    9 350 Kč  │ ← SUM of INV-1001 line items (rollup)
│ INV-1002   │   18 100 Kč  │
│ INV-1003   │    8 450 Kč  │
├────────────┼──────────────┤
│ Grand total:  35 900 Kč   │ ← SUM across every invoice's items
└────────────┴──────────────┘
```

## Custom Closure Summaries

For anything the built-ins don't cover, pass a closure. It receives a collection
of the column's non-null values and (for `query` scope) the query builder:

```php
use Illuminate\Support\Collection;

TextColumn::make('price')->summarize(
    fn (Collection $values, $query) => $values->max() - $values->min(),
    label: 'Spread',
);
```

## Multiple Summaries

Stack as many summaries on one column as you need — each renders on its own footer
row:

```php
TextColumn::make('total')
    ->money()
    ->summaryDecimals(2)
    ->summarizeSum('Grand total')
    ->summarizeAvg('Average')
    ->summarizeMax('Largest');
```

## How It Is Computed

- **`query` scope** uses a real SQL aggregate (`SUM`, `AVG`, `COUNT`, `MIN`,
  `MAX`, `DISTINCT COUNT`). It clones the filtered query so the table query is
  untouched, and never loads rows into memory.
- **Statistical types** that aren't portable across drivers (`median`,
  `variance`, `stddev`, `first`, `last`) pull the single column and compute in
  PHP.
- **`page` / `selection` / `subRows`** compute in memory from already-loaded
  models — no extra query.
- **Empty sets** return `0` for `sum`/`count`/`distinctCount`, `–` for `range`,
  and `null` otherwise.

## Worked Example

```php
public function table(Table $table): Table
{
    return $table
        ->model(Invoice::class)
        ->columns([
            TextColumn::make('number')->label('Invoice')->sortable(),
            TextColumn::make('customer')->label('Customer'),
            BadgeColumn::make('status')->colors([
                'paid' => 'success', 'pending' => 'warning', 'overdue' => 'danger',
            ]),
            TextColumn::make('items_count')
                ->label('Items')
                ->counts('items')
                ->summarizeSum('Total items'),
            TextColumn::make('items_total')
                ->label('Total')
                ->sums('items', 'line_total')
                ->numeric(0)
                ->suffix(' Kč')
                ->summaryDecimals(0)
                ->summarizeSum('Grand total')
                ->summarizeAvg('Average'),
        ])
        ->searchable()
        ->paginated(false);
}
```

## Related Docs

- [Sub-Rows](sub-rows.md) — per-parent subtotals and child summaries
- [Columns](columns.md)
- [Table Overview](overview.md)
