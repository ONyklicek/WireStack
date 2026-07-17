---
order: 31
nav: false
---

# DateFilter

Date filter with single date or date range mode. Renders native date input(s).

```php
use NyonCode\WireTable\Filters\DateFilter;
```

## Single Date

The default mode renders one date input and matches that exact day.

```php
DateFilter::make('created_at')
// Applies: WHERE DATE(created_at) = '2024-01-15'
```

## Date Range

Call `range()` to render two date inputs ("from" and "to"). The user can fill
either side, so open-ended ranges work without extra configuration.

```php
DateFilter::make('created_at')
    ->range()
// Renders two date inputs.
// Both set:  WHERE DATE(created_at) >= from AND <= to
// Only from: WHERE DATE(created_at) >= from
// Only to:   WHERE DATE(created_at) <= to
```

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
    ->maxDate(now()->format('Y-m-d'))
```

## DateFilter API

```php
->range(bool $range = true)         // two inputs (from/to) instead of one
->month(bool $month = true)         // month picker, matches whole month
->fromLabel(string $label)          // "from" placeholder (default: 'From')
->toLabel(string $label)            // "to" placeholder (default: 'To')
->minDate(string $date)             // min selectable date
->maxDate(string $date)             // max selectable date
```

## Range Behavior

| from | to | Condition |
|------|----|-----------|
| set | null | `WHERE DATE(column) >= from` |
| null | set | `WHERE DATE(column) <= to` |
| set | set | `WHERE DATE(column) >= from AND <= to` |
| null | null | No filter applied |
