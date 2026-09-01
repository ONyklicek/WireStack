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
| `collapsibleGroups(bool)`       | Let a user fold a group away (default off)          |

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

## Collapsible Groups

`collapsibleGroups()` puts a chevron on every group header. Clicking it folds
that group's rows away and leaves the header and the group's subtotal on screen:

```php
->groupBy('customer')
->collapsibleGroups();
```

A collapsed group's rows are **not rendered at all** — not hidden with CSS, not
moved off screen. That is the whole point rather than an implementation detail:
several table behaviours read their rows straight out of the DOM (keyboard
navigation, range selection, the fill handle, live cell sync), and they keep
working through a collapse because the list they walk stays consistent with what
is on screen. It is also why this framework offers no virtual scrolling:
rendering only the viewport would need a parallel path for each of those four,
and three of them fail silently — a fill that writes nothing, a range that skips
rows.

What stays visible is what a folded group is worth reading: its header, and its
subtotal row. Twenty invoices fold away and the customer's total is still there.

The collapsed set is keyed by the group's own value, not by the rows in it, so a
group stays folded when its contents change — a filter that swaps every row in
`Overdue` leaves `Overdue` folded, which is what the user asked for. It lives in
the table state under `rows.collapsedGroups`, which means it survives a Livewire
round trip and is one of the things a [saved view](advanced.md#saved-views)
carries.

```php
use Livewire\Component;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

class ListInvoices extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Invoice::class)
            ->perPage(100)
            ->columns([
                TextColumn::make('number')->label('Invoice'),
                TextColumn::make('issued_at')->date(),
                TextColumn::make('total')
                    ->summaryDecimals(0)
                    ->summarizeSum('Sum'),
            ])
            ->groupBy('customer')      // [tl! focus:start]
            ->collapsibleGroups();     // [tl! focus:end]
    }
}
```

Collapsing is only meaningful on a grouped table: `collapsibleGroups()` without
`groupBy()` renders no toggles rather than erroring, and `hasCollapsibleGroups()`
reports `false`. Driving it from a custom view is two methods on the component —
`toggleGroup(string $group)` and `isGroupCollapsed(string $group)` — both keyed
by the same group value the header shows.

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
