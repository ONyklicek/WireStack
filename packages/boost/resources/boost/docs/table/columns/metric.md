---
order: 23
nav: false
---

# MetricColumn

A figure read as a measurement: the number, and optionally the trend behind it.

```php
use NyonCode\WireTable\Columns\MetricColumn;
```

## How It Works

It aggregates nothing. Dot notation already turns a relation path into a
`withCount` / `withSum` subquery, so `MetricColumn::make('orders.count')` is the
same single query it always was — see [Relation Paths](relations.md#aggregates).
What this type adds is presentation:

- the figure defaults it shares with [MoneyColumn](money.md) — right-aligned,
  tabular digits, no wrapping — so numbers line up down the column and the
  stacked mobile card picks the metric up as its headline figure;
- an optional **trend** drawn beside the number as a single SVG polyline.

## Basic Usage

```php
MetricColumn::make('orders.count')->label('Orders')      // withCount, right-aligned
MetricColumn::make('items.sum.amount')->label('Volume')  // withSum
MetricColumn::make('open_tickets')                       // an ordinary column works too
```

## Adding a trend

```php
MetricColumn::make('orders.count')
    ->label('Orders')
    ->trend(fn (Customer $record): array => $record->orders_per_month)
```

The series is a plain array of numbers in reading order, oldest first. It is
drawn by the same geometry a stats widget uses, so a table cell and a dashboard
tile show the same shape for the same data.

### The series is yours to supply, and that is deliberate

A per-record series cannot be derived from the column's own path: *orders per
month for this customer* is a second query shape, not a formatting of the first.
Passing a closure keeps the decision — and the N+1 — where you can see it.

```php
// Right: the series arrives with the page, and the closure only reads it.
public function table(Table $table): Table
{
    return $table
        ->model(Customer::class)
        ->query(fn ($query) => $query->withCount('orders')->with('monthlyTotals'))  // [tl! focus]
        ->columns([
            TextColumn::make('name')->sortable(),
            MetricColumn::make('orders_count')                                      // [tl! focus]
                ->label('Orders')                                                   // [tl! focus]
                ->trend(fn (Customer $r): array => $r->monthlyTotals                // [tl! focus]
                    ->pluck('total')->all()),                                       // [tl! focus]
        ]);
}

// Wrong: this queries once per row, exactly as it reads.
->trend(fn (Customer $r): array => $r->orders()->sum('total') ? [] : [])
```

## What a series has to look like

| Series | Drawn as |
|--------|----------|
| `[1, 5, 3]` | the curve |
| `[4, 4, 4]` | a flat line through the **middle** — steady, not fallen to zero |
| `[7]` | a flat line; one reading is not a trend, but it is not nothing either |
| `[4, null, 4]` | the two readings there were — see below |
| `[]` | nothing at all: the cell is a plain figure |

Non-numeric readings are **dropped, not coerced**. A `null` month means "no
reading", and plotting it as `0` invents a crash to the floor and back that
never happened.

## Colouring the trend

The stroke follows the column's colour, or one stated for the trend alone:

```php
MetricColumn::make('churn')->color('danger')             // both figure and line
MetricColumn::make('orders.count')->trend($series, 'success')  // just the line
```

## Overriding the defaults

```php
MetricColumn::make('orders.count')
    ->alignment('left')     // …and it stops being the stacked card's metric
    ->textSize('lg')
```

## MetricColumn API

```php
->trend(Closure $callback, ?string $color = null)   // fn (Model $record): array<int, int|float>
->hasTrend(): bool
->getTrend(Model $record): array
->getTrendColorClass(): string
```

Everything from [TextColumn](text.md) — formatting, limits, prefixes, summaries
— applies unchanged.
