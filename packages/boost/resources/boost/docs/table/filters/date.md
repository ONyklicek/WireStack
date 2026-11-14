---
order: 31
summary: A single date with before / after / on, a range with named presets that stay relative, or a month — applied to a column or a query of your own.
---

# DateFilter

Date filter with three modes: a single date (optionally with a choice of *is
before*, *is after*, *is on*…), a from/to range (optionally with named presets
such as *Last 7 days*), or a whole month.

```php
use NyonCode\WireTable\Filters\DateFilter;
```

## How It Works

**Every comparison is by day.** The filter never compares the stored value
directly: it uses `whereDate` (or `whereYear` + `whereMonth` for a month), so on a
datetime column *is on 3 March* matches 3 March at 14:00. That is also why a date
filter is never planned by the `QueryPlanner` — every mode runs through its own
`apply()`.

**The mode decides the shape of the state:**

| Mode | State | Operators |
|---|---|---|
| single date | `'2026-03-03'`, or `{value, operator}` with a picker | is on, is not on, is before, is on or before, is after, is on or after, is empty, is not empty |
| `->range()` | `{from, to}` | none — the two bounds are the comparison |
| `->range()` + `->presets()` | `{from, to, preset}` | none |
| `->month()` | `'2026-03'` | none |

`->operators()` on a range or a month offers nothing — those modes have no
operators to choose from, whichever order the calls come in. *is not on* keeps rows with no date; *is empty* is
`IS NULL`. Across a relation (`DateFilter::make('notes.created_at')`) a day is
compared inside `whereHas`, and *is not on* and *is empty* mean "no related
record is on that day" / "has no dated record" — `whereDoesntHave`, as for every
other filter.

**Presets resolve when the query runs.** A preset is stored by its **name**
(`last_7_days`), never by the dates it covered on the day it was picked. A saved
view or a shared URL of *Last 7 days* still means the last seven days next week;
two stored dates would have quietly become a fixed week in the past. The dates
are worked out on every query, with "today" read in `->timezone()` (default:
`app.timezone`). Weeks start on Monday. When a preset is set it wins over the
from/to beside it, and before every render the table writes today's dates for it
into from/to, so the picker shows the period the name stands for now. Picking
days by hand clears the name.

