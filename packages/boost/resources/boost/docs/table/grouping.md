---
order: 57
---

# Row Grouping

Group rows by a column value: the table orders records so groups stay
contiguous, renders a header row for each group, and adds per-group subtotal
rows for every column with a [summary](summaries.md) — on top of the usual
grand-total footer.

## Quick Start

```php
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

public function table(Table $table): Table
{
    return $table
        ->model(Invoice::class)
        ->columns([
            TextColumn::make('number')->label('Invoice'),
            TextColumn::make('customer')->label('Customer'),
            TextColumn::make('total')
                ->suffix(' Kč')
                ->summaryDecimals(0)
                ->summarizeSum('Sum'),
        ])
        ->groupBy('customer');
}
```

```text
┌──────────────────────────────┐
│ Acme                         │   ← group header
├────────────┬─────────────────┤
│ INV-2      │        250 Kč   │
│ INV-4      │         25 Kč   │
│ Sum:       │        275 Kč   │   ← group subtotal
├──────────────────────────────┤
│ Beta                         │
│ INV-1      │        100 Kč   │
│ INV-3      │         50 Kč   │
│ Sum:       │        150 Kč   │
├────────────┼─────────────────┤
│ Sum:       │        425 Kč   │   ← grand total footer
└────────────┴─────────────────┘
```

## Configuration

| Method                          | Effect                                              |
| ------------------------------- | --------------------------------------------------- |
| `groupBy(string $column)`       | Group rows by a direct column on the model          |
| `groupLabel(string\|Closure)`   | Customize the group header label                    |
| `groupSummaries(bool)`          | Toggle per-group subtotal rows (default on)         |

### Group Labels

The header shows the raw group value by default. A string label becomes a
prefix; a closure receives the value and the group's first record:

```php
->groupBy('customer')->groupLabel('Customer')            // "Customer: Acme"
->groupBy('status')->groupLabel(fn ($value) => match ($value) {
    'paid' => '✓ Paid',
    'pending' => '⏳ Pending',
    default => ucfirst((string) $value),
})
```

Empty and `null` group values render as `—`.

## Sorting

Grouping prepends an ascending order on the group column, so any other sort —
the configured `defaultSort()` or a user's header click — applies **within**
each group. Sorting by the group column itself takes over completely: the
user's direction then controls group order (and groups stay contiguous, since
sorting by the group column orders groups by definition).

## Subtotals

Group subtotal rows appear automatically for every column with a summary; all
[aggregate types and formatting](summaries.md) apply. Subtotals are computed
in memory from the group's rows on the current page.

```php
TextColumn::make('total')
    ->summaryDecimals(0)
    ->summarizeSum('Sum')       // → group subtotal row + grand total footer
    ->summarizeAvg('Average'),  // each summary gets its own subtotal row
```

Disable the subtotal rows (keeping headers and the footer) with
`->groupSummaries(false)`.

## Limits

- **Direct columns only.** `groupBy('customer.name')` throws — grouping must
  order the query by the group column, which a relationship path can't do
  without a join. Expose the related value on the query (join + select alias)
  and group by the alias instead.
- **Pagination splits groups.** A group crossing a page boundary shows a
  partial subtotal on each page. For strict accounting reports, disable
  pagination (`->paginated(false)`) or raise `perPage()`.
- **Desktop table layout.** Group headers/subtotals render in the standard
  table layout; the stacked mobile card layout ignores grouping.
- **Exports** contain data rows and grand totals, not group subtotal rows.

## Related Docs

- [Summaries](summaries.md) — aggregate types, scopes, formatting
- [Columns](columns/index.md)
- [Table Overview](overview.md)