**A day in another timezone than the stored one.** Days are compared by
`DATE(column)` — "on 3 March" matches 3 March at 14:00. When `->timezone()`
differs from `app.timezone` and the column holds a moment (a `datetime` cast or
one of the model's timestamps), `DATE()` would be the stored day, not the
reader's: a row written at 23:30 UTC is the next day in Prague. There a day
becomes the range from its first moment to the next day's, converted to
`app.timezone` — `created_at >= '2026-11-11 23:00:00' AND created_at < '2026-11-12 23:00:00'`
for 12 November in Prague, which an index on the column can also serve. A plain
`date` column has no moment to shift and keeps `DATE()`.

**The controls are the forms pickers.** A single date is drawn with the forms
`DateTimePicker`, a range with the forms `DateRangePicker` — one box, two months
side by side with the presets beside them; on a phone the two pickers with the
presets as chips — in the header, in a column popover and in the filter panel
alike (`dateControl()` / `rangeControl()`). Only `month()` keeps the browser's
month input.

**Defaults.** Single date, no operator picker, no presets. `->presets()` turns
range mode on by itself.

## Single Date

The default mode renders one date input and matches that exact day.

```php
DateFilter::make('created_at')
// Applies: WHERE DATE(created_at) = '2024-01-15'
```

## Before, After, On

```php
DateFilter::make('due_at')
    ->operators()                                // every single-date operator
```

The picker uses date wording, not number wording: *is before* rather than *is
less than*, *is on or after* rather than *is at least*. The chip reads the same —
`Due: is before 2026-03-01`. A narrower set:

```php
use NyonCode\WireTable\Enums\FilterOperator;

DateFilter::make('due_at')
    ->operators([FilterOperator::LessThan, FilterOperator::GreaterThan, FilterOperator::IsEmpty])
    ->defaultOperator(FilterOperator::LessThan)  // a fresh filter asks "is before"
```

## Date Range

Call `range()` to pick a period with the forms `DateRangePicker` ("from" and
"to"). Either side may stay empty, so open-ended ranges work without extra
configuration.

```php
DateFilter::make('created_at')
    ->range()
// Renders the DateRangePicker.
// Both set:  WHERE DATE(created_at) >= from AND <= to
// Only from: WHERE DATE(created_at) >= from
// Only to:   WHERE DATE(created_at) <= to
```

## Presets

```php
DateFilter::make('created_at')
    ->presets()                                  // every preset; turns range() on
```

A select of named ranges appears beside the two dates. Offer only some, as
`DatePreset` cases or their values:

```php
use NyonCode\WireCore\Foundation\Enums\DatePreset;

DateFilter::make('created_at')
    ->presets([DatePreset::Today, DatePreset::Last7Days, DatePreset::ThisMonth, 'last_month'])
    ->timezone('Europe/Prague')                  // "today" for the people using the list
```

| `DatePreset` | Value | Covers (inclusive) |
|---|---|---|
| `Today` | `today` | today |
| `Yesterday` | `yesterday` | yesterday |
| `Last7Days` | `last_7_days` | today and the six days before |
| `Last30Days` | `last_30_days` | today and the 29 days before |
| `ThisWeek` | `this_week` | Monday – Sunday of this week |
| `LastWeek` | `last_week` | Monday – Sunday of last week |
| `ThisMonth` | `this_month` | the first to the last day of this month |
| `LastMonth` | `last_month` | the whole previous month |
| `ThisQuarter` | `this_quarter` | the whole current quarter |
| `ThisYear` | `this_year` | 1 January – 31 December |
| `LastYear` | `last_year` | the whole previous year |

With `Table::queryString()` the preset travels as its own parameter with a
`_preset` suffix (`?filter_created_at_preset=last_7_days`), beside `_from` and
`_to`. The chip prints the preset's label — `Created: Last 7 days`. An unknown
or no-longer-offered preset name in the state is ignored.

## Custom Labels

In range mode the labels are used as the input placeholders.

```php
DateFilter::make('period')
    ->column('created_at')
    ->range()
    ->fromLabel('Created after')
    ->toLabel('Created before')
```

## Month + Year

Call `month()` to filter by a whole month instead of an exact day. It renders a
native month picker (`<input type="month">`) and matches every record in the
selected month:

```php
DateFilter::make('billed_at')
    ->month()
// Value "2026-06" applies: WHERE YEAR(billed_at) = 2026 AND MONTH(billed_at) = 6
```

Combine with [`subRows()`](relationships.md#filtering-by-sub-row-values) to filter parents by
the month of their child records.

## Date Constraints

```php
DateFilter::make('birth_date')
    ->minDate('1900-01-01')
    ->maxDate(now())
```

## Range Behavior

| from | to | Condition |
|------|----|-----------|
| set | null | `WHERE DATE(column) >= from` |
| null | set | `WHERE DATE(column) <= to` |
| set | set | `WHERE DATE(column) >= from AND <= to` |
| null | null | No filter applied |

A preset, when one is set, supplies both bounds and wins over the typed dates.

## Extended Example

An invoice list: *Issued* answers "last month" and stays relative in a bookmarked
URL, *Due* answers "before the end of the quarter", *Paid* answers "never".

```php
<?php

namespace App\Livewire;

use App\Models\Invoice;
use Livewire\Component;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireCore\Foundation\Enums\DatePreset;
use NyonCode\WireTable\Enums\FilterOperator;
use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Table;

class ListInvoices extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Invoice::class)
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('issued_at')->date(),
                TextColumn::make('due_at')->date(),
                TextColumn::make('paid_at')->dateTime(),
            ])
            ->filters([
                DateFilter::make('issued_at') // [tl! focus:start]
                    ->label('Issued')
                    ->presets([DatePreset::ThisMonth, DatePreset::LastMonth, DatePreset::ThisQuarter])
                    ->timezone('Europe/Prague'),
                DateFilter::make('due_at')
                    ->label('Due')
                    ->operators([FilterOperator::LessThan, FilterOperator::GreaterThan])
                    ->defaultOperator(FilterOperator::LessThan),
                DateFilter::make('paid_at')
                    ->label('Paid')
                    ->operators([FilterOperator::Equals, FilterOperator::IsEmpty]), // [tl! focus:end]
            ])
            ->queryString();
    }

    public function render()
    {
        return view('livewire.list-invoices');
    }
}
```

## DateFilter API

```php
->range(bool $range = true)         // a from/to period (the DateRangePicker) instead of one date
->month(bool $month = true)         // month picker, matches whole month
->fromLabel(?string $label)         // "from" placeholder (default: 'From')
->toLabel(?string $label)           // "to" placeholder (default: 'To')
->minDate(string|DateTimeInterface|null $date)   // min selectable date
->maxDate(string|DateTimeInterface|null $date)   // max selectable date
->presets(array|bool $presets = true) // named ranges beside the dates: true = all, or list<DatePreset|string>; turns range() on
->timezone(?string $timezone)       // the timezone days are read in — a preset's "today", and the day bounds on a timestamp column; default app.timezone
->operators(array|bool $operators = true) // inherited — single date only: 'equals'|'not_equals'|'lt'|'lte'|'gt'|'gte'|'is_empty'|'is_not_empty'
->defaultOperator(FilterOperator|string $operator) // inherited — default 'equals' (is on)
->getPresets(): array               // list<DatePreset>
->hasPresets(): bool
->isRange(): bool
->isMonth(): bool
```

## Related

- [Filters](index.md#operators) — the operator vocabulary every filter shares
- [Column-Level Filters](column-level.md#date-presets) — `->filterPresets()` on a column
- [QueryBuilderFilter](query-builder.md) — a single date as one condition among several
